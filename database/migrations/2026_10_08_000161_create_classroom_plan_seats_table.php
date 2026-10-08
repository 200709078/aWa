<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_plan_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row');
            $table->unsignedInteger('column');
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['classroom_plan_id', 'row', 'column']);
            $table->unique(['classroom_plan_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_plan_seats');
    }
};
