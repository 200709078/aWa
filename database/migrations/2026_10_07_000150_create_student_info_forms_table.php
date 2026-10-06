<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_info_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students');
            $table->string('earthquake_loss', 50)->nullable();
            $table->string('family_income', 50)->nullable();
            $table->string('transport', 50)->nullable();
            $table->string('free_lunch', 50)->nullable();
            $table->string('martyr_child', 50)->nullable();
            $table->string('preschool', 50)->nullable();
            $table->string('medication', 255)->nullable();
            $table->string('medical_device', 255)->nullable();
            $table->string('hobbies', 500)->nullable();
            $table->string('moved', 50)->nullable();
            $table->string('changed_school', 50)->nullable();
            $table->string('extracurricular', 500)->nullable();
            $table->string('tech_devices', 500)->nullable();
            $table->string('trauma', 500)->nullable();
            $table->unsignedTinyInteger('sibling_count')->nullable();
            $table->unsignedTinyInteger('birth_order')->nullable();
            $table->unsignedTinyInteger('school_siblings')->nullable();
            $table->string('family_disability', 255)->nullable();
            $table->string('family_illness', 255)->nullable();
            $table->string('household', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_info_forms');
    }
};
