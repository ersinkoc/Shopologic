<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme;

use Shopologic\Core\Hook\HookSystem;

/**
 * Sandbox environment for template execution
 */
class TemplateSandbox
{
    private TemplateEngine $engine;
    private array $context;
    private array $blocks = [];
    private array $blockStack = [];

    public function __construct(TemplateEngine $engine, array $context = [])
    {
        $this->engine = $engine;
        $this->context = $context;
    }

    /**
     * Execute compiled template
     */
    public function execute(string $compiledCode): void
    {
        // Extract context variables
        extract($this->context, EXTR_SKIP);

        // Execute template
        eval('?>' . $compiledCode);
    }

    /**
     * Start a block
     */
    public function startBlock(string $name): void
    {
        $this->blockStack[] = $name;
        ob_start();
    }

    /**
     * End a block
     */
    public function endBlock(string $name): void
    {
        $content = ob_get_clean();
        
        if (empty($this->blockStack) || array_pop($this->blockStack) !== $name) {
            throw new \RuntimeException(sprintf('Block "%s" was not started', $name));
        }

        $this->blocks[$name] = $content;
    }

    /**
     * Output a block
     */
    public function block(string $name, string $default = ''): string
    {
        return $this->blocks[$name] ?? $default;
    }

    /**
     * Include another template
     */
    public function includeTemplate(string $template, array $variables = []): string
    {
        return $this->engine->includeTemplate($template, $variables);
    }

    /**
     * Extend a parent template
     */
    public function extendTemplate(string $parent): void
    {
        $this->engine->extendTemplate($parent);
    }

    /**
     * Render a component
     */
    public function renderComponent(string $name, array $props = []): string
    {
        $componentManager = $this->engine->getComponentManager();
        
        if (!$componentManager) {
            return '<!-- Component manager not available -->';
        }

        return $componentManager->render($name, $props);
    }

    /**
     * Execute a hook
     */
    public function executeHook(string $name, array $data = []): string
    {
        ob_start();
        HookSystem::doAction($name, $data);
        return ob_get_clean();
    }

    /**
     * Apply a filter
     */
    public function applyFilter(string $name, array $arguments): mixed
    {
        $filter = $this->engine->getFilter($name);
        
        if (!$filter) {
            throw new \RuntimeException(sprintf('Filter "%s" not found', $name));
        }

        return call_user_func_array($filter, $arguments);
    }

    /**
     * Call a template function
     */
    public function callFunction(string $name, array $arguments): mixed
    {
        $function = $this->engine->getFunction($name);
        
        if (!$function) {
            throw new \RuntimeException(sprintf('Function "%s" not found', $name));
        }

        return call_user_func_array($function, $arguments);
    }

    /**
     * Escape output
     */
    public function escape($value): string
    {
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        
        if (is_array($value) || is_object($value)) {
            return htmlspecialchars(json_encode($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        
        return (string) $value;
    }

    /**
     * Get context variable
     */
    public function getContext(string $key = null): mixed
    {
        if ($key === null) {
            return $this->context;
        }

        return $this->context[$key] ?? null;
    }

    /**
     * Set context variable
     */
    public function setContext(string $key, $value): void
    {
        $this->context[$key] = $value;
    }
}