<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('security_audit_logs')) {
            return;
        }

        Schema::table('security_audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('security_audit_logs', 'action')) {
                $table->string('action', 20)->default('view')->index();
            }

            if (! Schema::hasColumn('security_audit_logs', 'state')) {
                $table->enum('state', ['SUCCESS', 'FAILED', 'WARNING', 'PENDING', 'CANCELLED'])->default('SUCCESS')->index();
            }

            if (! Schema::hasColumn('security_audit_logs', 'severity')) {
                $table->enum('severity', ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])->default('info')->index();
            }

            if (! Schema::hasColumn('security_audit_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable();
            }

            if (! Schema::hasColumn('security_audit_logs', 'user_agent')) {
                $table->text('user_agent')->nullable();
            }

            if (! Schema::hasColumn('security_audit_logs', 'route')) {
                $table->string('route')->nullable();
            }

            if (! Schema::hasColumn('security_audit_logs', 'method')) {
                $table->string('method', 10)->nullable();
            }

            if (! Schema::hasColumn('security_audit_logs', 'status')) {
                $table->unsignedSmallInteger('status')->nullable();
            }

            if (! Schema::hasColumn('security_audit_logs', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (! Schema::hasColumn('security_audit_logs', 'previous_hash')) {
                $table->string('previous_hash', 128)->nullable();
            }

            if (! Schema::hasColumn('security_audit_logs', 'current_hash')) {
                $table->string('current_hash', 128)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('security_audit_logs')) {
            return;
        }

        $columns = array_filter([
            Schema::hasColumn('security_audit_logs', 'current_hash') ? 'current_hash' : null,
            Schema::hasColumn('security_audit_logs', 'previous_hash') ? 'previous_hash' : null,
        ]);

        if ($columns === []) {
            return;
        }

        Schema::table('security_audit_logs', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
