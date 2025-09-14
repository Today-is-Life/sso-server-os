<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestSocialLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:social-login';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test social login functionality';

    /**
     * Supported social providers
     */
    private const SUPPORTED_PROVIDERS = [
        'google',
        'github',
        'facebook',
        'instagram',
        'linkedin',
        'twitter',
        'microsoft',
        'apple'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing social login functionality...');

        try {
            // Get or create a test user
            $user = User::whereNotNull('email_verified_at')->first();

            if (!$user) {
                $this->error('No verified users found. Creating test user...');

                $email = 'socialtest@example.com';
                $emailHash = hash('sha256', strtolower($email));

                $user = new User();
                $user->id = (string) Str::uuid();
                $user->name = 'Social Test User';
                $user->email = $email;
                $user->setAttribute('email_hash', $emailHash);
                $user->password = \Illuminate\Support\Facades\Hash::make('TestPassword123!');
                $user->email_verified_at = now();
                $user->setAttribute('locale', 'de');
                $user->setAttribute('timezone', 'Europe/Berlin');
                $user->save();

                $this->info('Test user created: ' . $user->name);
            }

            $this->info('Testing with user: ' . $user->name . ' (ID: ' . $user->id . ')');

            // Test each social provider
            foreach (self::SUPPORTED_PROVIDERS as $provider) {
                $this->info("\nTesting {$provider} integration...");

                // Simulate social account data
                $socialId = 'test_' . $provider . '_' . Str::random(10);
                $socialEmail = 'test_' . $provider . '@example.com';
                $socialName = 'Test User from ' . ucfirst($provider);

                // Check if social account already exists
                $existingAccount = DB::table('social_accounts')
                    ->where('provider', $provider)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existingAccount) {
                    $this->info("  ✓ Social account already exists for {$provider}");
                    continue;
                }

                // Create social account
                DB::table('social_accounts')->insert([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_user_id' => $socialId,
                    'name' => $socialName,
                    'email' => $socialEmail,
                    'avatar' => 'https://example.com/avatar_' . $provider . '.jpg',
                    'access_token' => Str::random(60),
                    'refresh_token' => $provider === 'google' || $provider === 'microsoft' ? Str::random(60) : null,
                    'token_expires_at' => now()->addDays(30),
                    'raw_data' => json_encode(['test_data' => 'from_' . $provider]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->info("  ✓ Social account created for {$provider}");
                $this->info("    - Provider ID: {$socialId}");
                $this->info("    - Email: {$socialEmail}");

                // Test finding user by social account
                $foundAccount = DB::table('social_accounts')
                    ->where('provider', $provider)
                    ->where('provider_user_id', $socialId)
                    ->first();

                if ($foundAccount) {
                    $this->info("  ✓ Social account lookup successful");

                    // Find associated user
                    $foundUser = User::find($foundAccount->user_id);
                    if ($foundUser) {
                        $this->info("  ✓ User association verified: " . $foundUser->name);
                    } else {
                        $this->error("  ✗ User association failed");
                    }
                } else {
                    $this->error("  ✗ Social account lookup failed");
                }
            }

            // Test provider configuration check
            $this->info("\nTesting provider configuration checks...");
            foreach (self::SUPPORTED_PROVIDERS as $provider) {
                $clientIdKey = strtoupper($provider) . '_CLIENT_ID';
                $clientSecretKey = strtoupper($provider) . '_CLIENT_SECRET';

                $hasConfig = !empty(config("services.{$provider}.client_id")) ||
                            !empty(env($clientIdKey));

                $this->info("  {$provider}: " . ($hasConfig ? '✓ Configured' : '✗ Not configured'));
            }

            // Count total social accounts
            $totalAccounts = DB::table('social_accounts')
                ->where('user_id', $user->id)
                ->count();

            $this->info("\nTotal social accounts for user: {$totalAccounts}");

            $this->info("\nSocial login test completed successfully!");

        } catch (\Exception $e) {
            $this->error('Social login test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
