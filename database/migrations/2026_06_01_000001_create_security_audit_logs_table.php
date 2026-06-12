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
        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->string('action', 20)->index();
            $table->enum('state', ['SUCCESS', 'FAILED', 'WARNING', 'PENDING', 'CANCELLED'])->index();
            $table->enum('severity', ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])->default('info')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('route')->nullable();
            $table->string('method', 10)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
    }
};
