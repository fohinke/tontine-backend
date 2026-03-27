<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_plans', function (Blueprint $table): void {
            $table->string('schedule_type')->nullable()->after('day_interval');
            $table->unsignedTinyInteger('day_of_month')->nullable()->after('schedule_type');
            $table->string('week_of_month')->nullable()->after('day_of_month');
            $table->string('weekday')->nullable()->after('week_of_month');
        });
    }

    public function down(): void
    {
        Schema::table('contribution_plans', function (Blueprint $table): void {
            $table->dropColumn([
                'schedule_type',
                'day_of_month',
                'week_of_month',
                'weekday',
            ]);
        });
    }
};
