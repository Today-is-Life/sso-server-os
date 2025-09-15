<?php

/**
 * SSO Client SDK - HTTP Request Builder
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Http;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use TodayIsLife\SsoClient\Exceptions\AuthenticationException;

/**
 * Builds HTTP requests for SSO API
 */
class RequestBuilder
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Build HTTP request
     */
    public function build(
        string $method,
        string $endpoint,
        array $data = [],
        bool $requiresAuth = false,
        ?string $customToken = null
    ): RequestInterface {
        $url = $this->config['base_url'] . $endpoint;
        $headers = $this->buildHeaders($requiresAuth, $customToken);

        $body = null;
        if (!empty($data)) {
            if (strtoupper($method) === 'GET') {
                $url .= '?' . http_build_query($data);
            } else {
                $body = json_encode($data, JSON_THROW_ON_ERROR);
                $headers['Content-Type'] = 'application/json';
            }
        }

        return new Request($method, $url, $headers, $body);
    }

    /**
     * Build request headers
     */
    private function buildHeaders(bool $requiresAuth = false, ?string $customToken = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $this->config['user_agent'],
        ];

        if ($requiresAuth || $customToken) {
            $token = $customToken ?? $this->config['access_token'];

            if (!$token) {
                throw new AuthenticationException('Access token is required for this request');
            }

            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}