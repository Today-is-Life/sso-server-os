<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:registration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test user registration functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing user registration functionality...');

        // Test data
        $email = 'test' . time() . '@example.com';
        $emailHash = hash('sha256', strtolower($email));

        try {
            // Check if user already exists
            if (User::where('email_hash', $emailHash)->exists()) {
                $this->error('User already exists with this email hash');
                return;
            }

            // Create user
            $user = new User();
            $user->id = (string) Str::uuid();
            $user->name = 'Test User';
            $user->email = $email; // Will be encrypted by mutator
            $user->setAttribute('email_hash', $emailHash);
            $user->password = Hash::make('TestPassword123!');
            $user->setAttribute('locale', 'de');
            $user->setAttribute('timezone', 'Europe/Berlin');
            $user->save();

            $this->info('User created successfully!');
            $this->info('User ID: ' . $user->id);
            $this->info('Email Hash: ' . $emailHash);
            $this->info('Registration test completed successfully!');

        } catch (\Exception $e) {
            $this->error('Registration test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
