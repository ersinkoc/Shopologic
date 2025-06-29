<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Controllers\Admin;

use Shopologic\Core\Http\Controllers\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use Shopologic\Core\MultiStore\StoreManager;
use Shopologic\Core\MultiStore\Store;
use Shopologic\Core\MultiStore\StoreSettings;

class StoreController extends Controller
{
    private StoreManager $storeManager;

    public function __construct(StoreManager $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * List all stores
     */
    public function index(Request $request): Response
    {
        $stores = Store::query()
            ->when($request->get('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('domain', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20);

        return $this->view('admin.stores.index', [
            'stores' => $stores
        ]);
    }

    /**
     * Show create store form
     */
    public function create(): Response
    {
        return $this->view('admin.stores.create', [
            'themes' => $this->getAvailableThemes(),
            'locales' => $this->getAvailableLocales(),
            'currencies' => $this->getAvailableCurrencies(),
            'timezones' => $this->getAvailableTimezones()
        ]);
    }

    /**
     * Create new store
     */
    public function store(Request $request): Response
    {
        $validated = $this->validate($request, [
            'code' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'subdomain' => 'nullable|string|max:50|regex:/^[a-z0-9-]+$/',
            'path_prefix' => 'nullable|string|max:50',
            'theme' => 'nullable|string|max:50',
            'locale' => 'required|string|max:10',
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:50',
            'is_active' => 'boolean'
        ]);

        try {
            $store = $this->storeManager->createStore($validated);
            
            return $this->redirect('/admin/stores/' . $store->id)
                ->with('success', 'Store created successfully');
        } catch (\Exception $e) {
            return $this->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show store details
     */
    public function show($id): Response
    {
        $store = Store::findOrFail($id);
        
        return $this->view('admin.stores.show', [
            'store' => $store,
            'stats' => $this->getStoreStats($store)
        ]);
    }

    /**
     * Show edit store form
     */
    public function edit($id): Response
    {
        $store = Store::findOrFail($id);
        
        return $this->view('admin.stores.edit', [
            'store' => $store,
            'themes' => $this->getAvailableThemes(),
            'locales' => $this->getAvailableLocales(),
            'currencies' => $this->getAvailableCurrencies(),
            'timezones' => $this->getAvailableTimezones()
        ]);
    }

    /**
     * Update store
     */
    public function update(Request $request, $id): Response
    {
        $store = Store::findOrFail($id);
        
        $validated = $this->validate($request, [
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'subdomain' => 'nullable|string|max:50|regex:/^[a-z0-9-]+$/',
            'path_prefix' => 'nullable|string|max:50',
            'theme' => 'nullable|string|max:50',
            'locale' => 'required|string|max:10',
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:50',
            'is_active' => 'boolean'
        ]);

        try {
            $store = $this->storeManager->updateStore($store, $validated);
            
            return $this->redirect('/admin/stores/' . $store->id)
                ->with('success', 'Store updated successfully');
        } catch (\Exception $e) {
            return $this->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Delete store
     */
    public function destroy($id): Response
    {
        $store = Store::findOrFail($id);
        
        try {
            $this->storeManager->deleteStore($store);
            
            return $this->redirect('/admin/stores')
                ->with('success', 'Store deleted successfully');
        } catch (\Exception $e) {
            return $this->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show store settings
     */
    public function settings($id): Response
    {
        $store = Store::findOrFail($id);
        $settings = StoreSettings::getStoreSettings($store->id);
        
        return $this->view('admin.stores.settings', [
            'store' => $store,
            'settings' => $settings,
            'settingsConfig' => $this->getSettingsConfig()
        ]);
    }

    /**
     * Update store settings
     */
    public function updateSettings(Request $request, $id): Response
    {
        $store = Store::findOrFail($id);
        $settings = $request->get('settings', []);
        
        foreach ($settings as $key => $value) {
            StoreSettings::setValue($store->id, $key, $value);
        }
        
        return $this->back()
            ->with('success', 'Settings updated successfully');
    }

    /**
     * List store users
     */
    public function users($id): Response
    {
        $store = Store::findOrFail($id);
        $users = $store->users()->paginate(20);
        
        return $this->view('admin.stores.users', [
            'store' => $store,
            'users' => $users,
            'roles' => config('multistore.roles')
        ]);
    }

    /**
     * Add user to store
     */
    public function addUser(Request $request, $id): Response
    {
        $store = Store::findOrFail($id);
        
        $validated = $this->validate($request, [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string'
        ]);
        
        $store->users()->attach($validated['user_id'], [
            'role' => $validated['role'],
            'permissions' => json_encode([])
        ]);
        
        return $this->back()
            ->with('success', 'User added to store');
    }

    /**
     * Update user role in store
     */
    public function updateUser(Request $request, $id, $userId): Response
    {
        $store = Store::findOrFail($id);
        
        $validated = $this->validate($request, [
            'role' => 'required|string'
        ]);
        
        $store->users()->updateExistingPivot($userId, [
            'role' => $validated['role']
        ]);
        
        return $this->back()
            ->with('success', 'User role updated');
    }

    /**
     * Remove user from store
     */
    public function removeUser($id, $userId): Response
    {
        $store = Store::findOrFail($id);
        $store->users()->detach($userId);
        
        return $this->back()
            ->with('success', 'User removed from store');
    }

    /**
     * List store products
     */
    public function products($id): Response
    {
        $store = Store::findOrFail($id);
        $products = $store->products()->paginate(20);
        
        return $this->view('admin.stores.products', [
            'store' => $store,
            'products' => $products
        ]);
    }

    /**
     * Sync products with store
     */
    public function syncProducts(Request $request, $id): Response
    {
        $store = Store::findOrFail($id);
        
        $productSync = [];
        foreach ($request->get('products', []) as $productId => $data) {
            if ($data['enabled'] ?? false) {
                $productSync[$productId] = [
                    'price' => $data['price'] ?? null,
                    'stock' => $data['stock'] ?? null,
                    'is_active' => true
                ];
            }
        }
        
        $store->products()->sync($productSync);
        
        return $this->back()
            ->with('success', 'Products synced successfully');
    }

    // Private helper methods

    private function getStoreStats(Store $store): array
    {
        return [
            'total_orders' => $store->orders()->count(),
            'total_revenue' => $store->orders()
                ->where('status', 'completed')
                ->sum('grand_total'),
            'total_customers' => $store->orders()
                ->distinct('customer_email')
                ->count('customer_email'),
            'total_products' => $store->products()
                ->where('store_products.is_active', true)
                ->count()
        ];
    }

    private function getAvailableThemes(): array
    {
        $themesPath = base_path('themes');
        $themes = [];
        
        if (is_dir($themesPath)) {
            foreach (scandir($themesPath) as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($themesPath . '/' . $dir)) {
                    $themes[] = $dir;
                }
            }
        }
        
        return $themes;
    }

    private function getAvailableLocales(): array
    {
        return [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean'
        ];
    }

    private function getAvailableCurrencies(): array
    {
        return [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar',
            'JPY' => 'Japanese Yen',
            'CNY' => 'Chinese Yuan',
            'CHF' => 'Swiss Franc',
            'SEK' => 'Swedish Krona',
            'NZD' => 'New Zealand Dollar'
        ];
    }

    private function getAvailableTimezones(): array
    {
        return \DateTimeZone::listIdentifiers();
    }

    private function getSettingsConfig(): array
    {
        return [
            'general' => [
                'label' => 'General Settings',
                'fields' => [
                    'general.store_name' => ['type' => 'text', 'label' => 'Store Name'],
                    'general.store_email' => ['type' => 'email', 'label' => 'Store Email'],
                    'general.store_phone' => ['type' => 'tel', 'label' => 'Store Phone'],
                    'general.store_address' => ['type' => 'textarea', 'label' => 'Store Address']
                ]
            ],
            'catalog' => [
                'label' => 'Catalog Settings',
                'fields' => [
                    'catalog.products_per_page' => ['type' => 'number', 'label' => 'Products Per Page'],
                    'catalog.default_sort' => [
                        'type' => 'select',
                        'label' => 'Default Sort',
                        'options' => [
                            'newest' => 'Newest First',
                            'price_asc' => 'Price: Low to High',
                            'price_desc' => 'Price: High to Low',
                            'name' => 'Name: A to Z'
                        ]
                    ]
                ]
            ],
            'checkout' => [
                'label' => 'Checkout Settings',
                'fields' => [
                    'checkout.guest_checkout' => ['type' => 'boolean', 'label' => 'Allow Guest Checkout'],
                    'checkout.require_phone' => ['type' => 'boolean', 'label' => 'Require Phone Number']
                ]
            ]
        ];
    }
}