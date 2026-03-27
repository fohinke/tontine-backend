<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('frequency');
            $table->decimal('amount', 12, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('day_interval')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_plans');
    }
};
