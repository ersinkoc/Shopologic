<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser;

/**
 * Tokenizes template source code
 */
class Lexer
{
    private const PRINT_START = '{{';
    private const PRINT_END = '}}';
    private const BLOCK_START = '{%';
    private const BLOCK_END = '%}';
    private const COMMENT_START = '{#';
    private const COMMENT_END = '#}';

    private string $source = '';
    private int $position = 0;
    private int $line = 1;
    private array $tokens = [];

    /**
     * Tokenize source code
     */
    public function tokenize(string $source): array
    {
        $this->source = $source;
        $this->position = 0;
        $this->line = 1;
        $this->tokens = [];

        while ($this->position < strlen($this->source)) {
            $this->scanToken();
        }

        $this->tokens[] = new Token(TokenType::EOF, '', $this->line);

        return $this->tokens;
    }

    /**
     * Scan next token
     */
    private function scanToken(): void
    {
        // Check for template tags
        if ($this->matchString(self::PRINT_START)) {
            $this->scanPrintTag();
        } elseif ($this->matchString(self::BLOCK_START)) {
            $this->scanBlockTag();
        } elseif ($this->matchString(self::COMMENT_START)) {
            $this->scanComment();
        } else {
            $this->scanText();
        }
    }

    /**
     * Scan print tag {{ ... }}
     */
    private function scanPrintTag(): void
    {
        $this->addToken(TokenType::PRINT_START, self::PRINT_START);
        
        $this->scanInsideTag(self::PRINT_END);
        
        if ($this->matchString(self::PRINT_END)) {
            $this->addToken(TokenType::PRINT_END, self::PRINT_END);
        } else {
            throw new LexerException('Unclosed print tag');
        }
    }

    /**
     * Scan block tag {% ... %}
     */
    private function scanBlockTag(): void
    {
        $this->addToken(TokenType::BLOCK_START, self::BLOCK_START);
        
        $this->scanInsideTag(self::BLOCK_END);
        
        if ($this->matchString(self::BLOCK_END)) {
            $this->addToken(TokenType::BLOCK_END, self::BLOCK_END);
        } else {
            throw new LexerException('Unclosed block tag');
        }
    }

    /**
     * Scan comment {# ... #}
     */
    private function scanComment(): void
    {
        // Skip until end of comment
        while (!$this->isAtEnd() && !$this->checkString(self::COMMENT_END)) {
            if ($this->current() === "\n") {
                $this->line++;
            }
            $this->advance();
        }

        if ($this->matchString(self::COMMENT_END)) {
            // Comment consumed, don't add token
        } else {
            throw new LexerException('Unclosed comment');
        }
    }

    /**
     * Scan plain text
     */
    private function scanText(): void
    {
        $start = $this->position;
        
        while (!$this->isAtEnd()) {
            // Stop at any template tag
            if ($this->checkString(self::PRINT_START) || 
                $this->checkString(self::BLOCK_START) || 
                $this->checkString(self::COMMENT_START)) {
                break;
            }

            if ($this->current() === "\n") {
                $this->line++;
            }
            
            $this->advance();
        }

        $text = substr($this->source, $start, $this->position - $start);
        if ($text !== '') {
            $this->addToken(TokenType::TEXT, $text);
        }
    }

    /**
     * Scan inside a tag
     */
    private function scanInsideTag(string $endTag): void
    {
        $this->skipWhitespace();

        while (!$this->isAtEnd() && !$this->checkString($endTag)) {
            $this->skipWhitespace();

            if ($this->isAtEnd() || $this->checkString($endTag)) {
                break;
            }

            // Numbers
            if ($this->isDigit($this->current())) {
                $this->scanNumber();
            }
            // Strings
            elseif ($this->current() === '"' || $this->current() === "'") {
                $this->scanString();
            }
            // Names (identifiers)
            elseif ($this->isAlpha($this->current())) {
                $this->scanName();
            }
            // Operators and punctuation
            else {
                $this->scanOperator();
            }

            $this->skipWhitespace();
        }
    }

    /**
     * Scan number
     */
    private function scanNumber(): void
    {
        $start = $this->position;

        while ($this->isDigit($this->current())) {
            $this->advance();
        }

        // Decimal part
        if ($this->current() === '.' && $this->isDigit($this->peek())) {
            $this->advance(); // Consume '.'
            
            while ($this->isDigit($this->current())) {
                $this->advance();
            }
        }

        $value = substr($this->source, $start, $this->position - $start);
        $this->addToken(TokenType::NUMBER, floatval($value));
    }

