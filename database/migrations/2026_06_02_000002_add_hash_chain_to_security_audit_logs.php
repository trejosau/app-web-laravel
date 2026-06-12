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
            if (! Schema::hasColumn('security_audit_logs', 'previous_hash')) {
                $table->string('previous_hash', 128)->nullable()->after('metadata');
            }

            if (! Schema::hasColumn('security_audit_logs', 'current_hash')) {
                $table->string('current_hash', 128)->nullable()->after('previous_hash');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('security_audit_logs')) {
            return;
        }

        $columns = array_filter([
            Schema::hasColumn('security_audit_logs', 'previous_hash') ? 'previous_hash' : null,
            Schema::hasColumn('security_audit_logs', 'current_hash') ? 'current_hash' : null,
        ]);

        if ($columns === []) {
            return;
        }

        Schema::table('security_audit_logs', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
