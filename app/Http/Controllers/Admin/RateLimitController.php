<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RateLimitingMiddleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;

class RateLimitController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Show rate limiting dashboard
     */
    public function dashboard(): View
    {
        return view('admin.rate-limits');
    }

    /**
     * Get rate limit status for an IP or user
     */
    public function status(Request $request): JsonResponse
    {
        $ip = $request->get('ip', $request->ip());
        $userId = $request->get('user_id');

        $status = RateLimitingMiddleware::getRateLimitStatus($ip, $userId);

        return response()->json([
            'ip' => $ip,
            'user_id' => $userId,
            'limits' => $status,
        ]);
    }

    /**
     * Clear rate limits for IP or user
     */
    public function clear(Request $request): JsonResponse
    {
        $ip = $request->get('ip');
        $userId = $request->get('user_id');
        $type = $request->get('type', 'all');

        $cleared = [];

        if ($ip && ($type === 'all' || $type === 'ip')) {
            $this->clearIpRateLimits($ip);
            $cleared[] = "IP: {$ip}";
        }

        if ($userId && ($type === 'all' || $type === 'user')) {
            $this->clearUserRateLimits($userId);
            $cleared[] = "User: {$userId}";
        }

        return response()->json([
            'success' => true,
            'message' => 'Rate limits cleared for: ' . implode(', ', $cleared),
            'cleared' => $cleared,
        ]);
    }

    /**
     * Get blocked IPs list
     */
    public function blockedIps(): JsonResponse
    {
        $pattern = 'rate_limit:ip:*';
        $keys = $this->getCacheKeys($pattern);

        $blocked = [];
        foreach ($keys as $key) {
            $requests = Cache::get($key, []);
            if (count($requests) >= 50) { // Threshold for "blocked" status
                preg_match('/rate_limit:ip:([^:]+):(.+)/', $key, $matches);
                if (isset($matches[2])) {
                    $blocked[] = [
                        'ip' => $matches[2],
                        'type' => $matches[1],
                        'count' => count($requests),
                        'expires_in' => $this->getCacheExpiry($key),
                    ];
                }
            }
        }

        return response()->json($blocked);
    }

    /**
     * Get rate limiting statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_rate_limited_requests' => 0,
            'blocked_ips_count' => 0,
            'top_rate_limited_endpoints' => [],
            'hourly_stats' => $this->getHourlyStats(),
        ];

        // Count blocked IPs
        $pattern = 'rate_limit:ip:*';
        $keys = $this->getCacheKeys($pattern);

        $endpointStats = [];
        foreach ($keys as $key) {
            $requests = Cache::get($key, []);
            $stats['total_rate_limited_requests'] += count($requests);

            if (count($requests) >= 30) {
                $stats['blocked_ips_count']++;
            }

            // Extract endpoint info
            preg_match('/rate_limit:ip:([^:]+):/', $key, $matches);
            if (isset($matches[1])) {
                $endpoint = $matches[1];
                $endpointStats[$endpoint] = ($endpointStats[$endpoint] ?? 0) + count($requests);
            }
        }

        // Sort top endpoints
        arsort($endpointStats);
        $stats['top_rate_limited_endpoints'] = array_slice($endpointStats, 0, 10, true);

        return response()->json($stats);
    }

    /**
     * Clear IP rate limits
     */
    protected function clearIpRateLimits(string $ip): void
    {
        $types = array_keys(RateLimitingMiddleware::LIMITS);

        foreach ($types as $type) {
            $key = "rate_limit:ip:{$type}:{$ip}";
            Cache::forget($key);
        }

        // Also clear endpoint-specific limits
        $endpointKeys = $this->getCacheKeys("rate_limit:endpoint:*:{$ip}");
        foreach ($endpointKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clear user rate limits
     */
    protected function clearUserRateLimits(string $userId): void
    {
        $types = array_keys(RateLimitingMiddleware::LIMITS);

        foreach ($types as $type) {
            $key = "rate_limit:user:{$type}:{$userId}";
            Cache::forget($key);
        }
    }

    /**
     * Get cache keys matching pattern (simplified implementation)
     */
    protected function getCacheKeys(string $pattern): array
    {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN or similar
        $keys = [];

        // For demonstration, we'll check common patterns
        $types = array_keys(RateLimitingMiddleware::LIMITS);

        if (str_contains($pattern, 'rate_limit:ip:')) {
            foreach ($types as $type) {
                // Check a reasonable range of IPs (this is not efficient for production)
                for ($i = 1; $i <= 254; $i++) {
                    $testKey = str_replace('*', $type, str_replace('*', "192.168.1.{$i}", $pattern));
                    if (Cache::has($testKey)) {
                        $keys[] = $testKey;
                    }
                }
            }
        }

        return $keys;
    }

    /**
     * Get cache expiry time
     */
    protected function getCacheExpiry(string $key): int
    {
        // This would need to be implemented based on your cache driver
        // For now, return a default value
        return 60; // seconds
    }

    /**
     * Get hourly rate limiting stats
     */
    protected function getHourlyStats(): array
    {
        $stats = [];
        $currentHour = now()->startOfHour();

        for ($i = 0; $i < 24; $i++) {
            $hour = $currentHour->copy()->subHours($i);
            $stats[] = [
                'hour' => $hour->format('H:00'),
                'date' => $hour->format('Y-m-d'),
                'requests' => $this->getHourlyRequestCount($hour),
                'blocked' => $this->getHourlyBlockedCount($hour),
            ];
        }

        return array_reverse($stats);
    }

    /**
     * Get request count for specific hour
     */
    protected function getHourlyRequestCount(\Carbon\Carbon $hour): int
    {
        // This is a simplified implementation
        // In production, you'd want to track this in a time-series database
        return rand(0, 1000); // Mock data
    }

    /**
     * Get blocked request count for specific hour
     */
    protected function getHourlyBlockedCount(\Carbon\Carbon $hour): int
    {
        // This is a simplified implementation
        // In production, you'd want to track this in a time-series database
        return rand(0, 50); // Mock data
    }
}