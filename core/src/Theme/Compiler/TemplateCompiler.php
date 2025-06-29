<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Compiler;

use Shopologic\Core\Theme\Parser\TemplateParser;
use Shopologic\Core\Theme\Parser\Node\NodeInterface;

/**
 * Compiles template syntax to PHP code
 */
class TemplateCompiler
{
    private TemplateParser $parser;
    private array $variables = [];
    private array $blocks = [];
    private int $variableCounter = 0;
    private string $currentTemplate = '';

    public function __construct(TemplateParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Compile template source to PHP
     */
    public function compile(string $source, string $template): string
    {
        $this->currentTemplate = $template;
        $this->variables = [];
        $this->blocks = [];
        $this->variableCounter = 0;

        // Parse template into AST
        $ast = $this->parser->parse($source);

        // Compile AST to PHP
        $code = $this->compileNode($ast);

        // Wrap in PHP tags
        return "<?php\n" . $this->generatePreamble() . "\n" . $code . "\n?>";
    }

    /**
     * Compile a node
     */
    private function compileNode(NodeInterface $node): string
    {
        $method = 'compile' . (new \ReflectionClass($node))->getShortName();
        
        if (!method_exists($this, $method)) {
            throw new CompilerException(sprintf('Cannot compile node of type "%s"', get_class($node)));
        }

        return $this->$method($node);
    }

    /**
     * Compile template node (root)
     */
    private function compileTemplateNode($node): string
    {
        $code = '';
        
        foreach ($node->getChildren() as $child) {
            $code .= $this->compileNode($child);
        }
        
        return $code;
    }

    /**
     * Compile text node
     */
    private function compileTextNode($node): string
    {
        $text = $node->getText();
        
        // Escape PHP tags
        $text = str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $text);
        
        return 'echo ' . var_export($text, true) . ";\n";
    }

    /**
     * Compile print node {{ expression }}
     */
    private function compilePrintNode($node): string
    {
        $expression = $this->compileExpression($node->getExpression());
        $filters = $node->getFilters();
        
        // Apply filters
        foreach ($filters as $filter) {
            $expression = $this->compileFilter($expression, $filter);
        }
        
        // Auto-escape by default
        if (!in_array('raw', $filters)) {
            $expression = '$this->escape(' . $expression . ')';
        }
        
        return 'echo ' . $expression . ";\n";
    }

    /**
     * Compile variable node
     */
    private function compileVariableNode($node): string
    {
        $name = $node->getName();
        $path = $node->getPath();
        
        $code = '$context[\'' . $name . '\']';
        
        // Handle nested access (e.g., user.name)
        foreach ($path as $key) {
            if (is_string($key)) {
                $code .= '[\'' . $key . '\']';
            } else {
                $code .= '[' . $this->compileExpression($key) . ']';
            }
        }
        
        return '(' . $code . ' ?? null)';
    }

    /**
     * Compile if node {% if condition %}
     */
    private function compileIfNode($node): string
    {
        $code = 'if (' . $this->compileExpression($node->getCondition()) . ") {\n";
        
        foreach ($node->getIfBody() as $child) {
            $code .= $this->compileNode($child);
        }
        
        // Handle elseif
        foreach ($node->getElseIfClauses() as $elseif) {
            $code .= '} elseif (' . $this->compileExpression($elseif['condition']) . ") {\n";
            foreach ($elseif['body'] as $child) {
                $code .= $this->compileNode($child);
            }
        }
        
        // Handle else
        if ($node->hasElse()) {
            $code .= "} else {\n";
            foreach ($node->getElseBody() as $child) {
                $code .= $this->compileNode($child);
            }
        }
        
        $code .= "}\n";
        
        return $code;
    }

    /**
     * Compile for node {% for item in items %}
     */
    private function compileForNode($node): string
    {
        $itemVar = $node->getItemVariable();
        $keyVar = $node->getKeyVariable();
        $collection = $this->compileExpression($node->getCollection());
        
        // Generate unique loop variable
        $loopVar = '$_loop_' . $this->variableCounter++;
        
        $code = $loopVar . ' = ' . $collection . ";\n";
        $code .= 'if (is_array(' . $loopVar . ') || ' . $loopVar . ' instanceof \Traversable) {' . "\n";
        
        // Initialize loop variable
        $code .= '$context[\'loop\'] = [\'index0\' => 0, \'index\' => 1, \'first\' => true, \'last\' => false, \'length\' => count(' . $loopVar . ')];' . "\n";
        
        // Start foreach
        if ($keyVar) {
            $code .= 'foreach (' . $loopVar . ' as $context[\'' . $keyVar . '\'] => $context[\'' . $itemVar . '\']) {' . "\n";
        } else {
            $code .= 'foreach (' . $loopVar . ' as $context[\'' . $itemVar . '\']) {' . "\n";
        }
        
        // Update loop info
        $code .= '$context[\'loop\'][\'last\'] = $context[\'loop\'][\'index\'] === $context[\'loop\'][\'length\'];' . "\n";
        
        // Compile loop body
        foreach ($node->getBody() as $child) {
            $code .= $this->compileNode($child);
        }
        
        // Update loop counters
        $code .= '$context[\'loop\'][\'index0\']++;' . "\n";
        $code .= '$context[\'loop\'][\'index\']++;' . "\n";
        $code .= '$context[\'loop\'][\'first\'] = false;' . "\n";
        
        $code .= "}\n";
        
        // Handle else clause (for empty collections)
        if ($node->hasElse()) {
            $code .= '} else {' . "\n";
            foreach ($node->getElseBody() as $child) {
                $code .= $this->compileNode($child);
            }
        }
        
        $code .= "}\n";
        
        return $code;
    }

