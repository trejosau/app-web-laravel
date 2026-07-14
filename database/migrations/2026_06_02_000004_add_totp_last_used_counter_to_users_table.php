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
        if (Schema::hasColumn('users', 'totp_last_used_counter')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('totp_last_used_counter')->nullable()->after('totp_enabled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'totp_last_used_counter')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('totp_last_used_counter');
        });
    }
};
