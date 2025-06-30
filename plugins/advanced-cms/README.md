# 📝 Advanced CMS Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


Enterprise-grade content management system with advanced editing capabilities, workflow management, and seamless e-commerce integration.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Advanced CMS
php cli/plugin.php activate advanced-cms
```

## ✨ Key Features

### 📄 Advanced Content Management
- **Rich Text Editor** - WYSIWYG editor with custom blocks and components
- **Content Versioning** - Complete revision history with rollback capabilities
- **Multi-language Support** - Localized content management for global reach
- **Media Library** - Advanced asset management with CDN integration
- **Content Scheduling** - Publish content at optimal times

### 🔄 Workflow Management
- **Editorial Workflow** - Multi-stage approval process with role-based permissions
- **Content Review** - Collaborative editing with comments and suggestions
- **Publishing Pipeline** - Automated content deployment and distribution
- **Content Approval** - Hierarchical approval chains with notifications
- **Audit Trail** - Complete content change tracking and compliance

### 🎨 Design System Integration
- **Component Library** - Reusable content blocks and templates
- **Theme Integration** - Seamless integration with Shopologic themes
- **Responsive Editing** - Mobile-first content creation and preview
- **SEO Optimization** - Built-in SEO tools and meta tag management
- **Performance Optimization** - Automatic content optimization and caching

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`AdvancedCmsPlugin.php`** - Core CMS functionality and lifecycle management

### Services
- **`ContentServiceInterface.php`** - Content management service contract
- **Content Manager** - Core content CRUD operations and lifecycle
- **Workflow Engine** - Editorial workflow and approval management
- **Media Manager** - Asset storage, optimization, and delivery
- **SEO Manager** - Search engine optimization and meta management
- **Template Engine** - Dynamic content rendering and template management

### Models
- **Content** - Core content entity with versioning
- **ContentVersion** - Content revision tracking
- **ContentType** - Flexible content type definitions
- **MediaAsset** - Media file management
- **ContentWorkflow** - Editorial workflow states
- **ContentCategory** - Content organization and taxonomy

### Controllers
- **Content API** - RESTful content management endpoints
- **Media API** - Asset upload and management endpoints
- **Workflow API** - Editorial workflow management
- **Admin Interface** - Content management dashboard

## 📝 Content Management

### Creating Content Types

```php
// Define custom content types
$contentManager = app(ContentManager::class);

$contentType = $contentManager->createContentType([
    'name' => 'Landing Page',
    'slug' => 'landing_page',
    'description' => 'Marketing landing pages with conversion tracking',
    'fields' => [
        [
            'name' => 'hero_title',
            'type' => 'text',
            'label' => 'Hero Title',
            'required' => true,
            'max_length' => 100
        ],
        [
            'name' => 'hero_subtitle',
            'type' => 'textarea',
            'label' => 'Hero Subtitle',
            'required' => false,
            'max_length' => 250
        ],
        [
            'name' => 'hero_image',
            'type' => 'media',
            'label' => 'Hero Image',
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp']
        ],
        [
            'name' => 'content_blocks',
            'type' => 'blocks',
            'label' => 'Content Blocks',
            'blocks' => ['text', 'image', 'video', 'product_grid', 'cta_button']
        ],
        [
            'name' => 'seo_settings',
            'type' => 'group',
            'label' => 'SEO Settings',
            'fields' => [
                ['name' => 'meta_title', 'type' => 'text'],
                ['name' => 'meta_description', 'type' => 'textarea'],
                ['name' => 'canonical_url', 'type' => 'url']
            ]
        ]
    ],
    'workflow' => 'marketing_approval',
    'template' => 'landing-page.twig'
]);
```

### Advanced Content Creation

```php
// Create content with versioning
$content = $contentManager->createContent([
    'content_type' => 'landing_page',
    'title' => 'Black Friday 2024 Sale',
    'slug' => 'black-friday-2024',
    'status' => 'draft',
    'fields' => [
        'hero_title' => 'Save Up to 70% This Black Friday',
        'hero_subtitle' => 'Limited time deals on your favorite products',
        'hero_image' => [
            'asset_id' => 'media_12345',
            'alt_text' => 'Black Friday Sale Banner',
            'focal_point' => ['x' => 0.5, 'y' => 0.3]
        ],
        'content_blocks' => [
            [
                'type' => 'product_grid',
                'settings' => [
                    'category_id' => 5,
                    'limit' => 8,
                    'layout' => 'grid_4x2'
                ]
            ],
            [
                'type' => 'cta_button',
                'settings' => [
                    'text' => 'Shop Now',
                    'url' => '/sale/black-friday',
                    'style' => 'primary_large'
                ]
            ]
        ],
        'seo_settings' => [
            'meta_title' => 'Black Friday Sale 2024 - Up to 70% Off',
            'meta_description' => 'Don\'t miss our biggest sale of the year...',
            'canonical_url' => 'https://example.com/black-friday-2024'
        ]
    ],
    'scheduled_publish' => '2024-11-24 00:00:00',
    'author_id' => auth()->id()
]);

