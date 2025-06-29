<?php
namespace MultiVendorMarketplace\Services;

use Shopologic\Core\Services\BaseService;
use Shopologic\Core\Exceptions\ValidationException;
use Shopologic\Core\Exceptions\BusinessException;

/**
 * Vendor Manager Service
 * 
 * Comprehensive vendor management with full lifecycle support
 */
class VendorManager extends BaseService
{
    private $cache;
    private $validator;
    private $notifications;
    private $permissions;
    
    public function __construct($api)
    {
        parent::__construct($api);
        $this->cache = $api->cache();
        $this->validator = $api->validator();
        $this->notifications = $api->notification();
        $this->permissions = $api->permissions();
    }
    
    /**
     * Create vendor profile
     */
    public function createProfile($vendorData, $options = []): array
    {
        // Validate vendor data
        $this->validateVendorData($vendorData);
        
        // Check if user already has vendor account
        if ($this->userHasVendorAccount($vendorData['user_id'])) {
            throw new BusinessException('User already has a vendor account');
        }
        
        // Generate unique slug
        $vendorData['slug'] = $this->generateUniqueSlug($vendorData['store_name'] ?? $vendorData['business_name']);
        
        // Set default values
        $vendorData = array_merge([
            'status' => $options['status'] ?? 'pending',
            'commission_rate' => $options['commission_rate'] ?? 15,
            'payout_schedule' => $options['payout_schedule'] ?? 'monthly',
            'capabilities' => $options['capabilities'] ?? $this->getDefaultCapabilities(),
            'settings' => json_encode($this->getDefaultSettings()),
            'rating' => 0,
            'total_sales' => 0
        ], $vendorData);
        
        // Store vendor profile
        $vendorId = $this->api->database()->table('vendors')->insertGetId($vendorData);
        
        // Create vendor role assignment
        $this->assignVendorRole($vendorData['user_id']);
        
        // Initialize vendor analytics
        $this->initializeVendorAnalytics($vendorId);
        
        // Clear cache
        $this->clearVendorCache();
        
        // Trigger vendor registered event
        $this->api->events()->dispatch('vendor.registered', [
            'vendor_id' => $vendorId,
            'vendor_data' => $vendorData
        ]);
        
        return $this->getVendor($vendorId);
    }
    
    /**
     * Update vendor status
     */
    public function updateStatus($vendorId, $status, $reason = null): bool
    {
        $validStatuses = ['pending', 'active', 'suspended', 'rejected'];
        
        if (!in_array($status, $validStatuses)) {
            throw new ValidationException("Invalid status: {$status}");
        }
        
        $vendor = $this->getVendor($vendorId);
        if (!$vendor) {
            throw new BusinessException('Vendor not found');
        }
        
        $updateData = ['status' => $status];
        
        // Handle status-specific updates
        switch ($status) {
            case 'active':
                $updateData['verified_at'] = date('Y-m-d H:i:s');
                $updateData['suspended_at'] = null;
                break;
                
            case 'suspended':
                $updateData['suspended_at'] = date('Y-m-d H:i:s');
                break;
                
            case 'rejected':
                $updateData['suspended_at'] = date('Y-m-d H:i:s');
                break;
        }
        
        // Update vendor status
        $this->api->database()->table('vendors')
            ->where('id', $vendorId)
            ->update($updateData);
        
        // Log status change
        $this->logStatusChange($vendorId, $vendor['status'], $status, $reason);
        
        // Send notifications
        $this->sendStatusChangeNotification($vendorId, $status, $reason);
        
        // Trigger appropriate event
        $this->api->events()->dispatch("vendor.{$status}", [
            'vendor_id' => $vendorId,
            'previous_status' => $vendor['status'],
            'reason' => $reason
        ]);
        
        // Clear cache
        $this->clearVendorCache($vendorId);
        
        return true;
    }
    
    /**
     * Get vendor by ID
     */
    public function getVendor($vendorId): ?array
    {
        return $this->cache->remember("vendor_{$vendorId}", 3600, function() use ($vendorId) {
            $vendor = $this->api->database()->table('vendors')
                ->where('id', $vendorId)
                ->first();
                
            if ($vendor) {
                $vendor['capabilities'] = json_decode($vendor['capabilities'], true);
                $vendor['settings'] = json_decode($vendor['settings'], true);
                $vendor['bank_details'] = $vendor['bank_details'] ? json_decode($vendor['bank_details'], true) : null;
            }
            
            return $vendor;
        });
    }
    
    /**
     * Get vendor by user ID
     */
    public function getVendorByUserId($userId): ?array
    {
        return $this->api->database()->table('vendors')
            ->where('user_id', $userId)
            ->first();
    }
    
