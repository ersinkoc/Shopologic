<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedEmailMarketing\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedEmailMarketing\Services\{
    TemplateManager,;
    PersonalizationEngine,;
    EmailSender;
};
use AdvancedEmailMarketing\Repositories\TemplateRepository;

class TemplateController extends Controller
{
    private TemplateManager $templateManager;
    private PersonalizationEngine $personalizationEngine;
    private EmailSender $emailSender;
    private TemplateRepository $templateRepository;

    public function __construct()
    {
        $this->templateManager = app(TemplateManager::class);
        $this->personalizationEngine = app(PersonalizationEngine::class);
        $this->emailSender = app(EmailSender::class);
        $this->templateRepository = app(TemplateRepository::class);
    }

    /**
     * List templates
     */
    public function index(Request $request): Response
    {
        $filters = [
            'type' => $request->query('type'),
            'category' => $request->query('category'),
            'search' => $request->query('search')
        ];
        
        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 20);
        
        $templates = $this->templateRepository->getWithPagination($filters, $page, $perPage);
        
        // Add usage statistics
        foreach ($templates['data'] as &$template) {
            $template['usage_count'] = $this->templateRepository->getUsageCount($template['id']);
            $template['last_used'] = $this->templateRepository->getLastUsed($template['id']);
        }
        
