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
        Schema::create('magic_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('email');
            $table->string('email_hash')->index();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('domain_id')->nullable();
            $table->string('redirect_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            
            $table->index('token_hash');
            $table->index('expires_at');
            $table->index('used_at');
            $table->index(['email_hash', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('magic_links');
    }
};
