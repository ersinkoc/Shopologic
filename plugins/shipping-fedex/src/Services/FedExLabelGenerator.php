<?php

declare(strict_types=1);

namespace Shopologic\Plugins\ShippingFedEx\Services;

use Shopologic\Plugins\ShippingFedEx\Models\FedExShipment;
use Shopologic\Plugins\ShippingFedEx\Exceptions\FedExException;

class FedExLabelGenerator
{
    private FedExApiClient $apiClient;

    public function __construct(FedExApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Generate a shipping label
     */
    public function generateLabel(FedExShipment $shipment): array
    {
        try {
            // If label already exists, return it
            if ($shipment->label_data) {
                return [
                    'data' => $shipment->label_data,
                    'format' => $shipment->label_format ?? 'PDF'
                ];
            }

            // Retrieve label from FedEx if we have tracking number
            if ($shipment->tracking_number) {
                return $this->retrieveLabel($shipment->tracking_number);
            }

            throw new FedExException('No tracking number available for label generation');

        } catch (FedExException $e) {
            logger()->error('FedEx label generation failed', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Retrieve an existing label
     */
    public function retrieveLabel(string $trackingNumber): array
    {
        // FedEx API endpoint for retrieving labels
        $response = $this->apiClient->request('POST', '/ship/v1/shipments/packages/retrieve', [
            'trackingNumber' => $trackingNumber,
            'includeLabel' => true
        ]);

        if (!isset($response['output']['documents'][0])) {
            throw new FedExException('Label not found');
        }

        $document = $response['output']['documents'][0];

        return [
            'data' => $document['encodedLabel'] ?? null,
            'format' => $document['docType'] ?? 'PDF',
            'url' => $document['url'] ?? null
        ];
    }

    /**
     * Generate a return label
     */
    public function generateReturnLabel(array $shipmentData): array
    {
        // Swap shipper and recipient for return
        $returnData = $shipmentData;
        $returnData['shipper'] = $shipmentData['recipient'];
        $returnData['recipient'] = $shipmentData['shipper'];
        $returnData['specialServices']['returnShipment'] = true;

        $response = $this->apiClient->createShipment($returnData);

        return [
            'tracking_number' => $response['tracking_number'],
            'label_data' => $response['label_data'],
            'label_url' => $response['label_url']
        ];
    }

    /**
     * Print label to a printer
     */
    public function printLabel(string $labelData, string $format, array $printerSettings): bool
    {
        // This would integrate with a printing service
        // For now, just save to file
        $filename = tempnam(sys_get_temp_dir(), 'fedex_label_');
        
        if ($format === 'PDF') {
            $filename .= '.pdf';
            file_put_contents($filename, base64_decode($labelData));
        } elseif ($format === 'PNG') {
            $filename .= '.png';
            file_put_contents($filename, base64_decode($labelData));
        } elseif ($format === 'ZPL') {
            $filename .= '.zpl';
            file_put_contents($filename, $labelData);
        }

        // In a real implementation, this would send to printer
        logger()->info('Label saved for printing', ['filename' => $filename]);

        return true;
    }
}