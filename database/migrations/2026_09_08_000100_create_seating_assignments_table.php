<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seating_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['seating_plan_id', 'student_id']);
            $table->unique(['seating_plan_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seating_assignments');
    }
};
