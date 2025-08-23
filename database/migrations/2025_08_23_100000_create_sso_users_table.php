<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the default users table
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        
        // Create SSO users table with UUID and encryption fields
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('email'); // Encrypted
            $table->string('email_hash')->unique(); // For lookups
            $table->timestamp('email_verified_at')->nullable();
            $table->text('phone')->nullable(); // Encrypted
            $table->string('phone_hash')->nullable()->index(); // For lookups
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->text('mfa_secret')->nullable(); // Encrypted
            $table->boolean('mfa_enabled')->default(false);
            $table->json('mfa_recovery_codes')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('locale')->default('de');
            $table->string('timezone')->default('Europe/Berlin');
            $table->json('preferences')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('deleted_at');
        });

        // Create password reset tokens table
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Create sessions table for UUID users
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id', 36)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        
        // Create user_sessions table for SSO sessions
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash')->unique();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('token_hash');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};