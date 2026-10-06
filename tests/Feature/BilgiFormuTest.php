<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Tests\Traits\CreatesStudents;

class BilgiFormuTest extends TestCase
{
    use CreatesStudents;
    use RefreshDatabase;

    private function setupYear(): AcademicYear
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        Branch::create(['academic_year_id' => $year->id, 'name' => '9B', 'grade_level' => 9, 'section' => 'B']);

        return $year;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function makeFile(array $headers, array $rows): UploadedFile
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray([$headers, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'bf').'.xlsx';
        (new Xlsx($sheet->getParent()))->save($path);

        return new UploadedFile($path, 'yanitlar.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function headers(): array
    {
        return [
            'E-posta Adresi', 'Adınız Soyadınız', 'Cinsiyetiniz', 'Okul Numaranız',
            'Telefon Numaranız', 'Sınıfınız', 'Doğum Yeriniz', 'Kan grubunuz nedir?',
            'Kaç kardeşsiniz?', 'Veliniz kim?', 'Annenizin adını ve soyadını giriniz.',
            'Annenizin telefon numarasını giriniz.',
        ];
    }

    public function test_import_bos_alanlari_doldurup_form_olusturur(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $student = $this->makeStudent($year, $branch, '145', 'Ali Veli');

        $file = $this->makeFile($this->headers(), [[
            'ali@example.com', 'Ali Veli', 'Erkek', '145', '05320000001', '9A',
            'Ankara', 'A Rh+', '3', 'Annem', 'Ayşe Veli', '05320000002',
        ]]);

        $response = $this->actingAs($user)->post('/bilgi-formlari/ice-aktar', [
            'academic_year_id' => $year->id,
            'file' => $file,
        ]);
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('summary.toplam', 1)
            ->where('summary.islendi', 1)
            ->where('summary.form_olustu', 1));

        $person = $student->person->fresh();
        $this->assertEquals('ali@example.com', $person->email);
        $this->assertEquals('+905320000001', $person->phone);
        $this->assertEquals('Erkek', $person->gender);
        $this->assertEquals('Ankara', $person->birth_place);
        $this->assertEquals('A Rh+', $person->blood_type);

        $this->assertDatabaseHas('student_info_forms', ['student_id' => $student->id, 'sibling_count' => 3]);
        $this->assertDatabaseHas('people', ['full_name' => 'Ayşe Veli', 'phone' => '+905320000002']);
    }

    public function test_dolu_alan_korunur_uzerine_yaz_secimliktir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $student = $this->makeStudent($year, $branch, '145', 'Ali Veli');
        $student->person->update(['phone' => '+905329999999']);

        $row = ['ali@example.com', 'Ali Veli', 'Erkek', '145', '05320000001', '9A', 'Ankara', 'A Rh+', '3', 'Annem', 'Ayşe Veli', '05320000002'];

        // Üzerine yaz kapalı: dolu telefon korunur, boş e-posta dolar.
        $this->actingAs($user)->post('/bilgi-formlari/ice-aktar', [
            'academic_year_id' => $year->id,
            'file' => $this->makeFile($this->headers(), [$row]),
        ])->assertOk();

        $person = $student->person->fresh();
        $this->assertEquals('+905329999999', $person->phone);
        $this->assertEquals('ali@example.com', $person->email);

        // Üzerine yaz açık: dosya kazanır.
        $this->actingAs($user)->post('/bilgi-formlari/ice-aktar', [
            'academic_year_id' => $year->id,
            'file' => $this->makeFile($this->headers(), [$row]),
            'overwrite' => true,
        ])->assertOk();

        $this->assertEquals('+905320000001', $student->person->fresh()->phone);
    }

    public function test_sinif_uyusmazsa_satir_islenmez(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $student = $this->makeStudent($year, $branch, '145', 'Ali Veli');

        $response = $this->actingAs($user)->post('/bilgi-formlari/ice-aktar', [
            'academic_year_id' => $year->id,
            'file' => $this->makeFile($this->headers(), [[
                'ali@example.com', 'Ali Veli', 'Erkek', '145', '05320000001', '9B',
                'Ankara', 'A Rh+', '3', 'Annem', 'Ayşe Veli', '05320000002',
            ]]),
        ]);
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('summary.islendi', 0)
            ->where('summary.atlandi', 1)
            ->has('warnings', 1));

        $this->assertDatabaseMissing('student_info_forms', ['student_id' => $student->id]);
        $this->assertNull($student->person->fresh()->email);
    }

    public function test_bilinmeyen_numara_hataya_duser(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();

        $response = $this->actingAs($user)->post('/bilgi-formlari/ice-aktar', [
            'academic_year_id' => $year->id,
            'file' => $this->makeFile($this->headers(), [[
                'ali@example.com', 'Ali Veli', 'Erkek', '999', '05320000001', '9A',
                'Ankara', 'A Rh+', '3', 'Annem', 'Ayşe Veli', '05320000002',
            ]]),
        ]);
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('errors', 1));
    }

    public function test_sayfalar_acilir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $student = $this->makeStudent($year, $branch, '145', 'Ali Veli');

        $this->actingAs($user)->get('/bilgi-formlari')->assertOk()
            ->assertInertia(fn ($page) => $page->where('students.total', 1));
        $this->actingAs($user)->get('/bilgi-formlari/ice-aktar')->assertOk();
        $this->actingAs($user)->get("/bilgi-formlari/{$student->id}/duzenle")->assertOk()
            ->assertInertia(fn ($page) => $page->where('student.full_name', 'Ali Veli'));
        $this->actingAs($user)->get("/bilgi-formlari/yazdir?ids={$student->id}")->assertOk();
        $this->actingAs($user)->get("/bilgi-formlari/risk-haritasi?branch_id={$branch->id}")->assertOk();
    }
}
