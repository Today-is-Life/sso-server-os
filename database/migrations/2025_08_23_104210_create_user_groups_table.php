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
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('group_id');
            $table->timestamp('assigned_at');
            $table->uuid('assigned_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->json('metadata')->nullable();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            
            $table->unique(['user_id', 'group_id']);
            $table->index('user_id');
            $table->index('group_id');
            $table->index('expires_at');
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_groups');
    }
};
