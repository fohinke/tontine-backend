<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contribution_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date');
            $table->dateTime('meeting_date')->nullable();
            $table->decimal('expected_amount', 12, 2)->default(0);
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_sessions');
    }
};
