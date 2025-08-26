<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Services\Security\AnomalyDetectionService;
use App\Services\SIEM\SIEMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AnomalyDetectionTest extends TestCase
{
    use RefreshDatabase;

    private AnomalyDetectionService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock SIEM service
        $siemMock = $this->createMock(SIEMService::class);
        $this->service = new AnomalyDetectionService($siemMock);
        
        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'mfa_enabled' => true,
        ]);
    }

    /** @test */
    public function it_detects_impossible_travel()
    {
        // First login from New York
        Cache::put("last_login_location_{$this->user->id}", [
            'ip' => '1.2.3.4',
            'location' => [
                'country' => 'USA',
                'city' => 'New York',
                'lat' => 40.7128,
                'lon' => -74.0060,
            ],
            'timestamp' => time() - 1800, // 30 minutes ago
        ], 86400);

        // Mock IP lookup for Tokyo
        Http::fake([
            'ip-api.com/*' => Http::response([
                'country' => 'Japan',
                'city' => 'Tokyo',
                'lat' => 35.6762,
                'lon' => 139.6503,
                'isp' => 'Example ISP',
            ], 200),
        ]);

        // Second login from Tokyo (impossible in 30 minutes)
        $context = [
            'ip' => '5.6.7.8',
            'user_agent' => 'Mozilla/5.0',
            'device_id' => 'device123',
        ];

        $anomalies = $this->service->detectLoginAnomalies($this->user, $context);

        $this->assertNotEmpty($anomalies);
        $this->assertEquals('impossible_travel', $anomalies[0]['type']);
        $this->assertEquals('critical', $anomalies[0]['severity']);
    }

    /** @test */
    public function it_detects_unusual_login_time()
    {
        // Create login history during business hours
        for ($i = 0; $i < 20; $i++) {
            DB::table('user_sessions')->insert([
                'id' => \Str::uuid(),
                'user_id' => $this->user->id,
                'created_at' => now()->setHour(rand(9, 17)),
            ]);
        }

        // Try login at 3 AM
        $context = [
            'ip' => '1.2.3.4',
            'user_agent' => 'Mozilla/5.0',
            'timezone' => 'UTC',
        ];

        // Mock the current hour to be 3 AM
        $this->travelTo(now()->setHour(3));

        $anomalies = $this->service->detectLoginAnomalies($this->user, $context);

        $unusualTime = collect($anomalies)->firstWhere('type', 'unusual_time');
        $this->assertNotNull($unusualTime);
        $this->assertEquals('warning', $unusualTime['severity']);
    }

    /** @test */
    public function it_detects_new_device()
    {
        $context = [
            'ip' => '1.2.3.4',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
            'device_id' => 'new_device_123',
            'accept_language' => 'en-US',
            'screen_resolution' => '1920x1080',
        ];

        $anomalies = $this->service->detectLoginAnomalies($this->user, $context);

        $newDevice = collect($anomalies)->firstWhere('type', 'new_device');
        $this->assertNotNull($newDevice);
        $this->assertEquals('warning', $newDevice['severity']);
    }

    /** @test */
    public function it_detects_brute_force_attempts()
    {
        // Simulate failed login attempts
        Cache::put("failed_login_{$this->user->id}", 10, 300);
        Cache::put("failed_login_ip_1.2.3.4", 15, 300);

        $context = [
            'ip' => '1.2.3.4',
            'user_agent' => 'Mozilla/5.0',
        ];

        $anomalies = $this->service->detectLoginAnomalies($this->user, $context);

        $bruteForce = collect($anomalies)->firstWhere('type', 'brute_force');
        $this->assertNotNull($bruteForce);
        $this->assertEquals('critical', $bruteForce['severity']);
        $this->assertTrue($bruteForce['details']['lockout_recommended']);
    }

    /** @test */
    public function it_detects_concurrent_sessions_from_different_countries()
    {
        // Create active session from USA
        DB::table('user_sessions')->insert([
            'id' => \Str::uuid(),
            'user_id' => $this->user->id,
            'ip_address' => '1.2.3.4',
            'expires_at' => now()->addHours(2),
            'created_at' => now()->subMinutes(30),
        ]);

        // Mock different geo locations
        Http::fake([
            'ip-api.com/json/1.2.3.4' => Http::response([
                'country' => 'USA',
                'city' => 'New York',
            ], 200),
            'ip-api.com/json/5.6.7.8' => Http::response([
                'country' => 'Germany',
                'city' => 'Berlin',
            ], 200),
        ]);

        // Try login from Germany
        $context = [
            'ip' => '5.6.7.8',
            'user_agent' => 'Mozilla/5.0',
        ];

        $anomalies = $this->service->detectLoginAnomalies($this->user, $context);

        $concurrent = collect($anomalies)->firstWhere('type', 'concurrent_sessions');
        $this->assertNotNull($concurrent);
        $this->assertEquals('critical', $concurrent['severity']);
    }
}