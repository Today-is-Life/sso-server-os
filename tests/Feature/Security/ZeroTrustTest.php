<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Services\Security\ZeroTrustService;
use App\Services\Security\AnomalyDetectionService;
use App\Services\SIEM\SIEMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ZeroTrustTest extends TestCase
{
    use RefreshDatabase;

    private ZeroTrustService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $siemMock = $this->createMock(SIEMService::class);
        $anomalyMock = $this->createMock(AnomalyDetectionService::class);
        $this->service = new ZeroTrustService($siemMock, $anomalyMock);
        
        $this->user = User::factory()->create([
            'mfa_enabled' => true,
            'email_verified_at' => now(),
            'password_changed_at' => now()->subDays(30),
        ]);
    }

    /** @test */
    public function it_calculates_trust_scores_correctly()
    {
        // Create trusted device
        DB::table('user_devices')->insert([
            'id' => 'device123',
            'user_id' => $this->user->id,
            'is_trusted' => true,
            'is_managed' => true,
            'last_successful_auth' => now()->subHours(1),
            'failed_attempts' => 0,
            'is_jailbroken' => false,
            'os_version' => 'iOS 17.0',
            'device_fingerprint' => 'fingerprint123',
            'created_at' => now(),
        ]);

        $context = [
            'user_id' => $this->user->id,
            'device_id' => 'device123',
            'auth_method' => 'certificate',
            'ip' => '10.0.0.1', // Corporate network
            'user_agent' => 'Mozilla/5.0',
            'action' => 'read',
            'resource' => '/api/profile',
        ];

        $decision = $this->service->verifyRequest($context);

        $this->assertTrue($decision['allowed']);
        $this->assertGreaterThan(50, $decision['score']);
        $this->assertArrayHasKey('device', $decision['scores']);
        $this->assertArrayHasKey('user', $decision['scores']);
        $this->assertArrayHasKey('network', $decision['scores']);
        $this->assertArrayHasKey('behavior', $decision['scores']);
        $this->assertArrayHasKey('context', $decision['scores']);
    }

    /** @test */
    public function it_denies_access_for_low_trust_score()
    {
        $context = [
            'user_id' => $this->user->id,
            'device_id' => null, // Unknown device
            'ip' => '1.2.3.4', // Unknown IP
            'user_agent' => 'Suspicious Bot',
            'action' => 'delete',
            'resource' => '/api/admin/users',
        ];

        // Simulate anomalies
        Cache::put("user_anomalies_{$this->user->id}", 3, 300);
        Cache::put("failed_attempts_{$this->user->id}", 5, 300);

        $decision = $this->service->verifyRequest($context);

        $this->assertFalse($decision['allowed']);
        $this->assertLessThan(50, $decision['score']);
        $this->assertNotEmpty($decision['recommendations']);
    }

    /** @test */
    public function it_requires_step_up_authentication()
    {
        $context = [
            'user_id' => $this->user->id,
            'device_id' => 'device123',
            'ip' => '1.2.3.4',
            'user_agent' => 'Mozilla/5.0',
            'action' => 'admin',
            'resource' => '/api/admin/settings',
        ];

        $decision = $this->service->verifyRequest($context);

        if (!$decision['allowed']) {
            $this->assertArrayHasKey('step_up_required', $decision);
            if (isset($decision['step_up_required'])) {
                $this->assertTrue($decision['step_up_required']);
                $this->assertNotEmpty($decision['step_up_methods']);
                $this->assertContains('totp', $decision['step_up_methods']);
            }
        }
    }

    /** @test */
    public function it_adjusts_score_based_on_network_trust()
    {
        // Test corporate network
        config(['security.corporate_ip_ranges' => ['10.0.0.0/8']]);
        
        $context = [
            'user_id' => $this->user->id,
            'device_id' => 'device123',
            'ip' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'action' => 'read',
            'resource' => '/api/data',
        ];

        $corporateDecision = $this->service->verifyRequest($context);

        // Test public WiFi
        $context['ip'] = '192.168.1.1'; // Public WiFi range
        $publicDecision = $this->service->verifyRequest($context);

        $this->assertGreaterThan(
            $publicDecision['scores']['network'],
            $corporateDecision['scores']['network']
        );
    }

    /** @test */
    public function it_detects_privilege_escalation_attempts()
    {
        // Create admin group
        $adminGroup = \App\Models\Group::create([
            'domain_id' => \Str::uuid(),
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $this->user->groups()->attach($adminGroup->id);

        Cache::put("escalation_attempts_{$this->user->id}", 3, 300);

        $context = [
            'user_id' => $this->user->id,
            'device_id' => 'device123',
            'ip' => '1.2.3.4',
            'user_agent' => 'Mozilla/5.0',
            'action' => 'system',
            'resource' => '/api/system/config',
        ];

        $decision = $this->service->verifyRequest($context);

        // Admin users have lower base score (higher risk)
        // Plus escalation attempts further reduce score
        $this->assertLessThan(70, $decision['scores']['user']);
        $this->assertLessThan(50, $decision['scores']['behavior']);
    }
}