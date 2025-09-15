<?php

/**
 * SSO Client SDK - HTTP Response Handler
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TodayIsLife\SsoClient\Exceptions\SsoClientException;

/**
 * Handles HTTP responses from SSO API
 */
class ResponseHandler
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle HTTP response and parse JSON
     */
    public function handle(ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();

        $this->logger->debug('SSO API Response', [
            'status_code' => $statusCode,
            'content_length' => strlen($body),
        ]);

        if (empty($body)) {
            if ($statusCode >= 200 && $statusCode < 300) {
                return [];
            }

            throw new SsoClientException(
                "Empty response body with status code {$statusCode}",
                $statusCode
            );
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Invalid JSON response from SSO API', [
                'status_code' => $statusCode,
                'body' => $body,
                'error' => $e->getMessage(),
            ]);

            throw new SsoClientException(
                'Invalid JSON response: ' . $e->getMessage(),
                $statusCode,
                $e
            );
        }

        if ($statusCode >= 400) {
            $this->logger->warning('SSO API Error Response', [
                'status_code' => $statusCode,
                'error_data' => $data,
            ]);

            throw new SsoClientException(
                $data['message'] ?? 'Unknown API error',
                $statusCode
            );
        }

        return $data;
    }
}