// Create content version
$version = $contentManager->createVersion($content->id, [
    'changes' => ['Updated hero title for better conversion'],
    'version_type' => 'major'
]);
```

## 🔄 Workflow Management

### Editorial Workflows

```php
// Define editorial workflow
$workflowEngine = app(WorkflowEngine::class);

$workflow = $workflowEngine->createWorkflow([
    'name' => 'Marketing Approval',
    'slug' => 'marketing_approval',
    'description' => 'Standard approval process for marketing content',
    'steps' => [
        [
            'name' => 'Draft',
            'slug' => 'draft',
            'permissions' => ['author', 'editor'],
            'actions' => ['edit', 'submit_for_review']
        ],
        [
            'name' => 'Content Review',
            'slug' => 'content_review',
            'permissions' => ['editor'],
            'actions' => ['approve', 'request_changes', 'reject'],
            'notifications' => ['email', 'dashboard']
        ],
        [
            'name' => 'Marketing Review',
            'slug' => 'marketing_review',
            'permissions' => ['marketing_manager'],
            'actions' => ['approve', 'request_changes', 'reject'],
            'auto_approve_after' => '48 hours'
        ],
        [
            'name' => 'Published',
            'slug' => 'published',
            'permissions' => ['editor', 'marketing_manager'],
            'actions' => ['unpublish', 'archive']
        ]
    ],
    'rules' => [
        'auto_submit_scheduled' => true,
        'require_approval_for_changes' => true,
        'notification_settings' => [
            'email_notifications' => true,
            'slack_integration' => true
        ]
    ]
]);

// Process workflow transitions
$workflowEngine->transitionContent($content->id, 'submit_for_review', [
    'comment' => 'Ready for content review',
    'reviewer_id' => $editorId
]);
```

### Collaborative Editing

```php
// Add content comments and suggestions
$collaborationManager = app(CollaborationManager::class);

$comment = $collaborationManager->addComment($content->id, [
    'field' => 'hero_title',
    'position' => 15,
    'comment' => 'Consider using action words like "Discover" or "Explore"',
    'type' => 'suggestion',
    'author_id' => auth()->id()
]);

// Track content changes
$changeTracker = app(ChangeTracker::class);
$changes = $changeTracker->getContentChanges($content->id, [
    'since' => '2024-01-01',
    'include_author' => true,
    'group_by' => 'field'
]);
```

## 🎨 Template System Integration

### Dynamic Content Rendering

```php
// Render content with template system
$templateEngine = app(TemplateEngine::class);

// Register custom content blocks
$templateEngine->registerBlock('product_grid', function($settings) {
    $productRepository = app(ProductRepositoryInterface::class);
    $products = $productRepository->findByCategory($settings['category_id'], [
        'limit' => $settings['limit'],
        'status' => 'active'
    ]);
    
    return $this->render('blocks/product-grid.twig', [
        'products' => $products,
        'layout' => $settings['layout']
    ]);
});

// Render content page
$renderedContent = $templateEngine->renderContent($content, [
    'context' => 'frontend',
    'cache_ttl' => 3600
]);
```

### SEO Integration

```php
// Advanced SEO management
$seoManager = app(SeoManager::class);

// Generate SEO metadata
$seoData = $seoManager->generateSeoData($content, [
    'include_schema' => true,
    'generate_social_tags' => true,
    'optimize_images' => true
]);

// Schema.org markup generation
$schema = $seoManager->generateSchema($content, [
    'type' => 'WebPage',
    'include_breadcrumbs' => true,
    'include_organization' => true
]);
```

## 🔗 Cross-Plugin Integration

### E-commerce Integration

```php
// Integrate with product catalog
$productRepository = app(ProductRepositoryInterface::class);

// Create product showcase content
$productShowcase = $contentManager->createContent([
    'content_type' => 'product_showcase',
    'title' => 'Featured Products',
    'fields' => [
        'featured_products' => $productRepository->getFeaturedProducts(8),
        'promotion_banner' => 'media_67890',
        'description' => 'Our hand-picked selection of premium products'
    ]
]);

// Integration with analytics
$analyticsProvider = app()->get(AnalyticsProviderInterface::class);
$analyticsProvider->trackEvent('content_viewed', [
    'content_id' => $content->id,
    'content_type' => $content->content_type,
    'content_title' => $content->title
]);
```

### Multi-language Content

```php
// Create localized content
$localizationManager = app(LocalizationManager::class);

