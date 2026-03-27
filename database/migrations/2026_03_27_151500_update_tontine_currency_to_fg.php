<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tontines')) {
            return;
        }

        DB::table('tontines')
            ->where('currency', 'FCFA')
            ->update(['currency' => 'FG']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tontines')) {
            return;
        }

        DB::table('tontines')
            ->where('currency', 'FG')
            ->update(['currency' => 'FCFA']);
    }
};
