<?php

declare(strict_types=1);

namespace Shopologic\Core\GraphQL;

/**
 * GraphQL lexer for tokenizing queries
 */
class Lexer
{
    private string $source;
    private int $position = 0;
    private int $line = 1;
    private int $column = 1;
    
    private const TOKEN_PATTERNS = [
        'COMMENT' => '/^#[^\n\r]*/A',
        'WHITESPACE' => '/^[\s,]+/A',
        'INT' => '/^-?(0|[1-9][0-9]*)/A',
        'FLOAT' => '/^-?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][+-]?[0-9]+)?/A',
        'STRING' => '/^"([^"\\\\]|\\\\.)*"/A',
        'BLOCK_STRING' => '/^"""((?:(?!""").)*)"""/As',
        'NAME' => '/^[_A-Za-z][_0-9A-Za-z]*/A',
        'PUNCTUATION' => '/^[!$():=@\[\]{|}]/A',
        'SPREAD' => '/^\.\.\./A'
    ];
    
    private const KEYWORDS = [
        'query', 'mutation', 'subscription', 'fragment', 'on',
        'type', 'interface', 'union', 'enum', 'input', 'extend',
        'schema', 'scalar', 'directive', 'implements', 'repeatable',
        'true', 'false', 'null'
    ];
    
    public function __construct(string $source)
    {
        $this->source = $source;
    }
    
    /**
     * Get next token
     */
    public function nextToken(): ?Token
    {
        $this->skipWhitespaceAndComments();
        
        if ($this->position >= strlen($this->source)) {
            return new Token('EOF', '', $this->position, $this->line, $this->column);
        }
        
        // Check for spread operator first
        if (substr($this->source, $this->position, 3) === '...') {
            $token = new Token('SPREAD', '...', $this->position, $this->line, $this->column);
            $this->advance(3);
            return $token;
        }
        
        // Check for punctuation
        $char = $this->source[$this->position];
        if (strpos('!$():=@[]{|}', $char) !== false) {
            $token = new Token($char, $char, $this->position, $this->line, $this->column);
            $this->advance(1);
            return $token;
        }
        
        // Check for numbers
        if (preg_match(self::TOKEN_PATTERNS['FLOAT'], $this->source, $matches, 0, $this->position)) {
            $value = $matches[0];
            if (strpos($value, '.') !== false || strpos($value, 'e') !== false || strpos($value, 'E') !== false) {
                $token = new Token('FLOAT', $value, $this->position, $this->line, $this->column);
            } else {
                $token = new Token('INT', $value, $this->position, $this->line, $this->column);
            }
            $this->advance(strlen($value));
            return $token;
        }
        
        // Check for strings
        if ($this->source[$this->position] === '"') {
            if (substr($this->source, $this->position, 3) === '"""') {
                return $this->readBlockString();
            }
            return $this->readString();
        }
        
        // Check for names/keywords
        if (preg_match(self::TOKEN_PATTERNS['NAME'], $this->source, $matches, 0, $this->position)) {
            $value = $matches[0];
            $type = in_array($value, self::KEYWORDS) ? strtoupper($value) : 'NAME';
            
            // Special handling for boolean values
            if ($value === 'true' || $value === 'false') {
                $type = 'BOOLEAN';
            } elseif ($value === 'null') {
                $type = 'NULL';
            }
            
            $token = new Token($type, $value, $this->position, $this->line, $this->column);
            $this->advance(strlen($value));
            return $token;
        }
        
        throw new \Exception("Unexpected character '{$char}' at line {$this->line}, column {$this->column}");
    }
    
    /**
     * Peek at next token without consuming it
     */
    public function peek(): ?Token
    {
        $savedPosition = $this->position;
        $savedLine = $this->line;
        $savedColumn = $this->column;
        
        $token = $this->nextToken();
        
        $this->position = $savedPosition;
        $this->line = $savedLine;
        $this->column = $savedColumn;
        
        return $token;
    }
    
