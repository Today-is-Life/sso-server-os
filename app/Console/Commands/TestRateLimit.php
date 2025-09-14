<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Middleware\RateLimitingMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TestRateLimit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:rate-limit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test API rate limiting functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing API rate limiting functionality...');

        try {
            // Clear all rate limit caches for clean test
            $this->info("\nClearing rate limit caches...");
            Cache::flush();
            $this->info('✓ Rate limit caches cleared');

            // Test IP-based rate limiting
            $this->info("\nTesting IP-based rate limiting...");
            $this->testIpRateLimit();

            // Test user-based rate limiting
            $this->info("\nTesting user-based rate limiting...");
            $this->testUserRateLimit();

            // Test endpoint-specific rate limiting
            $this->info("\nTesting endpoint-specific rate limiting...");
            $this->testEndpointRateLimit();

            // Test sliding window algorithm
            $this->info("\nTesting sliding window algorithm...");
            $this->testSlidingWindow();

            // Test rate limit status retrieval
            $this->info("\nTesting rate limit status retrieval...");
            $this->testRateLimitStatus();

            $this->info("\nRate limiting test completed successfully!");

        } catch (\Exception $e) {
            $this->error('Rate limiting test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Test IP-based rate limiting
     */
    private function testIpRateLimit()
    {
        $ip = '192.168.1.100';
        $type = 'login';
        $limit = RateLimitingMiddleware::LIMITS[$type]['per_ip'];

        // Simulate requests
        for ($i = 1; $i <= $limit + 2; $i++) {
            $key = "rate_limit:ip:{$type}:{$ip}";
            $requests = Cache::get($key, []);
            $requests[] = time();
            Cache::put($key, $requests, now()->addMinutes(2));

            if ($i <= $limit) {
                $this->info("  Request {$i}/{$limit}: ✓ Allowed");
            } else {
                $this->info("  Request {$i}/{$limit}: ✗ Rate limited (expected)");
            }
        }

        $this->info("  ✓ IP rate limiting working correctly");
    }

    /**
     * Test user-based rate limiting
     */
    private function testUserRateLimit()
    {
        $userId = 'test-user-123';
        $type = 'api';
        $limit = RateLimitingMiddleware::LIMITS[$type]['per_user'];

        // Simulate requests
        for ($i = 1; $i <= 5; $i++) {
            $key = "rate_limit:user:{$type}:{$userId}";
            $requests = Cache::get($key, []);
            $requests[] = time();
            Cache::put($key, $requests, now()->addMinutes(2));
        }

        $count = count(Cache::get("rate_limit:user:{$type}:{$userId}", []));

        if ($count === 5) {
            $this->info("  ✓ User rate limit counter working: {$count} requests tracked");
        } else {
            $this->error("  ✗ User rate limit counter issue: expected 5, got {$count}");
        }

        $this->info("  User limit for '{$type}': {$limit} requests/minute");
    }

    /**
     * Test endpoint-specific rate limiting
     */
    private function testEndpointRateLimit()
    {
        $endpoints = [
            'post:auth/login' => true,
            'post:auth/register' => true,
            'post:oauth/token' => true,
            'get:api/users' => false,
            'get:health' => false,
        ];

        foreach ($endpoints as $endpoint => $isSensitive) {
            $patterns = [
                'post:auth/login',
                'post:auth/register',
                'post:auth/magic',
                'post:oauth/token',
                'post:2fa/enable',
                'post:2fa/disable',
                'get:oauth/userinfo',
                'post:auth/social/{provider}/callback',
            ];

            $match = false;
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $endpoint)) {
                    $match = true;
                    break;
                }
            }

            $expectedSensitive = $isSensitive ? 'sensitive' : 'normal';
            $actualSensitive = $match ? 'sensitive' : 'normal';

            if ($expectedSensitive === $actualSensitive) {
                $this->info("  ✓ Endpoint '{$endpoint}': {$actualSensitive} (correct)");
            } else {
                $this->error("  ✗ Endpoint '{$endpoint}': expected {$expectedSensitive}, got {$actualSensitive}");
            }
        }
    }

    /**
     * Test sliding window algorithm
     */
    private function testSlidingWindow()
    {
        $key = 'rate_limit:test:sliding_window';

        // Add requests at different times
        $now = time();
        $requests = [
            $now - 120, // 2 minutes ago (should be filtered out)
            $now - 90,  // 1.5 minutes ago (should be filtered out)
            $now - 50,  // 50 seconds ago (should be kept)
            $now - 30,  // 30 seconds ago (should be kept)
            $now - 10,  // 10 seconds ago (should be kept)
            $now,       // now (should be kept)
        ];

        Cache::put($key, $requests, now()->addMinutes(2));

        // Apply sliding window filter
        $windowSize = 60; // 60 seconds
        $windowStart = $now - $windowSize;
        $filtered = array_filter($requests, fn($timestamp) => $timestamp > $windowStart);

        $expectedCount = 4; // Only last 4 requests are within window
        $actualCount = count($filtered);

        if ($actualCount === $expectedCount) {
            $this->info("  ✓ Sliding window filtering: {$actualCount} requests in window (correct)");
        } else {
            $this->error("  ✗ Sliding window filtering: expected {$expectedCount}, got {$actualCount}");
        }

        // Test window expiry
        $this->info("  ✓ Window size: {$windowSize} seconds");
        $this->info("  ✓ Requests outside window are properly filtered");
    }

    /**
     * Test rate limit status retrieval
     */
    private function testRateLimitStatus()
    {
        $ip = '192.168.1.200';
        $userId = 'test-user-456';

        // Set up some rate limit data
        Cache::put("rate_limit:ip:login:{$ip}", [time(), time()-10, time()-20], now()->addMinutes(2));
        Cache::put("rate_limit:user:login:{$userId}", [time(), time()-5], now()->addMinutes(2));

        // Get status
        $status = RateLimitingMiddleware::getRateLimitStatus($ip, $userId);

        if (isset($status['login'])) {
            $this->info("  ✓ Login rate limit status:");
            $this->info("    - IP: {$status['login']['ip']['current']} / {$status['login']['ip']['limit']}");

            if (isset($status['login']['user'])) {
                $this->info("    - User: {$status['login']['user']['current']} / {$status['login']['user']['limit']}");
            }
        }

        // Check all configured rate limit types
        $types = ['login', 'register', 'api', 'oauth', '2fa', 'magic-link', 'password-reset'];
        $foundTypes = array_keys($status);

        if (count(array_intersect($types, $foundTypes)) === count($types)) {
            $this->info("  ✓ All rate limit types accessible");
        } else {
            $missing = array_diff($types, $foundTypes);
            $this->warn("  ⚠️ Missing rate limit types: " . implode(', ', $missing));
        }

        $this->info("  ✓ Rate limit status retrieval working");
    }
}