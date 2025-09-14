<?php

namespace App\Http\Middleware;

use App\Services\SiemService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Advanced API Rate Limiting Middleware
 *
 * Implements multiple rate limiting strategies:
 * - Per IP rate limiting
 * - Per user rate limiting
 * - Per endpoint rate limiting
 * - Sliding window algorithm
 * - Intelligent throttling based on request patterns
 */
class RateLimitingMiddleware
{
    protected SiemService $siemService;

    // Rate limit configurations per minute
    const LIMITS = [
        'login' => ['per_ip' => 10, 'per_user' => 5],
        'register' => ['per_ip' => 5, 'per_user' => 2],
        'api' => ['per_ip' => 100, 'per_user' => 60],
        'oauth' => ['per_ip' => 30, 'per_user' => 20],
        '2fa' => ['per_ip' => 20, 'per_user' => 10],
        'magic-link' => ['per_ip' => 3, 'per_user' => 2],
        'password-reset' => ['per_ip' => 5, 'per_user' => 3],
    ];

    // Sliding window duration in seconds
    const WINDOW_SIZE = 60;

    public function __construct(SiemService $siemService)
    {
        $this->siemService = $siemService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'api'): Response
    {
        $ip = $request->ip();
        $userId = auth()->id();
        $endpoint = $this->getEndpointIdentifier($request);

        // Check if IP is already blocked by SIEM
        if ($this->siemService->isIpBlocked($ip)) {
            $this->logRateLimitExceeded($request, 'ip_blocked_by_siem', $ip);
            return $this->rateLimitResponse('IP temporarily blocked due to suspicious activity', 429);
        }

        // Get rate limits for the request type
        $limits = self::LIMITS[$type] ?? self::LIMITS['api'];

        // Check IP-based rate limiting
        $ipKey = "rate_limit:ip:{$type}:{$ip}";
        if ($this->isRateLimitExceeded($ipKey, $limits['per_ip'])) {
            $this->logRateLimitExceeded($request, 'ip_rate_limit', $ip, $limits['per_ip']);
            return $this->rateLimitResponse('Too many requests from your IP address', 429);
        }

        // Check user-based rate limiting (if authenticated)
        if ($userId) {
            $userKey = "rate_limit:user:{$type}:{$userId}";
            if ($this->isRateLimitExceeded($userKey, $limits['per_user'])) {
                $this->logRateLimitExceeded($request, 'user_rate_limit', $userId, $limits['per_user']);
                return $this->rateLimitResponse('Too many requests for your account', 429);
            }
        }

        // Check endpoint-specific rate limiting (stricter for sensitive endpoints)
        if ($this->isSensitiveEndpoint($endpoint)) {
            $endpointKey = "rate_limit:endpoint:{$endpoint}:{$ip}";
            $endpointLimit = intval($limits['per_ip'] * 0.5); // 50% of normal limit
            if ($this->isRateLimitExceeded($endpointKey, $endpointLimit)) {
                $this->logRateLimitExceeded($request, 'endpoint_rate_limit', $endpoint, $endpointLimit);
                return $this->rateLimitResponse('Too many requests to this sensitive endpoint', 429);
            }
        }

        // Increment counters using sliding window
        $this->incrementCounter($ipKey);
        if ($userId) {
            $this->incrementCounter("rate_limit:user:{$type}:{$userId}");
        }
        if ($this->isSensitiveEndpoint($endpoint)) {
            $this->incrementCounter("rate_limit:endpoint:{$endpoint}:{$ip}");
        }

        $response = $next($request);

        // Add rate limit headers to response
        $this->addRateLimitHeaders($response, $ipKey, $limits['per_ip']);

        return $response;
    }

    /**
     * Check if rate limit is exceeded using sliding window algorithm
     */
    protected function isRateLimitExceeded(string $key, int $limit): bool
    {
        $current = $this->getCurrentCount($key);
        return $current >= $limit;
    }

    /**
     * Get current count using sliding window
     */
    protected function getCurrentCount(string $key): int
    {
        $now = time();
        $windowStart = $now - self::WINDOW_SIZE;

        // Get all timestamps for this key
        $requests = Cache::get($key, []);

        // Filter out old requests outside the window
        $requests = array_filter($requests, fn($timestamp) => $timestamp > $windowStart);

        // Update cache with filtered requests
        Cache::put($key, $requests, now()->addMinutes(2));

        return count($requests);
    }

