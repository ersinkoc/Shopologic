<?php

declare(strict_types=1);

namespace Shopologic\Plugins\PaymentStripe\Services;

use Shopologic\Plugins\PaymentStripe\Exceptions\StripeException;

/**
 * Stripe API client implementation without external dependencies
 */
class StripeClient\n{
    private const API_BASE = 'https://api.stripe.com/v1';
    private const API_VERSION = '2023-10-16';
    
    private string $secretKey;
    private string $publishableKey;
    private array $defaultHeaders;

    public function __construct(string $secretKey, string $publishableKey)
    {
        $this->secretKey = $secretKey;
        $this->publishableKey = $publishableKey;
        
        $this->defaultHeaders = [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Stripe-Version' => self::API_VERSION,
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'Shopologic/1.0.0'
        ];
    }

    public function createPaymentIntent(array $params): array
    {
        return $this->request('POST', '/payment_intents', $params);
    }

    public function getPaymentIntent(string $id): array
    {
        return $this->request('GET', '/payment_intents/' . $id);
    }

    public function updatePaymentIntent(string $id, array $params): array
    {
        return $this->request('POST', '/payment_intents/' . $id, $params);
    }

    public function confirmPaymentIntent(string $id, array $params = []): array
    {
        return $this->request('POST', '/payment_intents/' . $id . '/confirm', $params);
    }

    public function capturePaymentIntent(string $id, array $params = []): array
    {
        return $this->request('POST', '/payment_intents/' . $id . '/capture', $params);
    }

    public function cancelPaymentIntent(string $id, array $params = []): array
    {
        return $this->request('POST', '/payment_intents/' . $id . '/cancel', $params);
    }

    public function createSetupIntent(array $params): array
    {
        return $this->request('POST', '/setup_intents', $params);
    }

    public function createCustomer(array $params): array
    {
        return $this->request('POST', '/customers', $params);
    }

    public function getCustomer(string $id): array
    {
        return $this->request('GET', '/customers/' . $id);
    }

    public function updateCustomer(string $id, array $params): array
    {
        return $this->request('POST', '/customers/' . $id, $params);
    }

    public function createPaymentMethod(array $params): array
    {
        return $this->request('POST', '/payment_methods', $params);
    }

    public function attachPaymentMethod(string $id, array $params): array
    {
        return $this->request('POST', '/payment_methods/' . $id . '/attach', $params);
    }

    public function detachPaymentMethod(string $id): array
    {
        return $this->request('POST', '/payment_methods/' . $id . '/detach');
    }

    public function listPaymentMethods(array $params): array
    {
        return $this->request('GET', '/payment_methods', $params);
    }

    public function createRefund(array $params): array
    {
        return $this->request('POST', '/refunds', $params);
    }

    public function getRefund(string $id): array
    {
        return $this->request('GET', '/refunds/' . $id);
    }

    public function createCharge(array $params): array
    {
        return $this->request('POST', '/charges', $params);
    }

    public function getCharge(string $id): array
    {
        return $this->request('GET', '/charges/' . $id);
    }

    public function createWebhookEndpoint(array $params): array
    {
        return $this->request('POST', '/webhook_endpoints', $params);
    }

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $elements = explode(',', $signature);
        $timestamp = null;
        $signatures = [];

        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if ($parts[0] === 't') {
                $timestamp = $parts[1];
            } elseif ($parts[0] === 'v1') {
                $signatures[] = $parts[1];
            }
        }

        if (!$timestamp) {
            return false;
        }

        // Check if timestamp is within tolerance (5 minutes)
        $tolerance = 300;
        if (abs(time() - intval($timestamp)) > $tolerance) {
            return false;
        }

        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        // Check if any signature matches
        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    private function request(string $method, string $path, array $params = []): array
    {
        $url = self::API_BASE . $path;
        
        $ch = curl_init();
        
        // Set basic options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Set headers
        $headers = [];
        foreach ($this->defaultHeaders as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set method-specific options
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($params)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->buildQueryString($params));
                }
                break;
                
            case 'GET':
                if (!empty($params)) {
                    $url .= '?' . $this->buildQueryString($params);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
                
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($params)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->buildQueryString($params));
                }
                break;
                
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new StripeException('cURL error: ' . $error);
        }
        
        curl_close($ch);
        
        // Parse response
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new StripeException('Invalid JSON response from Stripe API');
        }
        
        // Handle errors
        if ($httpCode >= 400) {
            $this->handleApiError($data, $httpCode);
        }
        
        return $data;
    }

    private function buildQueryString(array $params, string $prefix = ''): string
    {
        $query = [];
        
        foreach ($params as $key => $value) {
            $key = $prefix ? $prefix . '[' . $key . ']' : $key;
            
            if (is_array($value)) {
                $query[] = $this->buildQueryString($value, $key);
            } elseif ($value !== null) {
                $query[] = urlencode($key) . '=' . urlencode((string) $value);
            }
        }
        
        return implode('&', $query);
    }

    private function handleApiError(array $response, int $httpCode): void
    {
        $error = $response['error'] ?? [];
        $message = $error['message'] ?? 'Unknown Stripe API error';
        $code = $error['code'] ?? 'unknown_error';
        $type = $error['type'] ?? 'api_error';
        
        throw new StripeException($message, $httpCode, $code, $type);
    }
}