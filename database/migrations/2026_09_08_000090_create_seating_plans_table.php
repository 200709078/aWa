<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_week_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->unsignedInteger('total_students')->default(0);
            $table->unsignedInteger('used_room_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seating_plans');
    }
};
