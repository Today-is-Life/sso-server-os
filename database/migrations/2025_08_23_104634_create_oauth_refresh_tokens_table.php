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
        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('access_token_id');
            $table->text('token');
            $table->boolean('revoked')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->foreign('access_token_id')->references('id')->on('oauth_tokens')->onDelete('cascade');
            
            $table->index('access_token_id');
            $table->index('revoked');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_tokens');
    }
};
