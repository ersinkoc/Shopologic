<?php

declare(strict_types=1);

namespace Shopologic\Core\Admin\Modules;

use Shopologic\Core\Admin\AdminPanel;
use Shopologic\Core\Admin\AdminModuleInterface;

/**
 * Settings module for admin panel
 */
class SettingsModule implements AdminModuleInterface
{
    public function register(AdminPanel $admin): void
    {
        // Register menu items
        $menuBuilder = $admin->getMenu();
        
        $menuBuilder->addItem([
            'title' => 'Settings',
            'url' => '/admin/settings',
            'icon' => 'cog',
            'permission' => 'admin.settings.view',
            'order' => 70,
            'group' => 'system',
            'children' => [
                [
                    'title' => 'General',
                    'url' => '/admin/settings/general',
                    'permission' => 'admin.settings.general'
                ],
                [
                    'title' => 'Store Settings',
                    'url' => '/admin/settings/store',
                    'permission' => 'admin.settings.store'
                ],
                [
                    'title' => 'Payment Methods',
                    'url' => '/admin/settings/payments',
                    'permission' => 'admin.settings.payments'
                ],
                [
                    'title' => 'Shipping Methods',
                    'url' => '/admin/settings/shipping',
                    'permission' => 'admin.settings.shipping'
                ],
                [
                    'title' => 'Tax Settings',
                    'url' => '/admin/settings/tax',
                    'permission' => 'admin.settings.tax'
                ],
                [
                    'title' => 'Email Settings',
                    'url' => '/admin/settings/email',
                    'permission' => 'admin.settings.email'
                ],
                [
                    'title' => 'Localization',
                    'url' => '/admin/settings/localization',
                    'permission' => 'admin.settings.localization'
                ],
                [
                    'title' => 'API Settings',
                    'url' => '/admin/settings/api',
                    'permission' => 'admin.settings.api'
                ],
                [
                    'title' => 'Security',
                    'url' => '/admin/settings/security',
                    'permission' => 'admin.settings.security'
                ]
            ]
        ]);
    }
    
    public function getName(): string
    {
        return 'settings';
    }
    
