<?php
namespace MultiVendorMarketplace\Controllers;

use Shopologic\Core\Controller\BaseController;
use Shopologic\Core\Http\JsonResponse;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Exceptions\ValidationException;
use Shopologic\Core\Exceptions\UnauthorizedException;
use MultiVendorMarketplace\Services\VendorManager;
use MultiVendorMarketplace\Services\VendorAnalytics;
use MultiVendorMarketplace\Services\PayoutManager;

/**
 * Vendor Controller
 * 
 * Handles vendor-related API endpoints
 */
class VendorController extends BaseController
{
    private VendorManager $vendorManager;
    private VendorAnalytics $vendorAnalytics;
    private PayoutManager $payoutManager;
    
    public function __construct()
    {
        parent::__construct();
        $this->vendorManager = new VendorManager($this->api);
        $this->vendorAnalytics = new VendorAnalytics($this->api);
        $this->payoutManager = new PayoutManager($this->api);
    }
    
    /**
     * Register new vendor
     */
    public function register(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $this->validate($request, [
                'store_name' => 'required|string|max:255',
                'business_name' => 'string|max:255',
                'email' => 'required|email',
                'phone' => 'required|string',
                'address' => 'required|string',
                'city' => 'required|string',
                'state' => 'required|string',
                'country' => 'required|string',
                'postal_code' => 'required|string',
                'tax_id' => 'string',
                'description' => 'string',
                'bank_details' => 'array'
            ]);
            
            // Check if user is authenticated
            $user = $this->api->auth()->user();
            if (!$user) {
                throw new UnauthorizedException('Authentication required');
            }
            
            // Add user ID to vendor data
            $validated['user_id'] = $user->id;
            
            // Encrypt bank details if provided
            if (!empty($validated['bank_details'])) {
                $validated['bank_details'] = json_encode($this->encryptBankDetails($validated['bank_details']));
            }
            
            // Create vendor profile
            $vendor = $this->vendorManager->createProfile($validated);
            
            return $this->success([
                'message' => 'Vendor registration successful',
                'vendor' => $vendor,
                'status' => $vendor['status'],
                'next_steps' => $this->getNextSteps($vendor['status'])
            ]);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
    
    /**
     * Get vendor details
     */
    public function show($id): JsonResponse
    {
        try {
            $vendor = $this->vendorManager->getVendor($id);
            
            if (!$vendor) {
                return $this->error('Vendor not found', 404);
            }
            
            // Check if user has permission to view full details
            $canViewFullDetails = $this->canViewVendorDetails($vendor);
            
            // Prepare response data
            $data = [
                'vendor' => $canViewFullDetails ? $vendor : $this->getPublicVendorData($vendor),
                'rating' => $this->vendorManager->getVendorRating($id),
                'policies' => $this->vendorManager->getVendorPolicies($id)
            ];
            
            // Add analytics if vendor owner or admin
            if ($canViewFullDetails) {
                $data['analytics'] = $this->vendorAnalytics->getSalesOverview($id);
                $data['performance'] = $this->vendorAnalytics->getPerformanceMetrics($id);
            }
            
            return $this->success($data);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Update vendor
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $vendor = $this->vendorManager->getVendor($id);
            
            if (!$vendor) {
                return $this->error('Vendor not found', 404);
            }
            
            // Check permission
            if (!$this->canEditVendor($vendor)) {
                throw new UnauthorizedException('Permission denied');
            }
            
            // Validate update data
            $validated = $this->validate($request, [
                'store_name' => 'string|max:255',
                'business_name' => 'string|max:255',
                'description' => 'string',
                'logo' => 'string|max:500',
                'banner' => 'string|max:500',
                'phone' => 'string',
                'address' => 'string',
                'city' => 'string',
                'state' => 'string',
                'country' => 'string',
                'postal_code' => 'string',
                'settings' => 'array'
            ]);
            
            // Update vendor
            $updated = $this->api->database()->table('vendors')
                ->where('id', $id)
                ->update(array_merge($validated, [
                    'updated_at' => date('Y-m-d H:i:s')
                ]));
                
            if ($updated) {
                // Clear cache
                $this->api->cache()->forget("vendor_{$id}");
                
                return $this->success([
                    'message' => 'Vendor updated successfully',
                    'vendor' => $this->vendorManager->getVendor($id)
                ]);
            }
            
            return $this->error('Update failed', 400);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get vendor products
     */
    public function getProducts(Request $request, $id): JsonResponse
    {
        try {
            $vendor = $this->vendorManager->getVendor($id);
            
            if (!$vendor) {
                return $this->error('Vendor not found', 404);
            }
            
            // Get filters from request
            $filters = $request->only(['status', 'category_id', 'search', 'sort_by', 'sort_order', 'page', 'per_page']);
            
            // Get products
            $products = $this->vendorManager->getVendorProducts($id, $filters);
            
            return $this->success([
                'vendor' => [
                    'id' => $vendor['id'],
                    'store_name' => $vendor['store_name']
                ],
                'products' => $products
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get vendor analytics
     */
    public function getAnalytics(Request $request, $id): JsonResponse
    {
        try {
            $vendor = $this->vendorManager->getVendor($id);
            
            if (!$vendor) {
                return $this->error('Vendor not found', 404);
            }
            
            // Check permission
            if (!$this->canViewVendorAnalytics($vendor)) {
                throw new UnauthorizedException('Permission denied');
            }
            
            $period = $request->input('period', 'month');
            
            $analytics = [
                'sales_overview' => $this->vendorAnalytics->getSalesOverview($id),
                'performance_metrics' => $this->vendorAnalytics->getPerformanceMetrics($id),
                'revenue_analytics' => $this->vendorAnalytics->getRevenueAnalytics($id, $period),
                'customer_analytics' => $this->vendorAnalytics->getCustomerAnalytics($id),
                'product_performance' => $this->vendorAnalytics->getProductPerformance($id),
                'conversion_funnel' => $this->vendorAnalytics->getConversionFunnel($id, $period)
            ];
            
            // Add competitor analysis for premium vendors
            if ($vendor['settings']['tier'] === 'premium') {
                $analytics['competitor_analysis'] = $this->vendorAnalytics->getCompetitorAnalysis($id);
            }
            
            return $this->success($analytics);
            
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Request payout
     */
    public function requestPayout(Request $request): JsonResponse
    {
        try {
            $user = $this->api->auth()->user();
            if (!$user) {
                throw new UnauthorizedException('Authentication required');
            }
            
            // Get vendor by user ID
            $vendor = $this->vendorManager->getVendorByUserId($user->id);
            
            if (!$vendor) {
                return $this->error('Vendor account not found', 404);
            }
            
            if ($vendor['status'] !== 'active') {
                return $this->error('Vendor account is not active', 400);
            }
            
            // Validate request
            $validated = $this->validate($request, [
                'amount' => 'numeric|min:0'
            ]);
            
            // Process payout request
            $result = $this->payoutManager->requestPayout(
                $vendor['id'], 
                $validated['amount'] ?? null
            );
            
            return $this->success($result);
            
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
    
    /**
     * Get vendor list
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = $request->input('q', '');
            $filters = $request->only(['status', 'city', 'state', 'country', 'min_rating', 'sort_by', 'sort_order', 'per_page']);
            
            $vendors = $this->vendorManager->searchVendors($query, $filters);
            
            return $this->success($vendors);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Delete vendor (soft delete)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $vendor = $this->vendorManager->getVendor($id);
            
            if (!$vendor) {
                return $this->error('Vendor not found', 404);
            }
            
            // Check permission
            if (!$this->api->auth()->hasRole('admin')) {
                throw new UnauthorizedException('Admin permission required');
            }
            
            // Soft delete by updating status
            $this->vendorManager->updateStatus($id, 'deleted', 'Account deleted by admin');
            
            return $this->success([
                'message' => 'Vendor deleted successfully'
            ]);
            
        } catch (UnauthorizedException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get vendor reviews
     */
    public function getReviews(Request $request, $id): JsonResponse
    {
        try {
            $vendor = $this->vendorManager->getVendor($id);
            
            if (!$vendor) {
                return $this->error('Vendor not found', 404);
            }
            
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            $reviews = $this->api->database()->table('vendor_reviews as vr')
                ->join('customers as c', 'vr.customer_id', '=', 'c.id')
                ->where('vr.vendor_id', $id)
                ->where('vr.status', 'approved')
                ->select('vr.*', 'c.first_name', 'c.last_name')
                ->orderBy('vr.created_at', 'DESC')
                ->paginate($perPage, ['*'], 'page', $page);
                
            return $this->success([
                'vendor' => [
                    'id' => $vendor['id'],
                    'store_name' => $vendor['store_name']
                ],
                'rating' => $this->vendorManager->getVendorRating($id),
                'reviews' => $reviews
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Helper method to check if user can view vendor details
     */
    private function canViewVendorDetails($vendor): bool
    {
        $user = $this->api->auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Check if user is vendor owner or admin
        return $vendor['user_id'] == $user->id || $this->api->auth()->hasRole('admin');
    }
    
    /**
     * Helper method to check if user can edit vendor
     */
    private function canEditVendor($vendor): bool
    {
        $user = $this->api->auth()->user();
        
        if (!$user) {
            return false;
        }
        
        return $vendor['user_id'] == $user->id || $this->api->auth()->hasRole('admin');
    }
    
    /**
     * Helper method to check if user can view vendor analytics
     */
    private function canViewVendorAnalytics($vendor): bool
    {
        return $this->canViewVendorDetails($vendor);
    }
    
    /**
     * Get public vendor data
     */
    private function getPublicVendorData($vendor): array
    {
        return [
            'id' => $vendor['id'],
            'store_name' => $vendor['store_name'],
            'slug' => $vendor['slug'],
            'description' => $vendor['description'],
            'logo' => $vendor['logo'],
            'banner' => $vendor['banner'],
            'city' => $vendor['city'],
            'state' => $vendor['state'],
            'country' => $vendor['country'],
            'rating' => $vendor['rating'],
            'verified_at' => $vendor['verified_at'],
            'created_at' => $vendor['created_at']
        ];
    }
    
    /**
     * Get next steps based on vendor status
     */
    private function getNextSteps($status): array
    {
        switch ($status) {
            case 'pending':
                return [
                    'Upload required documents',
                    'Wait for admin approval',
                    'Complete bank account setup'
                ];
                
            case 'active':
                return [
                    'Add your first product',
                    'Configure shipping settings',
                    'Set up payment preferences'
                ];
                
            default:
                return [];
        }
    }
    
    /**
     * Encrypt bank details
     */
    private function encryptBankDetails($bankDetails): array
    {
        // In production, use proper encryption
        return $bankDetails;
    }
}