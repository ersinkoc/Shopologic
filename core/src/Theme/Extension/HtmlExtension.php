<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Extension;

/**
 * HTML-related template functions and filters
 */
class HtmlExtension implements ExtensionInterface
{
    public function getFilters(): array
    {
        return [
            'e' => [$this, 'escape'],
            'escape' => [$this, 'escape'],
            'raw' => [$this, 'raw'],
            'safe' => [$this, 'safe'],
            'truncate' => [$this, 'truncate'],
            'wordwrap' => [$this, 'wordwrap'],
            'nl2br' => 'nl2br',
            'strip_tags' => 'strip_tags',
            'markdown' => [$this, 'markdown'],
        ];
    }

    public function getFunctions(): array
    {
        return [
            'link' => [$this, 'link'],
            'image' => [$this, 'image'],
            'form_start' => [$this, 'formStart'],
            'form_end' => [$this, 'formEnd'],
            'input' => [$this, 'input'],
            'textarea' => [$this, 'textarea'],
            'select' => [$this, 'select'],
            'button' => [$this, 'button'],
            'csrf_token' => [$this, 'csrfToken'],
            'meta' => [$this, 'meta'],
            'script' => [$this, 'script'],
            'style' => [$this, 'style'],
        ];
    }

    public function getGlobals(): array
    {
        return [];
    }

    // Filter implementations

    public function escape($value, string $strategy = 'html'): string
    {
        switch ($strategy) {
            case 'html':
                return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            case 'js':
                return json_encode($value);
            case 'css':
                return addslashes((string)$value);
            case 'url':
                return rawurlencode((string)$value);
            case 'html_attr':
                return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            default:
                return (string)$value;
        }
    }

    public function raw($value)
    {
        return $value;
    }

    public function safe($value)
    {
        return $value;
    }

    public function truncate(string $text, int $length = 100, string $suffix = '...', bool $preserveWords = false): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        if ($preserveWords) {
            $truncated = mb_substr($text, 0, $length);
            $lastSpace = mb_strrpos($truncated, ' ');
            
            if ($lastSpace !== false) {
                $truncated = mb_substr($truncated, 0, $lastSpace);
            }
            
            return $truncated . $suffix;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }

    public function wordwrap(string $text, int $width = 75, string $break = "\n", bool $cut = false): string
    {
        return wordwrap($text, $width, $break, $cut);
    }

    public function markdown(string $text): string
    {
        // Simple markdown parser
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        
        // Headers
        $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);
        
        // Bold
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        
        // Italic
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
        
        // Links
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);
        
        // Lists
        $text = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $text);
        $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);
        
        // Paragraphs
        $text = preg_replace('/\n\n/', '</p><p>', $text);
        $text = '<p>' . $text . '</p>';
        
        return $text;
    }

    // Function implementations

    public function link(string $url, string $text = '', array $attributes = []): string
    {
        if (empty($text)) {
            $text = $url;
        }
        
        $attrs = $this->buildAttributes(array_merge(['href' => $url], $attributes));
        return sprintf('<a%s>%s</a>', $attrs, htmlspecialchars($text));
    }

    public function image(string $src, string $alt = '', array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'src' => $src,
            'alt' => $alt
        ], $attributes));
        
        return sprintf('<img%s>', $attrs);
    }

    public function formStart(string $action = '', string $method = 'POST', array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'action' => $action,
            'method' => strtoupper($method),
            'accept-charset' => 'UTF-8'
        ], $attributes));
        
        $form = sprintf('<form%s>', $attrs) . PHP_EOL;
        
        if (strtoupper($method) === 'POST') {
            $form .= $this->csrfToken() . PHP_EOL;
        }
        
        return $form;
    }

    public function formEnd(): string
    {
        return '</form>';
    }

    public function input(string $name, string $type = 'text', $value = '', array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'id' => $attributes['id'] ?? $name
        ], $attributes));
        
        return sprintf('<input%s>', $attrs);
    }

    public function textarea(string $name, $value = '', array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'name' => $name,
            'id' => $attributes['id'] ?? $name
        ], $attributes));
        
        unset($attributes['value']); // Remove value from attributes
        
        return sprintf('<textarea%s>%s</textarea>', $attrs, htmlspecialchars((string)$value));
    }

    public function select(string $name, array $options, $selected = null, array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'name' => $name,
            'id' => $attributes['id'] ?? $name
        ], $attributes));
        
        $html = sprintf('<select%s>' . PHP_EOL, $attrs);
        
        foreach ($options as $value => $label) {
            $isSelected = ($selected !== null && $value == $selected) ? ' selected' : '';
            $html .= sprintf(
                '  <option value="%s"%s>%s</option>' . PHP_EOL,
                htmlspecialchars((string)$value),
                $isSelected,
                htmlspecialchars($label)
            );
        }
        
        $html .= '</select>';
        
        return $html;
    }

    public function button(string $text, string $type = 'button', array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'type' => $type
        ], $attributes));
        
        return sprintf('<button%s>%s</button>', $attrs, htmlspecialchars($text));
    }

    public function csrfToken(): string
    {
        // Get CSRF token from session or security component
        $token = $_SESSION['_csrf_token'] ?? $this->generateCsrfToken();
        
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars($token)
        );
    }

    public function meta(string $name, string $content, array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'name' => $name,
            'content' => $content
        ], $attributes));
        
        return sprintf('<meta%s>', $attrs);
    }

    public function script(string $src = '', string $content = '', array $attributes = []): string
    {
        if (!empty($src)) {
            $attrs = $this->buildAttributes(array_merge(['src' => $src], $attributes));
            return sprintf('<script%s></script>', $attrs);
        }
        
        $attrs = $this->buildAttributes($attributes);
        return sprintf('<script%s>%s</script>', $attrs, $content);
    }

    public function style(string $content, array $attributes = []): string
    {
        $attrs = $this->buildAttributes($attributes);
        return sprintf('<style%s>%s</style>', $attrs, $content);
    }

    // Helper methods

    private function buildAttributes(array $attributes): string
    {
        $attrs = '';
        
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            
            if ($value === true) {
                $attrs .= ' ' . $key;
            } else {
                $attrs .= sprintf(' %s="%s"', $key, htmlspecialchars((string)$value));
            }
        }
        
        return $attrs;
    }

    private function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
        return $token;
    }
}