    /**
     * Scan string
     */
    private function scanString(): void
    {
        $quote = $this->current();
        $start = $this->position;
        $this->advance(); // Opening quote

        while (!$this->isAtEnd() && $this->current() !== $quote) {
            if ($this->current() === '\\') {
                $this->advance(); // Skip escaped character
            }
            if ($this->current() === "\n") {
                $this->line++;
            }
            $this->advance();
        }

        if ($this->isAtEnd()) {
            throw new LexerException('Unterminated string');
        }

        $this->advance(); // Closing quote

        $value = substr($this->source, $start, $this->position - $start);
        $this->addToken(TokenType::STRING, $value);
    }

    /**
     * Scan name (identifier)
     */
    private function scanName(): void
    {
        $start = $this->position;

        while ($this->isAlphaNumeric($this->current())) {
            $this->advance();
        }

        $value = substr($this->source, $start, $this->position - $start);
        $this->addToken(TokenType::NAME, $value);
    }

    /**
     * Scan operator
     */
    private function scanOperator(): void
    {
        $char = $this->current();
        $this->advance();

        switch ($char) {
            case '+':
                $this->addToken(TokenType::PLUS, '+');
                break;
            case '-':
                $this->addToken(TokenType::MINUS, '-');
                break;
            case '*':
                $this->addToken(TokenType::MULTIPLY, '*');
                break;
            case '/':
                $this->addToken(TokenType::DIVIDE, '/');
                break;
            case '=':
                if ($this->current() === '=') {
                    $this->advance();
                    $this->addToken(TokenType::NAME, '==');
                } else {
                    $this->addToken(TokenType::ASSIGN, '=');
                }
                break;
            case '!':
                if ($this->current() === '=') {
                    $this->advance();
                    $this->addToken(TokenType::NAME, '!=');
                }
                break;
            case '<':
                if ($this->current() === '=') {
                    $this->advance();
                    $this->addToken(TokenType::NAME, '<=');
                } else {
                    $this->addToken(TokenType::NAME, '<');
                }
                break;
            case '>':
                if ($this->current() === '=') {
                    $this->advance();
                    $this->addToken(TokenType::NAME, '>=');
                } else {
                    $this->addToken(TokenType::NAME, '>');
                }
                break;
            case '.':
                $this->addToken(TokenType::DOT, '.');
                break;
            case ',':
                $this->addToken(TokenType::COMMA, ',');
                break;
            case ':':
                $this->addToken(TokenType::COLON, ':');
                break;
            case '|':
                $this->addToken(TokenType::PIPE, '|');
                break;
            case '(':
                $this->addToken(TokenType::LPAREN, '(');
                break;
            case ')':
                $this->addToken(TokenType::RPAREN, ')');
                break;
            case '[':
                $this->addToken(TokenType::LBRACKET, '[');
                break;
            case ']':
                $this->addToken(TokenType::RBRACKET, ']');
                break;
            case '{':
                $this->addToken(TokenType::LBRACE, '{');
                break;
            case '}':
                $this->addToken(TokenType::RBRACE, '}');
                break;
        }
    }

    /**
     * Skip whitespace
     */
    private function skipWhitespace(): void
    {
        while (!$this->isAtEnd()) {
            switch ($this->current()) {
                case ' ':
                case "\r":
                case "\t":
                    $this->advance();
                    break;
                case "\n":
                    $this->line++;
                    $this->advance();
                    break;
                default:
                    return;
            }
        }
    }

    // Helper methods

    private function current(): string
    {
        if ($this->isAtEnd()) {
            return "\0";
        }
        return $this->source[$this->position];
    }

    private function peek(int $offset = 1): string
    {
        $pos = $this->position + $offset;
        if ($pos >= strlen($this->source)) {
            return "\0";
        }
        return $this->source[$pos];
    }

    private function advance(): void
    {
        if (!$this->isAtEnd()) {
            $this->position++;
        }
    }

    private function isAtEnd(): bool
    {
        return $this->position >= strlen($this->source);
    }

    private function checkString(string $str): bool
    {
        $len = strlen($str);
        if ($this->position + $len > strlen($this->source)) {
            return false;
        }
        return substr($this->source, $this->position, $len) === $str;
    }

    private function matchString(string $str): bool
    {
        if ($this->checkString($str)) {
            $this->position += strlen($str);
            return true;
        }
        return false;
    }

    private function isDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private function isAlpha(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z') ||
               ($char >= 'A' && $char <= 'Z') ||
               $char === '_';
    }

    private function isAlphaNumeric(string $char): bool
    {
        return $this->isAlpha($char) || $this->isDigit($char);
    }

    private function addToken(string $type, $value): void
    {
        $this->tokens[] = new Token($type, $value, $this->line);
    }
}

class LexerException extends \Exception {}