    public function getRoutes(): array
    {
        return [
            // General Settings
            ['GET', '/admin/settings', 'SettingsController@index'],
            ['GET', '/admin/settings/general', 'SettingsController@general'],
            ['PUT', '/admin/settings/general', 'SettingsController@updateGeneral'],
            
            // Store Settings
            ['GET', '/admin/settings/store', 'StoreSettingsController@index'],
            ['PUT', '/admin/settings/store', 'StoreSettingsController@update'],
            ['GET', '/admin/settings/store/inventory', 'StoreSettingsController@inventory'],
            ['PUT', '/admin/settings/store/inventory', 'StoreSettingsController@updateInventory'],
            ['GET', '/admin/settings/store/checkout', 'StoreSettingsController@checkout'],
            ['PUT', '/admin/settings/store/checkout', 'StoreSettingsController@updateCheckout'],
            
            // Payment Methods
            ['GET', '/admin/settings/payments', 'PaymentSettingsController@index'],
            ['GET', '/admin/settings/payments/{method}', 'PaymentSettingsController@show'],
            ['PUT', '/admin/settings/payments/{method}', 'PaymentSettingsController@update'],
            ['POST', '/admin/settings/payments/{method}/activate', 'PaymentSettingsController@activate'],
            ['POST', '/admin/settings/payments/{method}/deactivate', 'PaymentSettingsController@deactivate'],
            ['POST', '/admin/settings/payments/{method}/test', 'PaymentSettingsController@test'],
            
            // Shipping Methods
            ['GET', '/admin/settings/shipping', 'ShippingSettingsController@index'],
            ['GET', '/admin/settings/shipping/{method}', 'ShippingSettingsController@show'],
            ['PUT', '/admin/settings/shipping/{method}', 'ShippingSettingsController@update'],
            ['POST', '/admin/settings/shipping/{method}/activate', 'ShippingSettingsController@activate'],
            ['POST', '/admin/settings/shipping/{method}/deactivate', 'ShippingSettingsController@deactivate'],
            ['GET', '/admin/settings/shipping/zones', 'ShippingSettingsController@zones'],
            ['POST', '/admin/settings/shipping/zones', 'ShippingSettingsController@createZone'],
            ['PUT', '/admin/settings/shipping/zones/{id}', 'ShippingSettingsController@updateZone'],
            ['DELETE', '/admin/settings/shipping/zones/{id}', 'ShippingSettingsController@deleteZone'],
            
            // Tax Settings
            ['GET', '/admin/settings/tax', 'TaxSettingsController@index'],
            ['PUT', '/admin/settings/tax', 'TaxSettingsController@update'],
            ['GET', '/admin/settings/tax/rates', 'TaxSettingsController@rates'],
            ['POST', '/admin/settings/tax/rates', 'TaxSettingsController@createRate'],
            ['PUT', '/admin/settings/tax/rates/{id}', 'TaxSettingsController@updateRate'],
            ['DELETE', '/admin/settings/tax/rates/{id}', 'TaxSettingsController@deleteRate'],
            ['GET', '/admin/settings/tax/classes', 'TaxSettingsController@classes'],
            ['POST', '/admin/settings/tax/classes', 'TaxSettingsController@createClass'],
            
            // Email Settings
            ['GET', '/admin/settings/email', 'EmailSettingsController@index'],
            ['PUT', '/admin/settings/email', 'EmailSettingsController@update'],
            ['GET', '/admin/settings/email/templates', 'EmailSettingsController@templates'],
            ['GET', '/admin/settings/email/templates/{id}', 'EmailSettingsController@showTemplate'],
            ['PUT', '/admin/settings/email/templates/{id}', 'EmailSettingsController@updateTemplate'],
            ['POST', '/admin/settings/email/test', 'EmailSettingsController@sendTest'],
            
            // Localization
            ['GET', '/admin/settings/localization', 'LocalizationController@index'],
            ['PUT', '/admin/settings/localization', 'LocalizationController@update'],
            ['GET', '/admin/settings/localization/languages', 'LocalizationController@languages'],
            ['POST', '/admin/settings/localization/languages', 'LocalizationController@addLanguage'],
            ['PUT', '/admin/settings/localization/languages/{code}', 'LocalizationController@updateLanguage'],
            ['DELETE', '/admin/settings/localization/languages/{code}', 'LocalizationController@deleteLanguage'],
            ['GET', '/admin/settings/localization/currencies', 'LocalizationController@currencies'],
            ['POST', '/admin/settings/localization/currencies', 'LocalizationController@addCurrency'],
            ['PUT', '/admin/settings/localization/currencies/{code}', 'LocalizationController@updateCurrency'],
            ['DELETE', '/admin/settings/localization/currencies/{code}', 'LocalizationController@deleteCurrency'],
            ['POST', '/admin/settings/localization/currencies/update-rates', 'LocalizationController@updateRates'],
            
            // API Settings
            ['GET', '/admin/settings/api', 'APISettingsController@index'],
            ['PUT', '/admin/settings/api', 'APISettingsController@update'],
            ['GET', '/admin/settings/api/keys', 'APISettingsController@keys'],
            ['POST', '/admin/settings/api/keys', 'APISettingsController@createKey'],
            ['PUT', '/admin/settings/api/keys/{id}', 'APISettingsController@updateKey'],
            ['DELETE', '/admin/settings/api/keys/{id}', 'APISettingsController@deleteKey'],
            ['POST', '/admin/settings/api/keys/{id}/regenerate', 'APISettingsController@regenerateKey'],
            ['GET', '/admin/settings/api/webhooks', 'APISettingsController@webhooks'],
            ['POST', '/admin/settings/api/webhooks', 'APISettingsController@createWebhook'],
            ['PUT', '/admin/settings/api/webhooks/{id}', 'APISettingsController@updateWebhook'],
            ['DELETE', '/admin/settings/api/webhooks/{id}', 'APISettingsController@deleteWebhook'],
            
            // Security Settings
            ['GET', '/admin/settings/security', 'SecuritySettingsController@index'],
            ['PUT', '/admin/settings/security', 'SecuritySettingsController@update'],
            ['GET', '/admin/settings/security/2fa', 'SecuritySettingsController@twoFactor'],
            ['PUT', '/admin/settings/security/2fa', 'SecuritySettingsController@updateTwoFactor'],
            ['GET', '/admin/settings/security/ip-whitelist', 'SecuritySettingsController@ipWhitelist'],
            ['PUT', '/admin/settings/security/ip-whitelist', 'SecuritySettingsController@updateIpWhitelist'],
            ['GET', '/admin/settings/security/logs', 'SecuritySettingsController@logs'],
            ['POST', '/admin/settings/security/clear-logs', 'SecuritySettingsController@clearLogs']
        ];
    }
    