        return $this->json([
            'status' => 'success',
            'data' => $templates['data'],
            'pagination' => $templates['pagination']
        ]);
    }

    /**
     * Get template details
     */
    public function show(Request $request, int $id): Response
    {
        $template = $this->templateRepository->findById($id);
        
        if (!$template) {
            return $this->json([
                'status' => 'error',
                'message' => 'Template not found'
            ], 404);
        }
        
        // Parse template variables
        $template['variables'] = $this->templateManager->extractVariables($template['content']);
        $template['blocks'] = $this->templateManager->extractBlocks($template['content']);
        $template['usage_count'] = $this->templateRepository->getUsageCount($id);
        $template['campaigns'] = $this->templateRepository->getRecentCampaigns($id, 5);
        
        return $this->json([
            'status' => 'success',
            'data' => $template
        ]);
    }

    /**
     * Create new template
     */
    public function create(Request $request): Response
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:campaign,automation,transactional',
            'category' => 'string',
            'tags' => 'array',
            'settings' => 'array'
        ]);
        
        try {
            $data = $request->all();
            
            // Validate template syntax
            $validation = $this->templateManager->validateTemplate($data['content']);
            if (!$validation['valid']) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Template validation failed',
                    'errors' => $validation['errors']
                ], 400);
            }
            
            // Process and optimize template
            $data['content'] = $this->templateManager->processTemplate($data['content']);
            $data['content_text'] = $this->templateManager->generateTextVersion($data['content']);
            
            $template = $this->templateManager->createTemplate($data);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Template created successfully',
                'data' => $template
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update template
     */
    public function update(Request $request, int $id): Response
    {
        $this->validate($request, [
            'name' => 'string|max:255',
            'subject' => 'string|max:255',
            'content' => 'string',
            'category' => 'string',
            'tags' => 'array',
            'settings' => 'array'
        ]);
        
        try {
            $template = $this->templateRepository->findById($id);
            
            if (!$template) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Template not found'
                ], 404);
            }
            
            $data = $request->all();
            
            // Validate template syntax if content is being updated
            if (isset($data['content'])) {
                $validation = $this->templateManager->validateTemplate($data['content']);
                if (!$validation['valid']) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Template validation failed',
                        'errors' => $validation['errors']
                    ], 400);
                }
                
                // Process and optimize template
                $data['content'] = $this->templateManager->processTemplate($data['content']);
                $data['content_text'] = $this->templateManager->generateTextVersion($data['content']);
            }
            
            $updated = $this->templateManager->updateTemplate($id, $data);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Template updated successfully',
                'data' => $updated
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete template
     */
    public function delete(Request $request, int $id): Response
    {
        try {
            $template = $this->templateRepository->findById($id);
            
            if (!$template) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Template not found'
                ], 404);
            }
            
            // Check if template is in use
            $usage = $this->templateRepository->getActiveUsage($id);
            if (!empty($usage)) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Cannot delete template that is in use',
                    'usage' => $usage
                ], 400);
            }
            
            $this->templateManager->deleteTemplate($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Template deleted successfully'
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Send test email
     */
    public function sendTest(Request $request, int $id): Response
    {
        $this->validate($request, [
            'test_email' => 'required|email',
            'test_data' => 'array'
        ]);
        
        try {
            $template = $this->templateRepository->findById($id);
            
            if (!$template) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Template not found'
                ], 404);
            }
            
            $testEmail = $request->input('test_email');
            $testData = $request->input('test_data', []);
            
            // Add sample data for testing
            $sampleData = $this->templateManager->generateSampleData($template['type']);
            $mergedData = array_merge($sampleData, $testData);
            
            // Send test email
            $result = $this->emailSender->sendTestEmail($testEmail, $template, $mergedData);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Test email sent successfully',
                'data' => [
                    'sent_to' => $testEmail,
                    'template_id' => $id,
                    'result' => $result
                ]
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Duplicate template
     */
    public function duplicate(Request $request, int $id): Response
    {
        try {
            $template = $this->templateRepository->findById($id);
            
            if (!$template) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Template not found'
                ], 404);
            }
            
            $duplicated = $this->templateManager->duplicateTemplate($id);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Template duplicated successfully',
                'data' => $duplicated
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Preview template
     */
    public function preview(Request $request, int $id): Response
    {
        $template = $this->templateRepository->findById($id);
        
        if (!$template) {
            return $this->json([
                'status' => 'error',
                'message' => 'Template not found'
            ], 404);
        }
        
        $sampleData = $request->input('sample_data', []);
        if (empty($sampleData)) {
            $sampleData = $this->templateManager->generateSampleData($template['type']);
        }
        
        // Render template with sample data
        $rendered = $this->templateManager->renderTemplate($template, $sampleData);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'html' => $rendered['html'],
                'text' => $rendered['text'],
                'subject' => $rendered['subject'],
                'sample_data' => $sampleData
            ]
        ]);
    }

    /**
     * Get template categories
     */
    public function categories(Request $request): Response
    {
        $categories = $this->templateRepository->getCategories();
        
        return $this->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    /**
     * Get template variables
     */
    public function variables(Request $request, int $id): Response
    {
        $template = $this->templateRepository->findById($id);
        
        if (!$template) {
            return $this->json([
                'status' => 'error',
                'message' => 'Template not found'
            ], 404);
        }
        
        $variables = $this->templateManager->extractVariables($template['content']);
        $availableVariables = $this->templateManager->getAvailableVariables($template['type']);
        
        return $this->json([
            'status' => 'success',
            'data' => [
                'used_variables' => $variables,
                'available_variables' => $availableVariables
            ]
        ]);
    }

    /**
     * Validate template
     */
    public function validate(Request $request): Response
    {
        $this->validate($request, [
            'content' => 'required|string',
            'type' => 'in:campaign,automation,transactional'
        ]);
        
        $content = $request->input('content');
        $type = $request->input('type', 'campaign');
        
        $validation = $this->templateManager->validateTemplate($content);
        
        if ($validation['valid']) {
            $variables = $this->templateManager->extractVariables($content);
            $blocks = $this->templateManager->extractBlocks($content);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Template is valid',
                'data' => [
                    'variables' => $variables,
                    'blocks' => $blocks,
                    'warnings' => $validation['warnings'] ?? []
                ]
            ]);
        }
        
        return $this->json([
            'status' => 'error',
            'message' => 'Template validation failed',
            'errors' => $validation['errors']
        ], 400);
    }

    /**
     * Import template
     */
    public function import(Request $request): Response
    {
        $this->validate($request, [
            'template_data' => 'required|array',
            'format' => 'in:json,html'
        ]);
        
        try {
            $templateData = $request->input('template_data');
            $format = $request->input('format', 'json');
            
            $imported = $this->templateManager->importTemplate($templateData, $format);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Template imported successfully',
                'data' => $imported
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Export template
     */
    public function export(Request $request, int $id): Response
    {
        try {
            $template = $this->templateRepository->findById($id);
            
            if (!$template) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Template not found'
                ], 404);
            }
            
            $format = $request->query('format', 'json');
            $export = $this->templateManager->exportTemplate($id, $format);
            
            if ($format === 'html') {
                return $this->html($export, "template_{$id}.html");
            }
            
            return $this->json([
                'status' => 'success',
                'data' => $export
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get template gallery
     */
    public function gallery(Request $request): Response
    {
        $category = $request->query('category');
        $templates = $this->templateManager->getGalleryTemplates($category);
        
        return $this->json([
            'status' => 'success',
            'data' => $templates
        ]);
    }

    /**
     * Install gallery template
     */
    public function installFromGallery(Request $request): Response
    {
        $this->validate($request, [
            'gallery_id' => 'required|string',
            'name' => 'required|string|max:255'
        ]);
        
        try {
            $galleryId = $request->input('gallery_id');
            $name = $request->input('name');
            
            $template = $this->templateManager->installGalleryTemplate($galleryId, $name);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Template installed successfully',
                'data' => $template
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}