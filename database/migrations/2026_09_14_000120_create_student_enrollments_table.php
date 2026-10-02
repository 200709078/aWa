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
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('school_number');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            // Arşivdeki numara boşa çıkar (AGENTS.md #23): aktifler arası unique
            // uygulama katmanında denetlenir, DB'de index olarak tutulur.
            $table->index(['academic_year_id', 'school_number']);
            $table->unique(['student_id', 'academic_year_id']);
        });

        Schema::create('student_guardian', function (Blueprint $table) {
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relationship')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->primary(['student_id', 'guardian_id']);
        });

        Schema::create('graduates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('graduation_year');
            $table->string('graduation_number');
            $table->timestamps();
            $table->softDeletes();

            // Arşivdeki mezun numarası da boşa çıkar (AGENTS.md #23).
            $table->index(['graduation_year', 'graduation_number']);
        });

        // Eski kurulumlardan gelen kayıtlarda okul bağlantısı boş kalmış olabilir.
        $school = School::orderBy('id')->first();
        if ($school) {
            AcademicYear::whereNull('school_id')->update(['school_id' => $school->id]);
            Room::whereNull('school_id')->update(['school_id' => $school->id]);

            foreach (User::all() as $user) {
                if (! $user->schools()->exists()) {
                    $user->schools()->attach($school->id);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('graduates');
        Schema::dropIfExists('student_guardian');
        Schema::dropIfExists('student_enrollments');
    }
};
