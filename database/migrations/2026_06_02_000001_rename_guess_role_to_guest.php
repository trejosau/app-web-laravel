<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', Role::LEGACY_GUESS)
            ->update(['name' => Role::GUEST]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', Role::GUEST)
            ->update(['name' => Role::LEGACY_GUESS]);
    }
};
