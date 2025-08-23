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
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id');
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('level')->default(0);
            $table->string('path', 500);
            $table->string('color', 7)->default('#3498db');
            $table->string('icon', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('max_users')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('groups')->onDelete('cascade');
            
            $table->index(['domain_id', 'slug']);
            $table->index('parent_id');
            $table->index('path');
            $table->index('level');
            $table->index('is_active');
            $table->unique(['domain_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
