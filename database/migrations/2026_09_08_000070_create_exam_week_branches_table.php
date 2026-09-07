<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_week_branches', function (Blueprint $table) {
            $table->foreignId('exam_week_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            $table->unique(['exam_week_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_week_branches');
    }
};