    public function getMenuItems(): array
    {
        return [
            [
                'title' => 'Settings',
                'url' => '/admin/settings',
                'icon' => 'cog',
                'permission' => 'admin.settings.view',
                'order' => 70,
                'children' => [
                    [
                        'title' => 'General',
                        'url' => '/admin/settings/general',
                        'permission' => 'admin.settings.general'
                    ],
                    [
                        'title' => 'Store Settings',
                        'url' => '/admin/settings/store',
                        'permission' => 'admin.settings.store'
                    ],
                    [
                        'title' => 'Payment Methods',
                        'url' => '/admin/settings/payments',
                        'permission' => 'admin.settings.payments'
                    ],
                    [
                        'title' => 'Shipping Methods',
                        'url' => '/admin/settings/shipping',
                        'permission' => 'admin.settings.shipping'
                    ],
                    [
                        'title' => 'Tax Settings',
                        'url' => '/admin/settings/tax',
                        'permission' => 'admin.settings.tax'
                    ],
                    [
                        'title' => 'Email Settings',
                        'url' => '/admin/settings/email',
                        'permission' => 'admin.settings.email'
                    ],
                    [
                        'title' => 'Localization',
                        'url' => '/admin/settings/localization',
                        'permission' => 'admin.settings.localization'
                    ],
                    [
                        'title' => 'API Settings',
                        'url' => '/admin/settings/api',
                        'permission' => 'admin.settings.api'
                    ],
                    [
                        'title' => 'Security',
                        'url' => '/admin/settings/security',
                        'permission' => 'admin.settings.security'
                    ]
                ]
            ]
        ];
    }
    
    public function getPermissions(): array
    {
        return [
            // General
            'admin.settings.view' => 'View settings',
            'admin.settings.general' => 'Manage general settings',
            
            // Store
            'admin.settings.store' => 'Manage store settings',
            'admin.settings.store.inventory' => 'Manage inventory settings',
            'admin.settings.store.checkout' => 'Manage checkout settings',
            
            // Payment
            'admin.settings.payments' => 'Manage payment methods',
            'admin.settings.payments.configure' => 'Configure payment gateways',
            
            // Shipping
            'admin.settings.shipping' => 'Manage shipping methods',
            'admin.settings.shipping.zones' => 'Manage shipping zones',
            
            // Tax
            'admin.settings.tax' => 'Manage tax settings',
            'admin.settings.tax.rates' => 'Manage tax rates',
            'admin.settings.tax.classes' => 'Manage tax classes',
            
            // Email
            'admin.settings.email' => 'Manage email settings',
            'admin.settings.email.templates' => 'Edit email templates',
            
            // Localization
            'admin.settings.localization' => 'Manage localization',
            'admin.settings.localization.languages' => 'Manage languages',
            'admin.settings.localization.currencies' => 'Manage currencies',
            
            // API
            'admin.settings.api' => 'Manage API settings',
            'admin.settings.api.keys' => 'Manage API keys',
            'admin.settings.api.webhooks' => 'Manage webhooks',
            
            // Security
            'admin.settings.security' => 'Manage security settings',
            'admin.settings.security.2fa' => 'Configure two-factor authentication',
            'admin.settings.security.logs' => 'View security logs'
        ];
    }
}