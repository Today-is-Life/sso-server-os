<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class Test2FA extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:2fa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test 2FA (Two-Factor Authentication) functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing 2FA functionality...');

        try {
            // Get first verified user
            $user = User::whereNotNull('email_verified_at')->first();

            if (!$user) {
                $this->error('No verified users found');
                return;
            }

            $this->info('Found verified user: ' . $user->name . ' (ID: ' . $user->id . ')');

            // Test 2FA secret generation
            $this->info('Testing 2FA secret generation...');
            $secret = $user->generate2FASecret();
            $this->info('2FA secret generated: ' . $secret);

            // Test QR code URL generation
            $qrUrl = $user->get2FAQRCodeUrl();
            $this->info('QR Code URL: ' . $qrUrl);

            // Test provisioning URI
            $provisioningUri = $user->get2FAProvisioningUri();
            $this->info('Provisioning URI: ' . $provisioningUri);

            // Generate a TOTP token for testing
            $totp = \OTPHP\TOTP::create($secret);
            $currentToken = $totp->now();
            $this->info('Current TOTP token: ' . $currentToken);

            // Test token verification before enabling 2FA
            $this->info('Testing token verification (before enabling)...');
            $verifyResult = $user->verify2FAToken($currentToken);
            $this->info('Token verification result (before enabling): ' . ($verifyResult ? 'Valid' : 'Invalid'));

            // Test enabling 2FA
            $this->info('Testing 2FA enablement...');
            $enableResult = $user->enable2FA($currentToken);
            $this->info('2FA enable result: ' . ($enableResult ? 'Success' : 'Failed'));

            if ($enableResult) {
                $this->info('2FA is now enabled for user');
                $this->info('MFA enabled status: ' . ($user->mfa_enabled ? 'true' : 'false'));

                // Test token verification after enabling 2FA
                $this->info('Testing token verification (after enabling)...');

                // Generate a new token for testing
                sleep(1); // Wait for a new time window
                $newToken = $totp->now();
                $this->info('New TOTP token: ' . $newToken);

                $verifyResultAfter = $user->verify2FAToken($newToken);
                $this->info('Token verification result (after enabling): ' . ($verifyResultAfter ? 'Valid' : 'Invalid'));

                // Test recovery codes generation
                $this->info('Testing recovery codes generation...');
                $recoveryCodes = $user->generateRecoveryCodes();
                $this->info('Recovery codes generated: ' . count($recoveryCodes) . ' codes');
                $this->info('First recovery code: ' . $recoveryCodes[0]);

                // Test recovery code verification
                $this->info('Testing recovery code verification...');
                $recoveryResult = $user->useRecoveryCode($recoveryCodes[0]);
                $this->info('Recovery code verification: ' . ($recoveryResult ? 'Valid' : 'Invalid'));

                // Test disabling 2FA
                $this->info('Testing 2FA disablement...');
                $disableResult = $user->disable2FA();
                $this->info('2FA disable result: ' . ($disableResult ? 'Success' : 'Failed'));
                $this->info('MFA enabled status after disable: ' . ($user->mfa_enabled ? 'true' : 'false'));
            }

            $this->info('2FA test completed successfully!');

        } catch (\Exception $e) {
            $this->error('2FA test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
