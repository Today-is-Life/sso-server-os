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
        Schema::create('domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('url');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->string('client_id')->unique();
            $table->text('client_secret');
            $table->json('allowed_origins')->nullable();
            $table->json('redirect_uris')->nullable();
            $table->string('logout_redirect_uri')->nullable();
            $table->integer('token_lifetime')->default(3600);
            $table->integer('refresh_token_lifetime')->default(86400);
            $table->timestamps();
            
            $table->index('name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
