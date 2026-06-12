<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            return;
        }

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('password_reset_tokens', 'user_id')) {
                $table->foreignUuid('user_id')->nullable()->after('email')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('password_reset_tokens', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('token');
            }

            if (! Schema::hasColumn('password_reset_tokens', 'used_at')) {
                $table->timestamp('used_at')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            return;
        }

        $columns = array_filter([
            Schema::hasColumn('password_reset_tokens', 'expires_at') ? 'expires_at' : null,
            Schema::hasColumn('password_reset_tokens', 'used_at') ? 'used_at' : null,
            Schema::hasColumn('password_reset_tokens', 'user_id') ? 'user_id' : null,
        ]);

        if ($columns === []) {
            return;
        }

        Schema::table('password_reset_tokens', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
