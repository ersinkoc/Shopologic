<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Models;

use Shopologic\Core\Database\Model;

class Template extends Model
{
    protected string $table = 'email_templates';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'name',
        'subject',
        'content',
        'content_text',
        'type',
        'category',
        'tags',
        'variables',
        'blocks',
        'settings',
        'preview_text',
        'from_name',
        'from_email',
        'is_active',
        'usage_count',
        'last_used_at'
    ];

    protected array $casts = [
        'tags' => 'json',
        'variables' => 'json',
        'blocks' => 'json',
        'settings' => 'json',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime'
    ];

    /**
     * Get campaigns using this template
     */
    public function campaigns()
    {
        return $this->hasMany(Campaign::class, 'template_id');
    }

    /**
     * Get automation steps using this template
     */
    public function automationSteps()
    {
        return $this->hasMany(AutomationStep::class, 'template_id');
    }

    /**
     * Scope active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if template is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if template is for campaigns
     */
    public function isForCampaigns(): bool
    {
        return $this->type === 'campaign';
    }

    /**
     * Check if template is for automation
     */
    public function isForAutomation(): bool
    {
        return $this->type === 'automation';
    }

    /**
     * Check if template is transactional
     */
    public function isTransactional(): bool
    {
        return $this->type === 'transactional';
    }

    /**
     * Get template variables
     */
    public function getVariables(): array
    {
        return $this->variables ?? [];
    }

    /**
     * Get template blocks
     */
    public function getBlocks(): array
    {
        return $this->blocks ?? [];
    }

    /**
     * Get required variables
     */
    public function getRequiredVariables(): array
    {
        $variables = $this->getVariables();
        return array_filter($variables, function($var) {
            return $var['required'] ?? false;
        });
    }

    /**
     * Has variable
     */
    public function hasVariable(string $name): bool
    {
        $variables = $this->getVariables();
        return isset($variables[$name]);
    }

    /**
     * Add tag
     */
    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
            $this->save();
        }
    }

    /**
     * Remove tag
     */
    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        $this->tags = array_values($tags);
        $this->save();
    }

    /**
     * Has tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    /**
     * Update variables
     */
    public function updateVariables(array $variables): void
    {
        $this->variables = $variables;
        $this->save();
    }

    /**
     * Update blocks
     */
    public function updateBlocks(array $blocks): void
    {
        $this->blocks = $blocks;
        $this->save();
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->usage_count++;
        $this->last_used_at = now();
        $this->save();
    }

    /**
     * Activate template
     */
    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate template
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Clone template
     */
    public function cloneTemplate(string $newName = null): self
    {
        $clone = $this->replicate();
        $clone->name = $newName ?? $this->name . ' (Copy)';
        $clone->usage_count = 0;
        $clone->last_used_at = null;
        $clone->save();
        
        return $clone;
    }

    /**
     * Render template with data
     */
    public function render(array $data = []): array
    {
        // This would be implemented with actual template rendering logic
        return [
            'subject' => $this->renderSubject($data),
            'html' => $this->renderHtml($data),
            'text' => $this->renderText($data)
        ];
    }

    /**
     * Render subject
     */
    private function renderSubject(array $data): string
    {
        $subject = $this->subject;
        
        // Simple variable replacement
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $subject = str_replace('{{' . $key . '}}', (string)$value, $subject);
            }
        }
        
        return $subject;
    }

    /**
     * Render HTML content
     */
    private function renderHtml(array $data): string
    {
        $content = $this->content;
        
        // Simple variable replacement
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $content = str_replace('{{' . $key . '}}', (string)$value, $content);
            }
        }
        
        return $content;
    }

    /**
     * Render text content
     */
    private function renderText(array $data): string
    {
        $content = $this->content_text;
        
        // Simple variable replacement
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $content = str_replace('{{' . $key . '}}', (string)$value, $content);
            }
        }
        
        return $content;
    }

    /**
     * Validate template data
     */
    public function validateData(array $data): array
    {
        $errors = [];
        $requiredVars = $this->getRequiredVariables();
        
        foreach ($requiredVars as $varName => $varConfig) {
            if (!isset($data[$varName])) {
                $errors[] = "Required variable '{$varName}' is missing";
            }
        }
        
        return $errors;
    }

    /**
     * Get preview data
     */
    public function getPreviewData(): array
    {
        $previewData = [];
        $variables = $this->getVariables();
        
        foreach ($variables as $varName => $varConfig) {
            $previewData[$varName] = $varConfig['preview'] ?? $varConfig['default'] ?? '[' . $varName . ']';
        }
        
        return $previewData;
    }
}