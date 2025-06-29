<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Parser;

use Shopologic\Core\Theme\Parser\Node\TemplateNode;
use Shopologic\Core\Theme\Parser\Node\TextNode;
use Shopologic\Core\Theme\Parser\Node\PrintNode;
use Shopologic\Core\Theme\Parser\Node\IfNode;
use Shopologic\Core\Theme\Parser\Node\ForNode;
use Shopologic\Core\Theme\Parser\Node\BlockNode;
use Shopologic\Core\Theme\Parser\Node\ExtendsNode;
use Shopologic\Core\Theme\Parser\Node\IncludeNode;
use Shopologic\Core\Theme\Parser\Node\SetNode;
use Shopologic\Core\Theme\Parser\Node\ComponentNode;
use Shopologic\Core\Theme\Parser\Node\HookNode;

/**
 * Parses template syntax into an AST
 */
class TemplateParser
{
    private Lexer $lexer;
    private array $tokens = [];
    private int $position = 0;
    private array $blocks = [];
    private ?string $extends = null;

    public function __construct()
    {
        $this->lexer = new Lexer();
    }

    /**
     * Parse template source into AST
     */
    public function parse(string $source): TemplateNode
    {
        // Tokenize source
        $this->tokens = $this->lexer->tokenize($source);
        $this->position = 0;
        $this->blocks = [];
        $this->extends = null;

        // Parse tokens into nodes
        $nodes = $this->parseUntil(TokenType::EOF);

        // Create root template node
        $template = new TemplateNode($nodes);
        
        if ($this->extends) {
            $template->setExtends($this->extends);
        }
        
        $template->setBlocks($this->blocks);

        return $template;
    }