    /**
     * Skip whitespace and comments
     */
    private function skipWhitespaceAndComments(): void
    {
        while ($this->position < strlen($this->source)) {
            // Skip whitespace
            if (preg_match(self::TOKEN_PATTERNS['WHITESPACE'], $this->source, $matches, 0, $this->position)) {
                $this->advance(strlen($matches[0]));
                continue;
            }
            
            // Skip comments
            if (preg_match(self::TOKEN_PATTERNS['COMMENT'], $this->source, $matches, 0, $this->position)) {
                $this->advance(strlen($matches[0]));
                continue;
            }
            
            break;
        }
    }
    
    /**
     * Read string token
     */
    private function readString(): Token
    {
        $start = $this->position;
        $startLine = $this->line;
        $startColumn = $this->column;
        
        $this->advance(1); // Skip opening quote
        $value = '';
        
        while ($this->position < strlen($this->source) && $this->source[$this->position] !== '"') {
            if ($this->source[$this->position] === '\\') {
                $this->advance(1);
                if ($this->position >= strlen($this->source)) {
                    throw new \Exception('Unterminated string');
                }
                
                $escaped = $this->source[$this->position];
                switch ($escaped) {
                    case '"':
                    case '\\':
                    case '/':
                        $value .= $escaped;
                        break;
                    case 'b':
                        $value .= "\b";
                        break;
                    case 'f':
                        $value .= "\f";
                        break;
                    case 'n':
                        $value .= "\n";
                        break;
                    case 'r':
                        $value .= "\r";
                        break;
                    case 't':
                        $value .= "\t";
                        break;
                    case 'u':
                        $this->advance(1);
                        $hex = substr($this->source, $this->position, 4);
                        if (strlen($hex) < 4 || !ctype_xdigit($hex)) {
                            throw new \Exception('Invalid unicode escape sequence');
                        }
                        $value .= json_decode('"\\u' . $hex . '"');
                        $this->advance(3);
                        break;
                    default:
                        throw new \Exception("Invalid escape sequence '\\{$escaped}'");
                }
            } else {
                $value .= $this->source[$this->position];
            }
            $this->advance(1);
        }
        
        if ($this->position >= strlen($this->source)) {
            throw new \Exception('Unterminated string');
        }
        
        $this->advance(1); // Skip closing quote
        
        return new Token('STRING', $value, $start, $startLine, $startColumn);
    }
    
    /**
     * Read block string token
     */
    private function readBlockString(): Token
    {
        $start = $this->position;
        $startLine = $this->line;
        $startColumn = $this->column;
        
        $this->advance(3); // Skip opening quotes
        
        if (preg_match('/^((?:(?!""").)*)"""/s', $this->source, $matches, 0, $this->position)) {
            $value = $matches[1];
            $this->advance(strlen($matches[0]));
            
            // Process block string value according to spec
            $value = $this->processBlockStringValue($value);
            
            return new Token('STRING', $value, $start, $startLine, $startColumn);
        }
        
        throw new \Exception('Unterminated block string');
    }
    
    /**
     * Process block string value
     */
    private function processBlockStringValue(string $value): string
    {
        // Remove common indentation
        $lines = explode("\n", $value);
        $commonIndent = null;
        
        foreach ($lines as $line) {
            $indent = strlen($line) - strlen(ltrim($line));
            if (strlen(trim($line)) > 0) {
                if ($commonIndent === null || $indent < $commonIndent) {
                    $commonIndent = $indent;
                }
            }
        }
        
        if ($commonIndent !== null && $commonIndent > 0) {
            $lines = array_map(function ($line) use ($commonIndent) {
                return substr($line, $commonIndent);
            }, $lines);
        }
        
        // Remove leading and trailing blank lines
        while (count($lines) > 0 && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while (count($lines) > 0 && trim($lines[count($lines) - 1]) === '') {
            array_pop($lines);
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Advance position
     */
    private function advance(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            if ($this->position < strlen($this->source)) {
                if ($this->source[$this->position] === "\n") {
                    $this->line++;
                    $this->column = 1;
                } else {
                    $this->column++;
                }
                $this->position++;
            }
        }
    }
}

/**
 * Token class
 */
class Token
{
    public string $type;
    public string $value;
    public int $position;
    public int $line;
    public int $column;
    
    public function __construct(
        string $type,
        string $value,
        int $position,
        int $line,
        int $column
    ) {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
        $this->line = $line;
        $this->column = $column;
    }
}