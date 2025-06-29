<?php

declare(strict_types=1);

namespace Shopologic\Core\Theme\Extension;

/**
 * Core template extension with basic filters and functions
 */
class CoreExtension implements ExtensionInterface
{
    public function getFilters(): array
    {
        return [
            'abs' => 'abs',
            'capitalize' => [$this, 'capitalize'],
            'date' => [$this, 'date'],
            'default' => [$this, 'defaultFilter'],
            'escape' => [$this, 'escape'],
            'first' => [$this, 'first'],
            'format' => 'sprintf',
            'join' => [$this, 'join'],
            'json_encode' => 'json_encode',
            'keys' => 'array_keys',
            'last' => [$this, 'last'],
            'length' => [$this, 'length'],
            'lower' => 'strtolower',
            'nl2br' => 'nl2br',
            'number_format' => 'number_format',
            'raw' => [$this, 'raw'],
            'replace' => [$this, 'replace'],
            'reverse' => [$this, 'reverse'],
            'round' => 'round',
            'slice' => [$this, 'slice'],
            'sort' => [$this, 'sort'],
            'split' => [$this, 'split'],
            'striptags' => 'strip_tags',
            'title' => [$this, 'title'],
            'trim' => 'trim',
            'upper' => 'strtoupper',
            'url_encode' => 'urlencode',
        ];
    }

    public function getFunctions(): array
    {
        return [
            'attribute' => [$this, 'attribute'],
            'constant' => 'constant',
            'cycle' => [$this, 'cycle'],
            'date' => [$this, 'dateFunction'],
            'dump' => [$this, 'dump'],
            'include' => [$this, 'includeFunction'],
            'max' => 'max',
            'min' => 'min',
            'random' => [$this, 'random'],
            'range' => 'range',
            'source' => [$this, 'source'],
        ];
    }

    public function getGlobals(): array
    {
        return [
            'now' => new \DateTime(),
        ];
    }

    // Filter implementations

    public function capitalize($string): string
    {
        return ucwords(strtolower($string));
    }

    public function date($date, string $format = 'Y-m-d H:i:s', ?string $timezone = null): string
    {
        if (!$date instanceof \DateTimeInterface) {
            if (is_numeric($date)) {
                $date = new \DateTime('@' . $date);
            } else {
                $date = new \DateTime($date);
            }
        }

        if ($timezone !== null) {
            $date = clone $date;
            $date->setTimezone(new \DateTimeZone($timezone));
        }

        return $date->format($format);
    }

    public function defaultFilter($value, $default = '')
    {
        return $value ?: $default;
    }

    public function escape($value, string $strategy = 'html', ?string $charset = null): string
    {
        $charset = $charset ?: 'UTF-8';

        switch ($strategy) {
            case 'html':
                return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
            
            case 'js':
                return json_encode($value);
            
            case 'css':
                return addslashes($value);
            
            case 'url':
                return rawurlencode($value);
            
            case 'html_attr':
                return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
            
            default:
                return $value;
        }
    }

    public function first($array, $default = null)
    {
        if (is_string($array)) {
            return mb_substr($array, 0, 1);
        }

        if (is_array($array) || $array instanceof \Traversable) {
            foreach ($array as $item) {
                return $item;
            }
        }

        return $default;
    }

    public function join($array, string $glue = '', ?string $lastGlue = null): string
    {
        if (!is_array($array) && !$array instanceof \Traversable) {
            return '';
        }

        $array = iterator_to_array($array, false);
        
        if ($lastGlue !== null && count($array) > 1) {
            $last = array_pop($array);
            return implode($glue, $array) . $lastGlue . $last;
        }

        return implode($glue, $array);
    }

    public function last($array, $default = null)
    {
        if (is_string($array)) {
            return mb_substr($array, -1);
        }

        if (is_array($array)) {
            return count($array) ? end($array) : $default;
        }

        return $default;
    }

    public function length($value): int
    {
        if (is_string($value)) {
            return mb_strlen($value);
        }

        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }

        if ($value instanceof \Traversable) {
            return iterator_count($value);
        }

        return 0;
    }

    public function raw($value)
    {
        return $value;
    }

    public function replace($string, array $replacements): string
    {
        return strtr($string, $replacements);
    }

    public function reverse($value)
    {
        if (is_string($value)) {
            return strrev($value);
        }

        if (is_array($value)) {
            return array_reverse($value);
        }

        return $value;
    }

    public function slice($value, int $start, ?int $length = null, bool $preserveKeys = false)
    {
        if (is_string($value)) {
            return mb_substr($value, $start, $length);
        }

        if (is_array($value)) {
            return array_slice($value, $start, $length, $preserveKeys);
        }

        return $value;
    }

    public function sort($array, ?string $arrow = null)
    {
        if (!is_array($array)) {
            return $array;
        }

        if ($arrow === null) {
            asort($array);
        } else {
            uasort($array, function ($a, $b) use ($arrow) {
                $aValue = $this->getProperty($a, $arrow);
                $bValue = $this->getProperty($b, $arrow);
                return $aValue <=> $bValue;
            });
        }

        return $array;
    }

    public function split(string $string, string $delimiter = '', ?int $limit = null): array
    {
        if ($delimiter === '') {
            return str_split($string);
        }

        return $limit === null 
            ? explode($delimiter, $string)
            : explode($delimiter, $string, $limit);
    }

    public function title(string $string): string
    {
        return mb_convert_case($string, MB_CASE_TITLE);
    }

    // Function implementations

    public function attribute($object, string $attribute, array $arguments = [])
    {
        if (is_array($object)) {
            return $object[$attribute] ?? null;
        }

        if (is_object($object)) {
            if (property_exists($object, $attribute)) {
                return $object->$attribute;
            }

            $getter = 'get' . ucfirst($attribute);
            if (method_exists($object, $getter)) {
                return call_user_func_array([$object, $getter], $arguments);
            }

            if (method_exists($object, $attribute)) {
                return call_user_func_array([$object, $attribute], $arguments);
            }
        }

        return null;
    }

    public function cycle(array $values, int $position): mixed
    {
        return $values[$position % count($values)];
    }

    public function dateFunction(?string $date = null, ?string $timezone = null): \DateTime
    {
        $dateTime = $date ? new \DateTime($date) : new \DateTime();
        
        if ($timezone) {
            $dateTime->setTimezone(new \DateTimeZone($timezone));
        }

        return $dateTime;
    }

    public function dump(...$vars): string
    {
        ob_start();
        var_dump(...$vars);
        return ob_get_clean();
    }

    public function includeFunction(string $template, array $variables = []): string
    {
        // This is handled by the template engine
        return '';
    }

    public function random($values = null)
    {
        if ($values === null) {
            return mt_rand();
        }

        if (is_int($values)) {
            return mt_rand(0, $values);
        }

        if (is_string($values)) {
            return $values[mt_rand(0, mb_strlen($values) - 1)];
        }

        if (is_array($values)) {
            return $values[array_rand($values)];
        }

        return null;
    }

    public function source(string $template): string
    {
        // This would be implemented by the template engine
        return '';
    }

    // Helper methods

    private function getProperty($object, string $property)
    {
        if (is_array($object)) {
            return $object[$property] ?? null;
        }

        if (is_object($object)) {
            return $object->$property ?? null;
        }

        return null;
    }
}