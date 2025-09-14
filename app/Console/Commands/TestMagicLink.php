<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestMagicLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:magic-link';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test magic link authentication functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing magic link functionality...');

        try {
            // Get first verified user
            $user = User::whereNotNull('email_verified_at')->first();

            if (!$user) {
                $this->error('No verified users found');
                return;
            }

            $this->info('Found verified user: ' . $user->name . ' (ID: ' . $user->id . ')');

            // Generate magic link token
            $token = Str::random(64);
            $hashedToken = hash('sha256', $token);

            // Store magic link in database
            $magicLinkId = Str::uuid();
            DB::table('magic_links')->insert([
                'id' => $magicLinkId,
                'email' => $user->email,
                'email_hash' => $user->email_hash,
                'token_hash' => $hashedToken,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test User Agent',
                'redirect_to' => null,
                'metadata' => json_encode([]),
                'expires_at' => now()->addMinutes(10), // 10 minutes expiry
                'created_at' => now(),
            ]);

            $this->info('Magic link created with token: ' . $token);

            // Generate magic URL (simulated)
            $magicUrl = url('/auth/magic/' . $token);
            $this->info('Magic URL: ' . $magicUrl);

            // Test the verification process
            $this->info('Testing magic link verification...');

            // Find and validate magic link
            $magicLink = DB::table('magic_links')
                ->where('token_hash', $hashedToken)
                ->where('expires_at', '>', now())
                ->whereNull('used_at')
                ->first();

            if (!$magicLink) {
                $this->error('Magic link validation failed');
                return;
            }

            $this->info('Magic link validation successful');

            // Mark magic link as used (simulated)
            DB::table('magic_links')
                ->where('id', $magicLink->id)
                ->update(['used_at' => now()]);

            $this->info('Magic link marked as used');

            // Verify user can be found
            $testUser = User::where('email_hash', $magicLink->email_hash)->first();
            if (!$testUser) {
                $this->error('User lookup failed');
                return;
            }

            $this->info('User lookup successful: ' . $testUser->name);
            $this->info('Magic link authentication test completed successfully!');

        } catch (\Exception $e) {
            $this->error('Magic link test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
