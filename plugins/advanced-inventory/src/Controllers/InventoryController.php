<?php

declare(strict_types=1);

namespace Shopologic\Plugins\AdvancedInventory\Controllers;

use Shopologic\Core\Http\Controller;
use Shopologic\Core\Http\Request;
use Shopologic\Core\Http\Response;
use AdvancedInventory\Services\InventoryManager;
use AdvancedInventory\Repositories\InventoryRepository;

class InventoryController extends Controller
{
    private InventoryManager $inventoryManager;
    private InventoryRepository $inventoryRepository;

    public function __construct()
    {
        $this->inventoryManager = app(InventoryManager::class);
        $this->inventoryRepository = app(InventoryRepository::class);
    }

    /**
     * Get inventory items
     */
    public function index(Request $request): Response
    {
        $filters = [
            'location_id' => $request->query('location_id'),
            'product_id' => $request->query('product_id'),
            'status' => $request->query('status'),
            'search' => $request->query('search')
        ];

        $page = (int)$request->query('page', 1);
        $perPage = (int)$request->query('per_page', 20);

        $items = $this->inventoryRepository->paginate($page, $perPage, $filters);

        return $this->json([
            'status' => 'success',
            'data' => $items['data'],
            'meta' => [
                'current_page' => $items['current_page'],
                'per_page' => $items['per_page'],
                'total' => $items['total'],
                'last_page' => $items['last_page']
            ]
        ]);
    }

    /**
     * Get inventory item by ID
     */
    public function show(Request $request, int $id): Response
    {
        $item = $this->inventoryRepository->findById($id);
        
        if (!$item) {
            return $this->json([
                'status' => 'error',
                'message' => 'Inventory item not found'
            ], 404);
        }

        return $this->json([
            'status' => 'success',
            'data' => $item
        ]);
    }

