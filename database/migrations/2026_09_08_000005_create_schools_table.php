<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kurum_kodu')->unique();
            $table->string('mail')->nullable();
            $table->string('telefon', 30)->nullable();
            $table->string('mudur')->nullable();
            $table->string('muduryrd')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('school_user', function (Blueprint $table) {
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['school_id', 'user_id']);
        });

        if (School::query()->count() === 0) {
            School::create([
                'name' => 'Ana Okul',
                'kurum_kodu' => '000000',
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_user');
        Schema::dropIfExists('schools');
    }
};
