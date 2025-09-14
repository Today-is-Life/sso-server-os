<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SiemService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestSiemLogging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:siem';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SIEM security event logging functionality';

    protected SiemService $siemService;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing SIEM security event logging...');

        try {
            // Create SIEM service instance
            $this->siemService = app(SiemService::class);
            $this->info('✓ SIEM service instantiated successfully');

            // Get test user
            $user = User::first();
            if (!$user) {
                $this->error('No users found for testing');
                return;
            }
            $this->info('Testing with user: ' . $user->name . ' (ID: ' . $user->id . ')');

            // Create mock request
            $request = Request::create('/test', 'POST', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.100',
                'HTTP_USER_AGENT' => 'TestBot/1.0'
            ]);

            // Test different event types
            $this->info("\nTesting event logging...");

            // 1. Login Success
            $event = $this->siemService->logEvent(
                SiemService::EVENT_LOGIN_SUCCESS,
                SiemService::LEVEL_INFO,
                $user->id,
                $request,
                ['login_method' => 'password']
            );
            $this->info("  ✓ Login success event logged (ID: {$event->id})");

            // 2. Login Failure
            $event = $this->siemService->logEvent(
                SiemService::EVENT_LOGIN_FAILURE,
                SiemService::LEVEL_WARNING,
                null,
                $request,
                ['email' => 'test@example.com', 'reason' => 'invalid_password']
            );
            $this->info("  ✓ Login failure event logged (ID: {$event->id})");

            // 3. Brute Force Detection
            $event = $this->siemService->logEvent(
                SiemService::EVENT_BRUTE_FORCE,
                SiemService::LEVEL_CRITICAL,
                $user->id,
                $request,
                ['attempt_count' => 10]
            );
            $this->info("  ✓ Brute force event logged (ID: {$event->id})");

            // 4. Account Creation
            $event = $this->siemService->logEvent(
                SiemService::EVENT_ACCOUNT_CREATED,
                SiemService::LEVEL_INFO,
                $user->id,
                $request,
                ['creation_method' => 'email_registration']
            );
            $this->info("  ✓ Account creation event logged (ID: {$event->id})");

            // 5. Permission Change
            $event = $this->siemService->logEvent(
                SiemService::EVENT_PERMISSION_CHANGE,
                SiemService::LEVEL_WARNING,
                $user->id,
                $request,
                ['permission' => 'admin.access', 'action' => 'granted']
            );
            $this->info("  ✓ Permission change event logged (ID: {$event->id})");

            // 6. Suspicious Activity
            $event = $this->siemService->logEvent(
                SiemService::EVENT_SUSPICIOUS_ACTIVITY,
                SiemService::LEVEL_ERROR,
                $user->id,
                $request,
                ['reason' => 'sql_injection_attempt', 'payload' => "'; DROP TABLE users;--"]
            );
            $this->info("  ✓ Suspicious activity event logged (ID: {$event->id})");

            // Test anomaly detection
            $this->info("\nTesting anomaly detection...");

            // Test impossible travel detection
            $anomaly = $this->siemService->detectImpossibleTravel(
                $user->id,
                '192.168.1.1',
                'New York',
                '10.0.0.1',
                'Tokyo'
            );
            $this->info("  ✓ Impossible travel detection: " . ($anomaly ? 'DETECTED' : 'Not detected'));

            // Test new device detection
            $isNewDevice = $this->siemService->isNewDevice(
                $user->id,
                'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X)'
            );
            $this->info("  ✓ New device detection: " . ($isNewDevice ? 'NEW DEVICE' : 'Known device'));

            // Test brute force detection
            $isBruteForce = $this->siemService->detectBruteForce(
                '192.168.1.100'
            );
            $this->info("  ✓ Brute force detection: " . ($isBruteForce ? 'DETECTED' : 'Not detected'));

            // Test retrieving recent events
            $this->info("\nTesting event retrieval...");

            $recentEvents = $this->siemService->getRecentEvents(5);
            $this->info("  ✓ Recent events retrieved: " . count($recentEvents) . " events");

            // Test event statistics
            $this->info("\nTesting event statistics...");

            // Count by severity
            $criticalCount = DB::table('security_events')
                ->where('severity', 'critical')
                ->count();
            $this->info("  ✓ Critical events: {$criticalCount}");

            $errorCount = DB::table('security_events')
                ->where('severity', 'error')
                ->count();
            $this->info("  ✓ Error events: {$errorCount}");

            $warningCount = DB::table('security_events')
                ->where('severity', 'warning')
                ->count();
            $this->info("  ✓ Warning events: {$warningCount}");

            $infoCount = DB::table('security_events')
                ->where('severity', 'info')
                ->count();
            $this->info("  ✓ Info events: {$infoCount}");

            // Total events
            $totalEvents = DB::table('security_events')->count();
            $this->info("  ✓ Total security events: {$totalEvents}");

            // Test correlation IDs
            $this->info("\nTesting event correlation...");
            $correlationId = \Illuminate\Support\Str::uuid();

            $event1 = $this->siemService->logEvent(
                SiemService::EVENT_LOGIN_SUCCESS,
                SiemService::LEVEL_INFO,
                $user->id,
                $request,
                ['step' => 1],
                $correlationId
            );

            $event2 = $this->siemService->logEvent(
                SiemService::EVENT_PERMISSION_CHANGE,
                SiemService::LEVEL_WARNING,
                $user->id,
                $request,
                ['step' => 2],
                $correlationId
            );

            $correlatedEvents = DB::table('security_events')
                ->where('correlation_id', $correlationId)
                ->count();

            $this->info("  ✓ Correlated events created: {$correlatedEvents} events with same correlation ID");

            $this->info("\nSIEM logging test completed successfully!");

        } catch (\Exception $e) {
            $this->error('SIEM logging test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