$localizedContent = $contentManager->createLocalizedContent($content->id, [
    'locale' => 'es',
    'fields' => [
        'hero_title' => 'Ahorra Hasta 70% Este Black Friday',
        'hero_subtitle' => 'Ofertas por tiempo limitado en tus productos favoritos',
        'seo_settings' => [
            'meta_title' => 'Venta Black Friday 2024 - Hasta 70% de Descuento',
            'meta_description' => 'No te pierdas nuestra mayor venta del año...'
        ]
    ]
]);
```

## ⚡ Real-Time Events

### Content Events

```php
// Process content events
$eventDispatcher = PluginEventDispatcher::getInstance();

$eventDispatcher->listen('content.published', function($event) {
    $contentData = $event->getData();
    
    // Clear cache
    $cacheManager = app(CacheManager::class);
    $cacheManager->clearContentCache($contentData['content_id']);
    
    // Update search index
    $searchManager = app(SearchManager::class);
    $searchManager->indexContent($contentData['content_id']);
    
    // Send notifications
    $notificationService = app(NotificationService::class);
    $notificationService->notifyContentPublished($contentData);
});

$eventDispatcher->listen('content.workflow_transition', function($event) {
    $data = $event->getData();
    
    // Send approval notifications
    if ($data['new_status'] === 'content_review') {
        $workflowEngine = app(WorkflowEngine::class);
        $workflowEngine->notifyReviewers($data['content_id'], $data['new_status']);
    }
});
```

## 🧪 Testing Framework Integration

### Test Coverage

```php
class AdvancedCmsTestSuite extends PluginTestSuite
{
    public function getUnitTests(): array
    {
        return [
            'test_content_creation' => [$this, 'testContentCreation'],
            'test_workflow_transitions' => [$this, 'testWorkflowTransitions'],
            'test_content_versioning' => [$this, 'testContentVersioning']
        ];
    }
    
    public function testContentCreation(): void
    {
        $contentManager = new ContentManager();
        $content = $contentManager->createContent([
            'content_type' => 'page',
            'title' => 'Test Page',
            'status' => 'draft'
        ]);
        
        Assert::assertEquals('Test Page', $content->title);
        Assert::assertEquals('draft', $content->status);
    }
}
```

## 🛠️ Configuration

### Plugin Settings

```json
{
    "default_editor": "rich_text",
    "auto_save_interval": 30,
    "enable_versioning": true,
    "max_versions_per_content": 50,
    "media_storage": "local",
    "cdn_integration": "cloudflare",
    "workflow_notifications": true,
    "seo_analysis": true,
    "content_cache_ttl": 3600
}
```

### Database Tables
- `cms_content` - Core content storage
- `cms_content_versions` - Content revision history
- `cms_content_types` - Content type definitions
- `cms_media_assets` - Media file management
- `cms_workflows` - Editorial workflow definitions
- `cms_workflow_states` - Content workflow status

## 📚 API Endpoints

### REST API
- `GET /api/v1/cms/content` - List content
- `POST /api/v1/cms/content` - Create content
- `PUT /api/v1/cms/content/{id}` - Update content
- `POST /api/v1/cms/content/{id}/publish` - Publish content
- `GET /api/v1/cms/content-types` - List content types
- `POST /api/v1/cms/media/upload` - Upload media

### Usage Examples

```bash
# Create content
curl -X POST /api/v1/cms/content \
  -H "Content-Type: application/json" \
  -d '{"content_type": "page", "title": "New Page", "fields": {...}}'

# Publish content
curl -X POST /api/v1/cms/content/123/publish \
  -H "Authorization: Bearer {token}"
```

## 🔧 Installation

### Requirements
- PHP 8.3+
- Image processing extensions (GD or ImageMagick)
- File storage capabilities

### Setup

```bash
# Activate plugin
php cli/plugin.php activate advanced-cms

# Run migrations
php cli/migrate.php up

# Setup media storage
php cli/cms.php setup-media --storage=local
```

## 📖 Documentation

- **Content Management Guide** - Creating and managing content
- **Workflow Configuration** - Setting up editorial workflows
- **Template Development** - Creating custom content templates
- **API Integration** - Headless CMS capabilities

## 🚀 Production Ready

This plugin is part of the enhanced Shopologic ecosystem and is production-ready with:
- ✅ Enterprise-grade content management capabilities
- ✅ Advanced workflow and approval systems
- ✅ Cross-plugin integration for e-commerce content
- ✅ SEO optimization and performance features
- ✅ Multi-language and localization support
- ✅ Complete testing framework integration

---

**Advanced CMS** - Enterprise content management for Shopologic