    /**
     * Parse until specific token type
     */
    private function parseUntil(string ...$types): array
    {
        $nodes = [];

        while (!$this->isEof()) {
            $token = $this->current();

            if (in_array($token->type, $types)) {
                break;
            }

            $node = $this->parseNode();
            if ($node) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * Parse a single node
     */
    private function parseNode()
    {
        $token = $this->current();

        switch ($token->type) {
            case TokenType::TEXT:
                return $this->parseText();

            case TokenType::PRINT_START:
                return $this->parsePrint();

            case TokenType::BLOCK_START:
                return $this->parseBlock();

            default:
                $this->advance();
                return null;
        }
    }

    /**
     * Parse text node
     */
    private function parseText(): TextNode
    {
        $token = $this->expect(TokenType::TEXT);
        return new TextNode($token->value);
    }

    /**
     * Parse print statement {{ expression }}
     */
    private function parsePrint(): PrintNode
    {
        $this->expect(TokenType::PRINT_START);
        
        $expression = $this->parseExpression();
        $filters = [];

        // Parse filters
        while ($this->match(TokenType::PIPE)) {
            $filterName = $this->expect(TokenType::NAME)->value;
            $arguments = [];

            if ($this->match(TokenType::LPAREN)) {
                $arguments = $this->parseArguments();
                $this->expect(TokenType::RPAREN);
            }

            $filters[] = ['name' => $filterName, 'arguments' => $arguments];
        }

        $this->expect(TokenType::PRINT_END);

        return new PrintNode($expression, $filters);
    }

    /**
     * Parse block statement {% ... %}
     */
    private function parseBlock()
    {
        $this->expect(TokenType::BLOCK_START);
        
        $name = $this->expect(TokenType::NAME)->value;

        switch ($name) {
            case 'if':
                return $this->parseIf();

            case 'for':
                return $this->parseFor();

            case 'block':
                return $this->parseBlockTag();

            case 'extends':
                return $this->parseExtends();

            case 'include':
                return $this->parseInclude();

            case 'set':
                return $this->parseSet();

            case 'component':
                return $this->parseComponent();

            case 'hook':
                return $this->parseHook();

            default:
                throw new ParserException(sprintf('Unknown tag "%s"', $name));
        }
    }

    /**
     * Parse if statement
     */
    private function parseIf(): IfNode
    {
        $condition = $this->parseExpression();
        $this->expect(TokenType::BLOCK_END);

        $ifBody = $this->parseUntil(TokenType::BLOCK_START);
        $elseIfClauses = [];
        $elseBody = [];

        while ($this->matchSequence(TokenType::BLOCK_START, 'elseif')) {
            $this->advance(); // Skip 'elseif'
            $elseIfCondition = $this->parseExpression();
            $this->expect(TokenType::BLOCK_END);
            
            $elseIfBody = $this->parseUntil(TokenType::BLOCK_START);
            $elseIfClauses[] = ['condition' => $elseIfCondition, 'body' => $elseIfBody];
        }

        if ($this->matchSequence(TokenType::BLOCK_START, 'else')) {
            $this->advance(); // Skip 'else'
            $this->expect(TokenType::BLOCK_END);
            $elseBody = $this->parseUntil(TokenType::BLOCK_START);
        }

        $this->expectSequence(TokenType::BLOCK_START, 'endif');
        $this->expect(TokenType::BLOCK_END);

        return new IfNode($condition, $ifBody, $elseIfClauses, $elseBody);
    }

    /**
     * Parse for loop
     */
    private function parseFor(): ForNode
    {
        $itemVar = $this->expect(TokenType::NAME)->value;
        $keyVar = null;

        if ($this->match(TokenType::COMMA)) {
            $keyVar = $itemVar;
            $itemVar = $this->expect(TokenType::NAME)->value;
        }

        $this->expectValue('in');
        $collection = $this->parseExpression();
        $this->expect(TokenType::BLOCK_END);

        $body = $this->parseUntil(TokenType::BLOCK_START);
        $elseBody = [];

        if ($this->matchSequence(TokenType::BLOCK_START, 'else')) {
            $this->advance(); // Skip 'else'
            $this->expect(TokenType::BLOCK_END);
            $elseBody = $this->parseUntil(TokenType::BLOCK_START);
        }

        $this->expectSequence(TokenType::BLOCK_START, 'endfor');
        $this->expect(TokenType::BLOCK_END);

        return new ForNode($itemVar, $keyVar, $collection, $body, $elseBody);
    }

    /**
     * Parse block tag
     */
    private function parseBlockTag(): BlockNode
    {
        $name = $this->expect(TokenType::NAME)->value;
        $this->expect(TokenType::BLOCK_END);

        $body = $this->parseUntil(TokenType::BLOCK_START);

        $this->expectSequence(TokenType::BLOCK_START, 'endblock');
        
        // Optional block name repetition
        if ($this->current()->type === TokenType::NAME) {
            $this->advance();
        }
        
        $this->expect(TokenType::BLOCK_END);

        $this->blocks[$name] = $body;

        return new BlockNode($name, $body);
    }

    /**
     * Parse extends tag
     */
    private function parseExtends(): ?ExtendsNode
    {
        if ($this->position > 1) {
            throw new ParserException('{% extends %} must be the first tag in the template');
        }

        $parent = $this->expect(TokenType::STRING)->value;
        $this->expect(TokenType::BLOCK_END);

        $this->extends = trim($parent, '"\'');

        return new ExtendsNode($this->extends);
    }

    /**
     * Parse include tag
     */
    private function parseInclude(): IncludeNode
    {
        $template = $this->expect(TokenType::STRING)->value;
        $template = trim($template, '"\'');
        
        $variables = null;
        
        if ($this->matchValue('with')) {
            $variables = $this->parseExpression();
        }

        $this->expect(TokenType::BLOCK_END);

        return new IncludeNode($template, $variables);
    }

    /**
     * Parse set tag
     */
    private function parseSet(): SetNode
    {
        $variable = $this->expect(TokenType::NAME)->value;
        $this->expect(TokenType::ASSIGN);
        $value = $this->parseExpression();
        $this->expect(TokenType::BLOCK_END);

        return new SetNode($variable, $value);
    }

    /**
     * Parse component tag
     */
    private function parseComponent(): ComponentNode
    {
        $name = $this->expect(TokenType::STRING)->value;
        $name = trim($name, '"\'');
        
        $props = null;
        
        if ($this->matchValue('with')) {
            $props = $this->parseExpression();
        }

        $this->expect(TokenType::BLOCK_END);

        return new ComponentNode($name, $props);
    }

    /**
     * Parse hook tag
     */
    private function parseHook(): HookNode
    {
        $name = $this->expect(TokenType::STRING)->value;
        $name = trim($name, '"\'');
        
        $data = null;
        
        if ($this->matchValue('with')) {
            $data = $this->parseExpression();
        }

        $this->expect(TokenType::BLOCK_END);

        return new HookNode($name, $data);
    }

    /**
     * Parse expression
     */
    private function parseExpression()
    {
        return $this->parseOr();
    }

    /**
     * Parse OR expression
     */
    private function parseOr()
    {
        $left = $this->parseAnd();

        while ($this->matchValue('or')) {
            $right = $this->parseAnd();
            $left = ['type' => 'or', 'left' => $left, 'right' => $right];
        }

        return $left;
    }

    /**
     * Parse AND expression
     */
    private function parseAnd()
    {
        $left = $this->parseComparison();

        while ($this->matchValue('and')) {
            $right = $this->parseComparison();
            $left = ['type' => 'and', 'left' => $left, 'right' => $right];
        }

        return $left;
    }

    /**
     * Parse comparison
     */
    private function parseComparison()
    {
        $left = $this->parseAddition();
        
        $operators = ['==', '!=', '<', '>', '<=', '>='];
        $token = $this->current();
        
        if (in_array($token->value, $operators)) {
            $op = $token->value;
            $this->advance();
            $right = $this->parseAddition();
            return ['type' => 'comparison', 'operator' => $op, 'left' => $left, 'right' => $right];
        }

        return $left;
    }

    /**
     * Parse addition/subtraction
     */
    private function parseAddition()
    {
        $left = $this->parseMultiplication();

        while ($this->match(TokenType::PLUS) || $this->match(TokenType::MINUS)) {
            $op = $this->previous()->type;
            $right = $this->parseMultiplication();
            $left = ['type' => 'binary', 'operator' => $op, 'left' => $left, 'right' => $right];
        }

        return $left;
    }

    /**
     * Parse multiplication/division
     */
    private function parseMultiplication()
    {
        $left = $this->parseUnary();

        while ($this->match(TokenType::MULTIPLY) || $this->match(TokenType::DIVIDE)) {
            $op = $this->previous()->type;
            $right = $this->parseUnary();
            $left = ['type' => 'binary', 'operator' => $op, 'left' => $left, 'right' => $right];
        }

        return $left;
    }

    /**
     * Parse unary expression
     */
    private function parseUnary()
    {
        if ($this->matchValue('not') || $this->match(TokenType::MINUS)) {
            $op = $this->previous()->value;
            $expr = $this->parseUnary();
            return ['type' => 'unary', 'operator' => $op, 'expression' => $expr];
        }

        return $this->parsePrimary();
    }

    /**
     * Parse primary expression
     */
    private function parsePrimary()
    {
        // Numbers
        if ($this->match(TokenType::NUMBER)) {
            return $this->previous()->value;
        }

        // Strings
        if ($this->match(TokenType::STRING)) {
            return trim($this->previous()->value, '"\'');
        }

        // Booleans
        if ($this->matchValue('true')) {
            return true;
        }
        
        if ($this->matchValue('false')) {
            return false;
        }
        
        if ($this->matchValue('null')) {
            return null;
        }

        // Arrays
        if ($this->match(TokenType::LBRACKET)) {
            return $this->parseArray();
        }

        // Objects
        if ($this->match(TokenType::LBRACE)) {
            return $this->parseObject();
        }

        // Parentheses
        if ($this->match(TokenType::LPAREN)) {
            $expr = $this->parseExpression();
            $this->expect(TokenType::RPAREN);
            return $expr;
        }

        // Variables
        if ($this->match(TokenType::NAME)) {
            return $this->parseVariable();
        }

        throw new ParserException('Unexpected token: ' . $this->current()->type);
    }

    /**
     * Parse variable access
     */
    private function parseVariable()
    {
        $name = $this->previous()->value;
        $path = [];

        while (true) {
            if ($this->match(TokenType::DOT)) {
                $path[] = $this->expect(TokenType::NAME)->value;
            } elseif ($this->match(TokenType::LBRACKET)) {
                $path[] = $this->parseExpression();
                $this->expect(TokenType::RBRACKET);
            } else {
                break;
            }
        }

        return ['type' => 'variable', 'name' => $name, 'path' => $path];
    }

    /**
     * Parse array
     */
    private function parseArray(): array
    {
        $items = [];

        while (!$this->check(TokenType::RBRACKET)) {
            $items[] = $this->parseExpression();
            
            if (!$this->match(TokenType::COMMA)) {
                break;
            }
        }

        $this->expect(TokenType::RBRACKET);

        return $items;
    }

    /**
     * Parse object/hash
     */
    private function parseObject(): array
    {
        $items = [];

        while (!$this->check(TokenType::RBRACE)) {
            $key = $this->expect(TokenType::NAME)->value;
            $this->expect(TokenType::COLON);
            $value = $this->parseExpression();
            
            $items[$key] = $value;
            
            if (!$this->match(TokenType::COMMA)) {
                break;
            }
        }

        $this->expect(TokenType::RBRACE);

        return $items;
    }

    /**
     * Parse function arguments
     */
    private function parseArguments(): array
    {
        $args = [];

        while (!$this->check(TokenType::RPAREN)) {
            $args[] = $this->parseExpression();
            
            if (!$this->match(TokenType::COMMA)) {
                break;
            }
        }

        return $args;
    }

    // Helper methods

    private function current(): Token
    {
        return $this->tokens[$this->position] ?? new Token(TokenType::EOF, '', 0);
    }

    private function previous(): Token
    {
        return $this->tokens[$this->position - 1];
    }

    private function advance(): void
    {
        if (!$this->isEof()) {
            $this->position++;
        }
    }

    private function isEof(): bool
    {
        return $this->current()->type === TokenType::EOF;
    }

    private function check(string $type): bool
    {
        return $this->current()->type === $type;
    }

    private function checkValue(string $value): bool
    {
        return $this->current()->value === $value;
    }

    private function match(string $type): bool
    {
        if ($this->check($type)) {
            $this->advance();
            return true;
        }
        return false;
    }

    private function matchValue(string $value): bool
    {
        if ($this->checkValue($value)) {
            $this->advance();
            return true;
        }
        return false;
    }

    private function matchSequence(string $type, string $value): bool
    {
        $pos = $this->position;
        
        if ($this->match($type) && $this->matchValue($value)) {
            $this->position = $pos; // Reset position
            return true;
        }
        
        $this->position = $pos;
        return false;
    }

    private function expect(string $type): Token
    {
        if (!$this->check($type)) {
            throw new ParserException(sprintf('Expected %s, got %s', $type, $this->current()->type));
        }
        
        $token = $this->current();
        $this->advance();
        return $token;
    }

    private function expectValue(string $value): void
    {
        if (!$this->checkValue($value)) {
            throw new ParserException(sprintf('Expected "%s", got "%s"', $value, $this->current()->value));
        }
        $this->advance();
    }

    private function expectSequence(string $type, string $value): void
    {
        $this->expect($type);
        $this->expectValue($value);
    }
}

class ParserException extends \Exception {}

class Token
{
    public string $type;
    public $value;
    public int $line;

    public function __construct(string $type, $value, int $line)
    {
        $this->type = $type;
        $this->value = $value;
        $this->line = $line;
    }
}

class TokenType
{
    public const EOF = 'EOF';
    public const TEXT = 'TEXT';
    public const PRINT_START = 'PRINT_START';
    public const PRINT_END = 'PRINT_END';
    public const BLOCK_START = 'BLOCK_START';
    public const BLOCK_END = 'BLOCK_END';
    public const NAME = 'NAME';
    public const NUMBER = 'NUMBER';
    public const STRING = 'STRING';
    public const PLUS = 'PLUS';
    public const MINUS = 'MINUS';
    public const MULTIPLY = 'MULTIPLY';
    public const DIVIDE = 'DIVIDE';
    public const ASSIGN = 'ASSIGN';
    public const DOT = 'DOT';
    public const COMMA = 'COMMA';
    public const COLON = 'COLON';
    public const PIPE = 'PIPE';
    public const LPAREN = 'LPAREN';
    public const RPAREN = 'RPAREN';
    public const LBRACKET = 'LBRACKET';
    public const RBRACKET = 'RBRACKET';
    public const LBRACE = 'LBRACE';
    public const RBRACE = 'RBRACE';
}