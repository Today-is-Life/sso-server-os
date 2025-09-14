<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestEmailVerification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email-verification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email verification functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing email verification functionality...');

        try {
            // Get first unverified user
            $user = User::whereNull('email_verified_at')->first();

            if (!$user) {
                $this->error('No unverified users found');
                return;
            }

            $this->info('Found unverified user: ' . $user->name . ' (ID: ' . $user->id . ')');

            // Generate verification token
            $token = Str::random(60);

            // Delete any existing tokens for this user
            DB::table('password_reset_tokens')
                ->where('email', $user->email_hash)
                ->delete();

            // Insert verification token
            DB::table('password_reset_tokens')->insert([
                'email' => $user->email_hash,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            $this->info('Verification token created: ' . $token);

            // Generate verification URL (simulated)
            $verificationUrl = url('/auth/verify-email/' . urlencode($token) . '?email=' . urlencode($user->email_hash));
            $this->info('Verification URL: ' . $verificationUrl);

            // Test the verification process
            $this->info('Testing verification process...');

            // Find the verification token
            $verificationToken = DB::table('password_reset_tokens')
                ->where('email', $user->email_hash)
                ->where('created_at', '>', now()->subHours(24)) // Token valid for 24 hours
                ->first();

            if (!$verificationToken || !Hash::check($token, $verificationToken->token)) {
                $this->error('Verification token validation failed');
                return;
            }

            $this->info('Token validation successful');

            // Mark email as verified
            $user->email_verified_at = now();
            $user->save();

            // Delete verification token
            DB::table('password_reset_tokens')
                ->where('email', $user->email_hash)
                ->delete();

            $this->info('Email verification completed successfully!');
            $this->info('User email_verified_at: ' . $user->email_verified_at);

        } catch (\Exception $e) {
            $this->error('Email verification test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
