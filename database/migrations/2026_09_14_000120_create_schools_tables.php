<?php

use App\Models\AcademicYear;
use App\Models\Room;
use App\Models\School;
use App\Models\User;
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

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('school_admin')->after('password');
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $school = School::create([
            'name' => 'Ana Okul',
            'kurum_kodu' => '000000',
        ]);

        AcademicYear::query()->update(['school_id' => $school->id]);
        Room::query()->update(['school_id' => $school->id]);

        foreach (User::all() as $user) {
            $user->update(['role' => 'school_admin']);
            $user->schools()->attach($school->id);
        }
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::dropIfExists('school_user');
        Schema::dropIfExists('schools');
    }
};