    /**
     * Compile block node {% block name %}
     */
    private function compileBlockNode($node): string
    {
        $name = $node->getName();
        $this->blocks[$name] = true;
        
        $code = '$this->startBlock(\'' . $name . '\');' . "\n";
        
        foreach ($node->getBody() as $child) {
            $code .= $this->compileNode($child);
        }
        
        $code .= '$this->endBlock(\'' . $name . '\');' . "\n";
        $code .= 'echo $this->block(\'' . $name . '\');' . "\n";
        
        return $code;
    }

    /**
     * Compile extends node {% extends "parent.twig" %}
     */
    private function compileExtendsNode($node): string
    {
        $parent = $node->getParent();
        
        return '$this->extendTemplate(' . var_export($parent, true) . ');' . "\n";
    }

    /**
     * Compile include node {% include "partial.twig" %}
     */
    private function compileIncludeNode($node): string
    {
        $template = $node->getTemplate();
        $variables = $node->getVariables();
        
        if ($variables) {
            $vars = $this->compileExpression($variables);
            return 'echo $this->includeTemplate(' . var_export($template, true) . ', ' . $vars . ');' . "\n";
        }
        
        return 'echo $this->includeTemplate(' . var_export($template, true) . ');' . "\n";
    }

    /**
     * Compile set node {% set variable = value %}
     */
    private function compileSetNode($node): string
    {
        $variable = $node->getVariable();
        $value = $this->compileExpression($node->getValue());
        
        return '$context[\'' . $variable . '\'] = ' . $value . ';' . "\n";
    }

    /**
     * Compile component node {% component "name" with {props} %}
     */
    private function compileComponentNode($node): string
    {
        $name = $node->getName();
        $props = $node->getProps();
        
        $code = 'echo $this->renderComponent(' . var_export($name, true);
        
        if ($props) {
            $code .= ', ' . $this->compileExpression($props);
        } else {
            $code .= ', []';
        }
        
        $code .= ');' . "\n";
        
        return $code;
    }

    /**
     * Compile hook node {% hook "name" with {data} %}
     */
    private function compileHookNode($node): string
    {
        $name = $node->getName();
        $data = $node->getData();
        
        $code = 'echo $this->executeHook(' . var_export($name, true);
        
        if ($data) {
            $code .= ', ' . $this->compileExpression($data);
        } else {
            $code .= ', []';
        }
        
        $code .= ');' . "\n";
        
        return $code;
    }

    /**
     * Compile expression
     */
    private function compileExpression($expression): string
    {
        if (is_string($expression)) {
            return var_export($expression, true);
        }
        
        if (is_numeric($expression)) {
            return (string) $expression;
        }
        
        if (is_bool($expression)) {
            return $expression ? 'true' : 'false';
        }
        
        if (is_array($expression)) {
            return $this->compileArray($expression);
        }
        
        if ($expression instanceof NodeInterface) {
            return $this->compileNode($expression);
        }
        
        return 'null';
    }

    /**
     * Compile array expression
     */
    private function compileArray(array $array): string
    {
        $items = [];
        
        foreach ($array as $key => $value) {
            if (is_int($key)) {
                $items[] = $this->compileExpression($value);
            } else {
                $items[] = var_export($key, true) . ' => ' . $this->compileExpression($value);
            }
        }
        
        return '[' . implode(', ', $items) . ']';
    }

    /**
     * Compile filter
     */
    private function compileFilter(string $expression, array $filter): string
    {
        $name = $filter['name'];
        $args = $filter['arguments'] ?? [];
        
        // Build arguments
        $arguments = [$expression];
        foreach ($args as $arg) {
            $arguments[] = $this->compileExpression($arg);
        }
        
        return '$this->applyFilter(\'' . $name . '\', [' . implode(', ', $arguments) . '])';
    }

    /**
     * Generate preamble code
     */
    private function generatePreamble(): string
    {
        return <<<'PHP'
// Template context
$context = $context ?? [];

// Helper functions
if (!function_exists('_template_escape')) {
    function _template_escape($value) {
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return $value;
    }
}

PHP;
    }
}

class CompilerException extends \Exception {}