    /**
     * Increment request counter
     */
    protected function incrementCounter(string $key): void
    {
        $now = time();
        $requests = Cache::get($key, []);
        $requests[] = $now;

        // Keep only recent requests
        $windowStart = $now - self::WINDOW_SIZE;
        $requests = array_filter($requests, fn($timestamp) => $timestamp > $windowStart);

        Cache::put($key, $requests, now()->addMinutes(2));
    }

    /**
     * Get endpoint identifier for rate limiting
     */
    protected function getEndpointIdentifier(Request $request): string
    {
        $path = $request->path();
        $method = $request->method();

        // Normalize common patterns
        $path = preg_replace('/\/\d+/', '/{id}', $path);
        $path = preg_replace('/\/[a-f0-9-]{36}/', '/{uuid}', $path);

        return strtolower("{$method}:{$path}");
    }

    /**
     * Check if endpoint is sensitive and needs stricter rate limiting
     */
    protected function isSensitiveEndpoint(string $endpoint): bool
    {
        $sensitivePatterns = [
            'post:auth/login',
            'post:auth/register',
            'post:auth/magic',
            'post:oauth/token',
            'post:2fa/enable',
            'post:2fa/disable',
            'get:oauth/userinfo',
            'post:auth/social/{provider}/callback',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (fnmatch($pattern, $endpoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log rate limit exceeded events
     */
    protected function logRateLimitExceeded(
        Request $request,
        string $reason,
        string $identifier,
        int $limit = null
    ): void {
        Log::warning('Rate limit exceeded', [
            'reason' => $reason,
            'identifier' => $identifier,
            'limit' => $limit,
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'endpoint' => $this->getEndpointIdentifier($request),
            'user_agent' => $request->userAgent(),
        ]);

        // Log security event if this looks like abuse
        if ($reason === 'ip_rate_limit' && $limit && $limit <= 10) {
            $this->siemService->logEvent(
                SiemService::EVENT_SUSPICIOUS_LOGIN,
                SiemService::LEVEL_WARNING,
                auth()->id(),
                $request,
                [
                    'reason' => 'rate_limit_exceeded',
                    'limit_type' => $reason,
                    'limit' => $limit,
                ]
            );
        }
    }

    /**
     * Create rate limit exceeded response
     */
    protected function rateLimitResponse(string $message, int $status = 429): Response
    {
        $response = [
            'error' => 'Rate limit exceeded',
            'message' => $message,
            'retry_after' => self::WINDOW_SIZE,
        ];

        return response()->json($response, $status)
            ->header('Retry-After', self::WINDOW_SIZE)
            ->header('X-RateLimit-Limit', 0)
            ->header('X-RateLimit-Remaining', 0)
            ->header('X-RateLimit-Reset', time() + self::WINDOW_SIZE);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(Response $response, string $key, int $limit): void
    {
        $current = $this->getCurrentCount($key);
        $remaining = max(0, $limit - $current);
        $reset = time() + self::WINDOW_SIZE;

        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', $remaining);
        $response->headers->set('X-RateLimit-Reset', $reset);

        // Add warning header when approaching limit
        if ($remaining <= ($limit * 0.2)) {
            $response->headers->set('X-RateLimit-Warning', 'Approaching rate limit');
        }
    }

    /**
     * Get rate limit status for debugging
     */
    public static function getRateLimitStatus(string $ip, ?string $userId = null): array
    {
        $status = [];

        foreach (self::LIMITS as $type => $limits) {
            $ipKey = "rate_limit:ip:{$type}:{$ip}";
            $ipCount = Cache::get($ipKey, []);

            $status[$type] = [
                'ip' => [
                    'current' => count($ipCount),
                    'limit' => $limits['per_ip'],
                    'remaining' => max(0, $limits['per_ip'] - count($ipCount)),
                ],
            ];

            if ($userId) {
                $userKey = "rate_limit:user:{$type}:{$userId}";
                $userCount = Cache::get($userKey, []);

                $status[$type]['user'] = [
                    'current' => count($userCount),
                    'limit' => $limits['per_user'],
                    'remaining' => max(0, $limits['per_user'] - count($userCount)),
                ];
            }
        }

        return $status;
    }
}