    /**
     * Update inventory quantity
     */
    public function updateQuantity(Request $request, int $id): Response
    {
        $this->validate($request, [
            'quantity' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'notes' => 'string'
        ]);

        try {
            $result = $this->inventoryManager->updateQuantity(
                $id,
                (float)$request->input('quantity'),
                $request->input('reason'),
                $request->input('notes')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Inventory quantity updated successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Adjust inventory quantity
     */
    public function adjustQuantity(Request $request, int $id): Response
    {
        $this->validate($request, [
            'adjustment' => 'required|numeric',
            'reason' => 'required|string',
            'notes' => 'string'
        ]);

        try {
            $result = $this->inventoryManager->adjustQuantity(
                $id,
                (float)$request->input('adjustment'),
                $request->input('reason'),
                $request->input('notes')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Inventory adjusted successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Transfer inventory between locations
     */
    public function transfer(Request $request): Response
    {
        $this->validate($request, [
            'from_location_id' => 'required|integer',
            'to_location_id' => 'required|integer',
            'product_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'string'
        ]);

        try {
            $result = $this->inventoryManager->transferStock(
                (int)$request->input('product_id'),
                (int)$request->input('from_location_id'),
                (int)$request->input('to_location_id'),
                (float)$request->input('quantity'),
                $request->input('notes')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Inventory transferred successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reserve inventory
     */
    public function reserve(Request $request): Response
    {
        $this->validate($request, [
            'inventory_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
            'reference_type' => 'required|string',
            'reference_id' => 'required|integer',
            'expires_at' => 'date'
        ]);

        try {
            $result = $this->inventoryManager->reserveStock(
                (int)$request->input('inventory_id'),
                (float)$request->input('quantity'),
                $request->input('reference_type'),
                (int)$request->input('reference_id'),
                $request->input('expires_at')
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Inventory reserved successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Release reserved inventory
     */
    public function releaseReservation(Request $request, int $reservationId): Response
    {
        try {
            $result = $this->inventoryManager->releaseReservation($reservationId);

            return $this->json([
                'status' => 'success',
                'message' => 'Reservation released successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get low stock items
     */
    public function lowStock(Request $request): Response
    {
        $threshold = $request->query('threshold');
        $locationId = $request->query('location_id');

        $items = $this->inventoryRepository->getLowStockItems($threshold);

        if ($locationId) {
            $items = array_filter($items, function($item) use ($locationId) {
                return $item['location_id'] == $locationId;
            });
        }

        return $this->json([
            'status' => 'success',
            'data' => array_values($items),
            'meta' => [
                'total' => count($items)
            ]
        ]);
    }

    /**
     * Get out of stock items
     */
    public function outOfStock(Request $request): Response
    {
        $locationId = $request->query('location_id');

        $items = $this->inventoryRepository->getOutOfStockItems();

        if ($locationId) {
            $items = array_filter($items, function($item) use ($locationId) {
                return $item['location_id'] == $locationId;
            });
        }

        return $this->json([
            'status' => 'success',
            'data' => array_values($items),
            'meta' => [
                'total' => count($items)
            ]
        ]);
    }

    /**
     * Get inventory metrics
     */
    public function metrics(Request $request): Response
    {
        $locationId = $request->query('location_id');
        
        $metrics = $this->inventoryManager->getInventoryMetrics($locationId);

        return $this->json([
            'status' => 'success',
            'data' => $metrics
        ]);
    }

    /**
     * Get inventory valuation
     */
    public function valuation(Request $request): Response
    {
        $locationId = $request->query('location_id');
        $method = $request->query('method', 'fifo');

        $valuation = $this->inventoryManager->getInventoryValuation($method, $locationId);

        return $this->json([
            'status' => 'success',
            'data' => [
                'method' => $method,
                'location_id' => $locationId,
                'total_value' => $valuation['total_value'],
                'items' => $valuation['items']
            ]
        ]);
    }

    /**
     * Get reorder suggestions
     */
    public function reorderSuggestions(Request $request): Response
    {
        $suggestions = $this->inventoryRepository->getReorderSuggestions();

        return $this->json([
            'status' => 'success',
            'data' => $suggestions,
            'meta' => [
                'total' => count($suggestions),
                'total_estimated_cost' => array_sum(array_column($suggestions, 'estimated_cost'))
            ]
        ]);
    }

    /**
     * Get ABC analysis
     */
    public function abcAnalysis(Request $request): Response
    {
        $analysis = $this->inventoryRepository->getAbcAnalysis();

        return $this->json([
            'status' => 'success',
            'data' => $analysis,
            'meta' => [
                'a_items' => count($analysis['A']),
                'b_items' => count($analysis['B']),
                'c_items' => count($analysis['C']),
                'total_items' => count($analysis['A']) + count($analysis['B']) + count($analysis['C'])
            ]
        ]);
    }

    /**
     * Get expiring items
     */
    public function expiringItems(Request $request): Response
    {
        $days = (int)$request->query('days', 30);
        
        $items = $this->inventoryRepository->getExpiringItems($days);

        return $this->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'total' => count($items),
                'days_ahead' => $days
            ]
        ]);
    }

    /**
     * Bulk update inventory
     */
    public function bulkUpdate(Request $request): Response
    {
        $this->validate($request, [
            'updates' => 'required|array',
            'updates.*.id' => 'required|integer',
            'updates.*.quantity' => 'required|numeric|min:0'
        ]);

        try {
            $updated = $this->inventoryRepository->bulkUpdate($request->input('updates'));

            return $this->json([
                'status' => 'success',
                'message' => "{$updated} items updated successfully",
                'data' => [
                    'updated_count' => $updated
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
     * Import inventory data
     */
    public function import(Request $request): Response
    {
        $this->validate($request, [
            'file' => 'required|file|mimes:csv,xlsx',
            'location_id' => 'required|integer',
            'update_existing' => 'boolean'
        ]);

        try {
            $file = $request->file('file');
            $locationId = (int)$request->input('location_id');
            $updateExisting = (bool)$request->input('update_existing', false);

            $result = $this->inventoryManager->importInventory($file, $locationId, $updateExisting);

            return $this->json([
                'status' => 'success',
                'message' => 'Inventory imported successfully',
                'data' => $result
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Export inventory data
     */
    public function export(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        $locationId = $request->query('location_id');
        $includeZeroStock = (bool)$request->query('include_zero_stock', false);

        try {
            $file = $this->inventoryManager->exportInventory($format, $locationId, $includeZeroStock);

            return $this->download($file, "inventory_export_{$format}");
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}