    /**
     * Get vendor products
     */
    public function getVendorProducts($vendorId, $filters = []): array
    {
        $query = $this->api->database()->table('vendor_products as vp')
            ->join('products as p', 'vp.product_id', '=', 'p.id')
            ->where('vp.vendor_id', $vendorId)
            ->select('p.*', 'vp.vendor_sku', 'vp.commission_override', 'vp.stock_quantity as vendor_stock', 'vp.lead_time_days', 'vp.shipping_from');
        
        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }
        
        if (!empty($filters['category_id'])) {
            $query->where('p.category_id', $filters['category_id']);
        }
        
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('p.name', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('p.sku', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('vp.vendor_sku', 'LIKE', "%{$filters['search']}%");
            });
        }
        
        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        $query->orderBy("p.{$sortBy}", $sortOrder);
        
        // Apply pagination
        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 20;
        
        return $query->paginate($perPage, ['*'], 'page', $page);
    }
    
    /**
     * Get pending orders for vendor
     */
    public function getPendingOrders($vendorId): array
    {
        return $this->api->database()->table('vendor_orders')
            ->where('vendor_id', $vendorId)
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('created_at', 'DESC')
            ->get()
            ->map(function($order) {
                $order['items'] = json_decode($order['items'], true);
                return $order;
            })
            ->toArray();
    }
    
    /**
     * Get vendor notifications
     */
    public function getNotifications($vendorId, $unreadOnly = false): array
    {
        $query = $this->api->database()->table('vendor_notifications')
            ->where('vendor_id', $vendorId);
            
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }
        
        return $query->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get()
            ->map(function($notification) {
                $notification['data'] = json_decode($notification['data'], true);
                return $notification;
            })
            ->toArray();
    }
    
    /**
     * Mark notifications as read
     */
    public function markNotificationsRead($vendorId, $notificationIds = []): int
    {
        $query = $this->api->database()->table('vendor_notifications')
            ->where('vendor_id', $vendorId)
            ->whereNull('read_at');
            
        if (!empty($notificationIds)) {
            $query->whereIn('id', $notificationIds);
        }
        
        return $query->update(['read_at' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Get vendor rating
     */
    public function getVendorRating($vendorId): array
    {
        $stats = $this->api->database()->table('vendor_reviews')
            ->where('vendor_id', $vendorId)
            ->where('status', 'approved')
            ->selectRaw('
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            ')
            ->first();
            
        return [
            'average' => round($stats['average_rating'] ?? 0, 1),
            'total_reviews' => $stats['total_reviews'] ?? 0,
            'distribution' => [
                5 => $stats['five_star'] ?? 0,
                4 => $stats['four_star'] ?? 0,
                3 => $stats['three_star'] ?? 0,
                2 => $stats['two_star'] ?? 0,
                1 => $stats['one_star'] ?? 0
            ]
        ];
    }
    
    /**
     * Get average response time
     */
    public function getAverageResponseTime($vendorId): string
    {
        // This would typically calculate from message/support ticket response times
        // For now, return a placeholder
        return "< 2 hours";
    }
    
    /**
     * Get vendor policies
     */
    public function getVendorPolicies($vendorId): array
    {
        $vendor = $this->getVendor($vendorId);
        
        return [
            'shipping_policy' => $vendor['settings']['shipping_policy'] ?? 'Standard shipping applies',
            'return_policy' => $vendor['settings']['return_policy'] ?? 'Standard return policy applies',
            'cancellation_policy' => $vendor['settings']['cancellation_policy'] ?? 'Orders can be cancelled before shipping'
        ];
    }
    
    /**
     * Get total vendors
     */
    public function getTotalVendors($status = null): int
    {
        $query = $this->api->database()->table('vendors');
        
        if ($status) {
            $query->where('status', $status);
        }
        
        return $query->count();
    }
    
    /**
     * Get active vendors
     */
    public function getActiveVendors(): int
    {
        return $this->getTotalVendors('active');
    }
    
    /**
     * Get pending approvals
     */
    public function getPendingApprovals(): array
    {
        return $this->api->database()->table('vendors')
            ->where('status', 'pending')
            ->orderBy('created_at', 'ASC')
            ->get()
            ->toArray();
    }
    
    /**
     * Get total vendor products
     */
    public function getTotalVendorProducts(): int
    {
        return $this->api->database()->table('vendor_products')->count();
    }
    
    /**
     * Search vendors
     */
    public function searchVendors($query, $filters = []): array
    {
        $search = $this->api->database()->table('vendors');
        
        // Text search
        if (!empty($query)) {
            $search->where(function($q) use ($query) {
                $q->where('store_name', 'LIKE', "%{$query}%")
                  ->orWhere('business_name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%")
                  ->orWhere('city', 'LIKE', "%{$query}%");
            });
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $search->where('status', $filters['status']);
        }
        
        // Location filter
        if (!empty($filters['city'])) {
            $search->where('city', $filters['city']);
        }
        
        if (!empty($filters['state'])) {
            $search->where('state', $filters['state']);
        }
        
        if (!empty($filters['country'])) {
            $search->where('country', $filters['country']);
        }
        
        // Rating filter
        if (!empty($filters['min_rating'])) {
            $search->where('rating', '>=', $filters['min_rating']);
        }
        
        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        $search->orderBy($sortBy, $sortOrder);
        
        return $search->paginate($filters['per_page'] ?? 20);
    }
    
    /**
     * Validate vendor data
     */
    private function validateVendorData($data): void
    {
        $rules = [
            'user_id' => 'required|integer',
            'store_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'postal_code' => 'required|string'
        ];
        
        $messages = [
            'store_name.required' => 'Store name is required',
            'email.required' => 'Email address is required',
            'email.email' => 'Invalid email address',
            'phone.required' => 'Phone number is required',
            'address.required' => 'Business address is required'
        ];
        
        $validation = $this->validator->make($data, $rules, $messages);
        
        if ($validation->fails()) {
            throw new ValidationException($validation->errors()->first());
        }
    }
    
    /**
     * Check if user already has vendor account
     */
    private function userHasVendorAccount($userId): bool
    {
        return $this->api->database()->table('vendors')
            ->where('user_id', $userId)
            ->exists();
    }
    
    /**
     * Generate unique slug
     */
    private function generateUniqueSlug($name): string
    {
        $slug = $this->api->str()->slug($name);
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if slug exists
     */
    private function slugExists($slug): bool
    {
        return $this->api->database()->table('vendors')
            ->where('slug', $slug)
            ->exists();
    }
    
    /**
     * Get default capabilities
     */
    private function getDefaultCapabilities(): array
    {
        return [
            'max_products' => 1000,
            'can_create_coupons' => true,
            'can_manage_shipping' => true,
            'can_export_data' => true,
            'can_use_api' => false,
            'storage_limit_gb' => 10
        ];
    }
    
    /**
     * Get default settings
     */
    private function getDefaultSettings(): array
    {
        return [
            'auto_accept_orders' => true,
            'notification_email' => true,
            'notification_sms' => false,
            'vacation_mode' => false,
            'shipping_policy' => '',
            'return_policy' => '',
            'cancellation_policy' => '',
            'business_hours' => [
                'monday' => ['09:00', '17:00'],
                'tuesday' => ['09:00', '17:00'],
                'wednesday' => ['09:00', '17:00'],
                'thursday' => ['09:00', '17:00'],
                'friday' => ['09:00', '17:00'],
                'saturday' => ['10:00', '14:00'],
                'sunday' => 'closed'
            ]
        ];
    }
    
    /**
     * Assign vendor role to user
     */
    private function assignVendorRole($userId): void
    {
        $this->permissions->assignRole($userId, 'vendor');
    }
    
    /**
     * Initialize vendor analytics
     */
    private function initializeVendorAnalytics($vendorId): void
    {
        $this->api->database()->table('vendor_analytics')->insert([
            'vendor_id' => $vendorId,
            'date' => date('Y-m-d'),
            'views' => 0,
            'visits' => 0,
            'orders' => 0,
            'revenue' => 0,
            'commission' => 0,
            'products_sold' => 0,
            'new_customers' => 0,
            'return_rate' => 0,
            'average_order_value' => 0,
            'conversion_rate' => 0
        ]);
    }
    
    /**
     * Log status change
     */
    private function logStatusChange($vendorId, $oldStatus, $newStatus, $reason = null): void
    {
        $this->api->logger()->info('Vendor status changed', [
            'vendor_id' => $vendorId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => $this->api->auth()->id()
        ]);
    }
    
    /**
     * Send status change notification
     */
    private function sendStatusChangeNotification($vendorId, $status, $reason = null): void
    {
        $vendor = $this->getVendor($vendorId);
        
        $message = match($status) {
            'active' => 'Your vendor account has been approved! You can now start selling.',
            'suspended' => 'Your vendor account has been suspended. ' . ($reason ?? 'Please contact support.'),
            'rejected' => 'Your vendor application has been rejected. ' . ($reason ?? 'Please review the requirements.'),
            default => 'Your vendor account status has been updated.'
        };
        
        // Create notification
        $this->api->database()->table('vendor_notifications')->insert([
            'vendor_id' => $vendorId,
            'type' => 'status_change',
            'title' => 'Account Status Update',
            'message' => $message,
            'data' => json_encode([
                'old_status' => $vendor['status'],
                'new_status' => $status,
                'reason' => $reason
            ])
        ]);
        
        // Send email
        $this->notifications->send($vendor['user_id'], [
            'type' => 'vendor_status_change',
            'title' => 'Vendor Account Status Update',
            'message' => $message,
            'email' => true
        ]);
    }
    
    /**
     * Clear vendor cache
     */
    private function clearVendorCache($vendorId = null): void
    {
        if ($vendorId) {
            $this->cache->forget("vendor_{$vendorId}");
        } else {
            $this->cache->tags(['vendors'])->flush();
        }
    }
}