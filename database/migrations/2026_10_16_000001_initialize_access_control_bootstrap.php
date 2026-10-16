<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing installations must never offer ownership to the next signup.
        DB::table('access_control_state')
            ->where('id', 1)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('users'))
            ->update(['first_user_assigned' => true]);
    }

    public function down(): void
    {
        // Ownership consumption is irreversible, even when accounts are deleted.
    }
};
