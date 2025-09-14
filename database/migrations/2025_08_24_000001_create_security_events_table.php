<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_id', 50)->index();
            $table->enum('severity', ['debug', 'info', 'warning', 'error', 'critical'])->index();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('domain_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('action', 100);
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->uuid('correlation_id')->nullable()->index();
            $table->timestamp('created_at')->index();
            
            // Composite indexes for common queries
            $table->index(['created_at', 'severity']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event_id', 'created_at']);
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('domain_id')->references('id')->on('domains')->nullOnDelete();
        });
        
        // Note: Partitioning is only available in MySQL/PostgreSQL
        // For SQLite, we rely on indexes for performance
        if (config('database.default') === 'mysql') {
            DB::statement("
                ALTER TABLE security_events
                PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
                    PARTITION p202501 VALUES LESS THAN (202502),
                    PARTITION p202502 VALUES LESS THAN (202503),
                    PARTITION p202503 VALUES LESS THAN (202504),
                    PARTITION p202504 VALUES LESS THAN (202505),
                    PARTITION p202505 VALUES LESS THAN (202506),
                    PARTITION p202506 VALUES LESS THAN (202507),
                    PARTITION p202507 VALUES LESS THAN (202508),
                    PARTITION p202508 VALUES LESS THAN (202509),
                    PARTITION p202509 VALUES LESS THAN (202510),
                    PARTITION p202510 VALUES LESS THAN (202511),
                    PARTITION p202511 VALUES LESS THAN (202512),
                    PARTITION p202512 VALUES LESS THAN (202601),
                    PARTITION pmax VALUES LESS THAN MAXVALUE
                )
            ");
        }
    }
    
    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};