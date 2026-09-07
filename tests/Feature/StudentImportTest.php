<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(string $ext, array $header, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(array_merge([$header], $rows), null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'imp').'.'.$ext;
        $writer = $ext === 'xls' ? new Xls($spreadsheet) : new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    private function upload(string $path, string $name, string $mime): UploadedFile
    {
        return new UploadedFile($path, $name, $mime, null, true);
    }

    public function test_excel_onizleme_ve_onay_akisi(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        Student::create(['academic_year_id' => $year->id, 'branch_id' => $branch->id, 'school_number' => '145', 'full_name' => 'Ali Veli']);

        $path = $this->makeFile('xlsx', ['Sınıf', 'Okul No', 'Ad Soyad'], [
            ['9/A', 301, 'Yeni Öğrenci'],
            ['9A', 145, 'Ali Veli Güncel'],
            ['9A', '', 'Nosuz Öğrenci'],
            ['9A', 302, ''],
            ['12D', 303, 'Şubesiz Öğrenci'],
            ['9A', 301, 'Mükerrer Öğrenci'],
            ['', '', ''],
        ]);

        $preview = $this->actingAs($user)->post('/students/import/preview', [
            'academic_year_id' => $year->id,
            'file' => $this->upload($path, 'ogrenciler.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);
        $preview->assertOk();

        $props = $preview->viewData('page')['props'];
        $this->assertEquals(['school_number' => 1, 'full_name' => 2, 'branch' => 0], $props['mapping']);
        $this->assertEquals(
            ['toplam' => 6, 'eklenecek' => 1, 'guncellenecek' => 1, 'atlandi' => 0, 'hatali' => 4],
            $props['summary']
        );

        $confirm = $this->actingAs($user)->post('/students/import/confirm', [
            'academic_year_id' => $year->id,
            'stored_path' => $props['storedPath'],
            'mapping' => $props['mapping'],
        ]);
        $confirm->assertOk();

        $summary = $confirm->viewData('page')['props']['summary'];
        $this->assertEquals(1, $summary['eklendi']);
        $this->assertEquals(1, $summary['guncellendi']);
        $this->assertEquals(4, $summary['hatali']);

        $this->assertDatabaseHas('students', ['school_number' => '301', 'full_name' => 'Yeni Öğrenci', 'branch_id' => $branch->id]);
        $this->assertDatabaseHas('students', ['school_number' => '145', 'full_name' => 'Ali Veli Güncel']);
        $this->assertDatabaseMissing('students', ['school_number' => '303']);

        unlink($path);
    }

    public function test_xls_dosyasi_ve_farkli_sutun_sirasi(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);

        $path = $this->makeFile('xls', ['Adı Soyadı', 'Şube', 'Öğrenci No'], [
            ['Xls Öğrenci', '9 A', 401],
        ]);

        $preview = $this->actingAs($user)->post('/students/import/preview', [
            'academic_year_id' => $year->id,
            'file' => $this->upload($path, 'ogrenciler.xls', 'application/vnd.ms-excel'),
        ]);
        $preview->assertOk();

        $props = $preview->viewData('page')['props'];
        $this->assertEquals(['school_number' => 2, 'full_name' => 0, 'branch' => 1], $props['mapping']);
        $this->assertEquals(1, $props['summary']['eklenecek']);

        unlink($path);
    }
}
