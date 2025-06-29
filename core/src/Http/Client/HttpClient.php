<?php

declare(strict_types=1);

namespace Shopologic\Core\Http\Client;

class HttpClient
{
    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'timeout' => 30,
            'user_agent' => 'Shopologic/1.0',
        ], $options);
    }

    /**
     * Send a GET request
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->request('GET', $url, [], $headers);
    }

    /**
     * Send a POST request
     */
    public function post(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * Send a PUT request
     */
    public function put(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return $this->request('PUT', $url, $data, $headers);
    }

    /**
     * Send a DELETE request
     */
    public function delete(string $url, array $headers = []): HttpResponse
    {
        return $this->request('DELETE', $url, [], $headers);
    }

    /**
     * Send an HTTP request
     */
    protected function request(string $method, string $url, array $data = [], array $headers = []): HttpResponse
    {
        $ch = curl_init();

        // Set basic options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->options['user_agent']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Set method
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        // Set headers
        if (!empty($headers)) {
            $headerArray = [];
            foreach ($headers as $key => $value) {
                $headerArray[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        }

        // Execute request
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);

        curl_close($ch);

        if ($error) {
            throw new HttpClientException("HTTP request failed: $error");
        }

        return new HttpResponse($httpCode, $response, $info);
    }

    /**
     * Send a JSON request
     */
    public function json(string $method, string $url, array $data = [], array $headers = []): HttpResponse
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->options['user_agent']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        if (!empty($data) && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);

        curl_close($ch);

        if ($error) {
            throw new HttpClientException("HTTP request failed: $error");
        }

        return new HttpResponse($httpCode, $response, $info);
    }
}