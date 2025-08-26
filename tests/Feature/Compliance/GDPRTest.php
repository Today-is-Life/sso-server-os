<?php

namespace Tests\Feature\Compliance;

use Tests\TestCase;
use App\Models\User;
use App\Services\Compliance\GDPRService;
use App\Services\SIEM\SIEMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;

class GDPRTest extends TestCase
{
    use RefreshDatabase;

    private GDPRService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('local');
        Mail::fake();
        
        $siemMock = $this->createMock(SIEMService::class);
        $this->service = new GDPRService($siemMock);
        
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
        ]);

        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create audit logs
        DB::table('audit_logs')->insert([
            'id' => \Str::uuid(),
            'user_id' => $this->user->id,
            'action' => 'login',
            'ip_address' => '1.2.3.4',
            'created_at' => now(),
        ]);

        // Create device
        DB::table('user_devices')->insert([
            'id' => \Str::uuid(),
            'user_id' => $this->user->id,
            'device_name' => 'iPhone 15',
            'device_type' => 'mobile',
            'is_trusted' => true,
            'last_active_at' => now(),
            'created_at' => now(),
        ]);

        // Create session
        DB::table('user_sessions')->insert([
            'id' => \Str::uuid(),
            'user_id' => $this->user->id,
            'ip_address' => '1.2.3.4',
            'expires_at' => now()->addHours(2),
            'created_at' => now(),
        ]);
    }

    /** @test */
    public function it_exports_user_data_gdpr_article_15()
    {
        $filename = $this->service->exportUserData($this->user);

        // Check file was created
        Storage::assertExists("gdpr-exports/{$filename}");

        // Check email was sent
        Mail::assertSent(function ($mail) {
            return $mail->hasTo($this->user->email) &&
                   str_contains($mail->subject, 'Data Export');
        });

        // Verify exported data structure
        $encryptedContent = Storage::get("gdpr-exports/{$filename}");
        $jsonContent = Crypt::decryptString($encryptedContent);
        $data = json_decode($jsonContent, true);

        $this->assertArrayHasKey('export_date', $data);
        $this->assertArrayHasKey('user_id', $data);
        $this->assertArrayHasKey('personal_information', $data);
        $this->assertArrayHasKey('account_data', $data);
        $this->assertArrayHasKey('activity_logs', $data);
        $this->assertArrayHasKey('permissions', $data);
        $this->assertArrayHasKey('devices', $data);
        $this->assertArrayHasKey('sessions', $data);
        $this->assertArrayHasKey('data_processing', $data);

        // Verify personal information
        $this->assertEquals('Test User', $data['personal_information']['name']);
        $this->assertEquals('test@example.com', $data['personal_information']['email']);
    }

    /** @test */
    public function it_performs_soft_delete_gdpr_article_17()
    {
        $originalEmail = $this->user->email;
        
        $this->service->deleteUserData($this->user, false);

        $this->user->refresh();

        // Check anonymization
        $this->assertEquals('Deleted User', $this->user->name);
        $this->assertStringContainsString('deleted_', $this->user->email);
        $this->assertStringContainsString('@deleted.local', $this->user->email);
        $this->assertNull($this->user->phone);
        $this->assertNull($this->user->avatar_url);
        $this->assertNull($this->user->last_login_ip);
        $this->assertNotNull($this->user->deleted_at);

        // Check audit logs were anonymized
        $auditLog = DB::table('audit_logs')
            ->where('user_id', null)
            ->first();
        $this->assertNotNull($auditLog);
    }

    /** @test */
    public function it_performs_hard_delete_gdpr_article_17()
    {
        $userId = $this->user->id;
        
        $this->service->deleteUserData($this->user, true);

        // Check user is completely deleted
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseMissing('audit_logs', ['user_id' => $userId]);
        $this->assertDatabaseMissing('user_devices', ['user_id' => $userId]);
        $this->assertDatabaseMissing('user_sessions', ['user_id' => $userId]);
    }

    /** @test */
    public function it_restricts_data_processing_gdpr_article_18()
    {
        $restrictions = [
            'marketing' => false,
            'analytics' => false,
            'profiling' => false,
        ];

        $this->service->restrictProcessing($this->user, $restrictions);

        $this->user->refresh();

        $this->assertNotNull($this->user->data_restrictions);
        $this->assertArrayHasKey('marketing', $this->user->data_restrictions);
        $this->assertFalse($this->user->data_restrictions['marketing']);
    }

    /** @test */
    public function it_exports_portable_data_gdpr_article_20()
    {
        $filename = $this->service->exportPortableData($this->user);

        Storage::assertExists("gdpr-exports/{$filename}");

        $content = Storage::get("gdpr-exports/{$filename}");
        $data = json_decode($content, true);

        $this->assertEquals('json', $data['format']);
        $this->assertEquals('1.0', $data['version']);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('data', $data);

        // Verify it's machine-readable format
        $this->assertJson($content);
    }

    /** @test */
    public function it_handles_data_objections_gdpr_article_21()
    {
        $objections = [
            ['type' => 'marketing', 'reason' => 'I do not want marketing emails'],
            ['type' => 'analytics', 'reason' => 'Privacy concerns'],
        ];

        $this->service->handleObjection($this->user, $objections);

        // Check objections were recorded
        $this->assertDatabaseHas('data_objections', [
            'user_id' => $this->user->id,
            'processing_type' => 'marketing',
            'status' => 'pending',
        ]);

        $this->user->refresh();
        
        // Check consents were updated
        $this->assertFalse($this->user->marketing_consent);
        $this->assertFalse($this->user->analytics_consent);
    }
}