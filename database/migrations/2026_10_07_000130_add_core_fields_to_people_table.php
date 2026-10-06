<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('last_name');
            $table->string('birth_place', 100)->nullable()->after('gender');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->string('blood_type', 10)->nullable()->after('birth_date');
            $table->string('religion', 50)->nullable()->after('blood_type');
            $table->unsignedSmallInteger('height_cm')->nullable()->after('religion');
            $table->unsignedSmallInteger('weight_kg')->nullable()->after('height_cm');
            $table->string('education_level', 50)->nullable()->after('weight_kg');
            $table->string('occupation', 100)->nullable()->after('education_level');
            $table->boolean('is_alive')->nullable()->after('occupation');
            $table->string('disability', 255)->nullable()->after('is_alive');
            $table->string('chronic_illness', 255)->nullable()->after('disability');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'birth_place', 'birth_date', 'blood_type', 'religion',
                'height_cm', 'weight_kg', 'education_level', 'occupation',
                'is_alive', 'disability', 'chronic_illness',
            ]);
        });
    }
};
