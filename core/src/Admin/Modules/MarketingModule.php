<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;

/**
 * Marketing module for admin panel
 */
class MarketingModule implements AdminModuleInterface
{
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $menuBuilder = $admin->getMenu();
        
        $menuBuilder->addItem([
            'title' => 'Marketing',
            'url' => '/admin/marketing',
            'icon' => 'megaphone',
            'permission' => 'admin.marketing.view',
            'order' => 40,
            'group' => 'marketing',
            'children' => [
                [
                    'title' => 'Campaigns',
                    'url' => '/admin/marketing/campaigns',
                    'permission' => 'admin.marketing.campaigns'
                ],
                [
                    'title' => 'Promotions',
                    'url' => '/admin/marketing/promotions',
                    'permission' => 'admin.marketing.promotions'
                ],
                [
                    'title' => 'Coupons',
                    'url' => '/admin/marketing/coupons',
                    'permission' => 'admin.marketing.coupons'
                ],
                [
                    'title' => 'Email Marketing',
                    'url' => '/admin/marketing/email',
                    'permission' => 'admin.marketing.email'
                ],
                [
                    'title' => 'SEO Manager',
                    'url' => '/admin/marketing/seo',
                    'permission' => 'admin.marketing.seo'
                ],
                [
                    'title' => 'Social Media',
                    'url' => '/admin/marketing/social',
                    'permission' => 'admin.marketing.social'
                ],
                [
                    'title' => 'A/B Testing',
                    'url' => '/admin/marketing/ab-testing',
                    'permission' => 'admin.marketing.testing'
                ]
            ]
        ]);
    }
    
    public function getName(): string
    {
        return 'marketing';
    }
    
    public function getRoutes(): array
    {
        return [
            // Dashboard
            ['GET', '/admin/marketing', 'MarketingController@index'],
            
            // Campaigns
            ['GET', '/admin/marketing/campaigns', 'CampaignsController@index'],
            ['GET', '/admin/marketing/campaigns/create', 'CampaignsController@create'],
            ['POST', '/admin/marketing/campaigns', 'CampaignsController@store'],
            ['GET', '/admin/marketing/campaigns/{id}', 'CampaignsController@show'],
            ['GET', '/admin/marketing/campaigns/{id}/edit', 'CampaignsController@edit'],
            ['PUT', '/admin/marketing/campaigns/{id}', 'CampaignsController@update'],
            ['DELETE', '/admin/marketing/campaigns/{id}', 'CampaignsController@destroy'],
            ['POST', '/admin/marketing/campaigns/{id}/activate', 'CampaignsController@activate'],
            ['POST', '/admin/marketing/campaigns/{id}/pause', 'CampaignsController@pause'],
            ['GET', '/admin/marketing/campaigns/{id}/analytics', 'CampaignsController@analytics'],
            
            // Promotions
            ['GET', '/admin/marketing/promotions', 'PromotionsController@index'],
            ['GET', '/admin/marketing/promotions/create', 'PromotionsController@create'],
            ['POST', '/admin/marketing/promotions', 'PromotionsController@store'],
            ['GET', '/admin/marketing/promotions/{id}/edit', 'PromotionsController@edit'],
            ['PUT', '/admin/marketing/promotions/{id}', 'PromotionsController@update'],
            ['DELETE', '/admin/marketing/promotions/{id}', 'PromotionsController@destroy'],
            
            // Coupons
            ['GET', '/admin/marketing/coupons', 'CouponsController@index'],
            ['GET', '/admin/marketing/coupons/create', 'CouponsController@create'],
            ['POST', '/admin/marketing/coupons', 'CouponsController@store'],
            ['GET', '/admin/marketing/coupons/{id}/edit', 'CouponsController@edit'],
            ['PUT', '/admin/marketing/coupons/{id}', 'CouponsController@update'],
            ['DELETE', '/admin/marketing/coupons/{id}', 'CouponsController@destroy'],
            ['POST', '/admin/marketing/coupons/generate-bulk', 'CouponsController@generateBulk'],
            ['GET', '/admin/marketing/coupons/{id}/usage', 'CouponsController@usage'],
            
            // Email Marketing
            ['GET', '/admin/marketing/email', 'EmailMarketingController@index'],
            ['GET', '/admin/marketing/email/campaigns', 'EmailMarketingController@campaigns'],
            ['GET', '/admin/marketing/email/templates', 'EmailMarketingController@templates'],
            ['GET', '/admin/marketing/email/lists', 'EmailMarketingController@lists'],
            ['POST', '/admin/marketing/email/send', 'EmailMarketingController@send'],
            ['GET', '/admin/marketing/email/analytics', 'EmailMarketingController@analytics'],
            
            // SEO
            ['GET', '/admin/marketing/seo', 'SEOController@index'],
            ['GET', '/admin/marketing/seo/meta', 'SEOController@meta'],
            ['POST', '/admin/marketing/seo/meta', 'SEOController@updateMeta'],
            ['GET', '/admin/marketing/seo/sitemap', 'SEOController@sitemap'],
            ['POST', '/admin/marketing/seo/sitemap/generate', 'SEOController@generateSitemap'],
            ['GET', '/admin/marketing/seo/redirects', 'SEOController@redirects'],
            ['POST', '/admin/marketing/seo/redirects', 'SEOController@storeRedirect'],
            
            // Social Media
            ['GET', '/admin/marketing/social', 'SocialMediaController@index'],
            ['GET', '/admin/marketing/social/accounts', 'SocialMediaController@accounts'],
            ['POST', '/admin/marketing/social/accounts', 'SocialMediaController@connectAccount'],
            ['GET', '/admin/marketing/social/posts', 'SocialMediaController@posts'],
            ['POST', '/admin/marketing/social/posts', 'SocialMediaController@createPost'],
            ['POST', '/admin/marketing/social/posts/schedule', 'SocialMediaController@schedulePost'],
            
            // A/B Testing
            ['GET', '/admin/marketing/ab-testing', 'ABTestingController@index'],
            ['GET', '/admin/marketing/ab-testing/create', 'ABTestingController@create'],
            ['POST', '/admin/marketing/ab-testing', 'ABTestingController@store'],
            ['GET', '/admin/marketing/ab-testing/{id}', 'ABTestingController@show'],
            ['PUT', '/admin/marketing/ab-testing/{id}', 'ABTestingController@update'],
            ['POST', '/admin/marketing/ab-testing/{id}/start', 'ABTestingController@start'],
            ['POST', '/admin/marketing/ab-testing/{id}/stop', 'ABTestingController@stop'],
            ['GET', '/admin/marketing/ab-testing/{id}/results', 'ABTestingController@results']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Marketing',
                'url' => '/admin/marketing',
                'icon' => 'megaphone',
                'permission' => 'admin.marketing.view',
                'order' => 40,
                'children' => [
                    [
                        'title' => 'Campaigns',
                        'url' => '/admin/marketing/campaigns',
                        'permission' => 'admin.marketing.campaigns'
                    ],
                    [
                        'title' => 'Promotions',
                        'url' => '/admin/marketing/promotions',
                        'permission' => 'admin.marketing.promotions'
                    ],
                    [
                        'title' => 'Coupons',
                        'url' => '/admin/marketing/coupons',
                        'permission' => 'admin.marketing.coupons'
                    ],
                    [
                        'title' => 'Email Marketing',
                        'url' => '/admin/marketing/email',
                        'permission' => 'admin.marketing.email'
                    ],
                    [
                        'title' => 'SEO Manager',
                        'url' => '/admin/marketing/seo',
                        'permission' => 'admin.marketing.seo'
                    ],
                    [
                        'title' => 'Social Media',
                        'url' => '/admin/marketing/social',
                        'permission' => 'admin.marketing.social'
                    ],
                    [
                        'title' => 'A/B Testing',
                        'url' => '/admin/marketing/ab-testing',
                        'permission' => 'admin.marketing.testing'
                    ]
                ]
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            // General
            'admin.marketing.view' => 'View marketing dashboard',
            
            // Campaigns
            'admin.marketing.campaigns' => 'Manage marketing campaigns',
            'admin.marketing.campaigns.create' => 'Create marketing campaigns',
            'admin.marketing.campaigns.update' => 'Update marketing campaigns',
            'admin.marketing.campaigns.delete' => 'Delete marketing campaigns',
            
            // Promotions
            'admin.marketing.promotions' => 'Manage promotions',
            'admin.marketing.promotions.create' => 'Create promotions',
            'admin.marketing.promotions.update' => 'Update promotions',
            'admin.marketing.promotions.delete' => 'Delete promotions',
            
            // Coupons
            'admin.marketing.coupons' => 'Manage coupons',
            'admin.marketing.coupons.create' => 'Create coupons',
            'admin.marketing.coupons.update' => 'Update coupons',
            'admin.marketing.coupons.delete' => 'Delete coupons',
            'admin.marketing.coupons.bulk' => 'Generate bulk coupons',
            
            // Email
            'admin.marketing.email' => 'Manage email marketing',
            'admin.marketing.email.send' => 'Send marketing emails',
            'admin.marketing.email.templates' => 'Manage email templates',
            
            // SEO
            'admin.marketing.seo' => 'Manage SEO settings',
            'admin.marketing.seo.meta' => 'Edit meta tags',
            'admin.marketing.seo.sitemap' => 'Generate sitemaps',
            'admin.marketing.seo.redirects' => 'Manage redirects',
            
            // Social
            'admin.marketing.social' => 'Manage social media',
            'admin.marketing.social.post' => 'Create social media posts',
            'admin.marketing.social.schedule' => 'Schedule social media posts',
            
            // Testing
            'admin.marketing.testing' => 'Manage A/B tests',
            'admin.marketing.testing.create' => 'Create A/B tests',
            'admin.marketing.testing.results' => 'View A/B test results'
        ];
    }
}