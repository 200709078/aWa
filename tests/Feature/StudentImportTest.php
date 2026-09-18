<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Person;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Tests\Traits\CreatesStudents;

class StudentImportTest extends TestCase
{
    use CreatesStudents;
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
        $this->makeStudent($year, $branch, '145', 'Ali Veli');

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
        $this->assertEquals([
            'school_number' => 1, 'full_name' => 2, 'branch' => 0,
            'student_phone' => null, 'student_email' => null, 'address' => null,
            'guardian_name' => null, 'guardian_phone' => null, 'guardian_email' => null,
        ], $props['mapping']);
        $this->assertEquals(
            ['toplam' => 6, 'eklenecek' => 1, 'guncellenecek' => 1, 'eklenecek_veli' => 0, 'guncellenecek_veli' => 0, 'atlandi' => 0, 'hatali' => 4],
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
        $this->assertEquals(0, $summary['eklenen_veli']);
        $this->assertEquals(0, $summary['guncellenen_veli']);

        $this->assertDatabaseHas('people', ['full_name' => 'Yeni Öğrenci']);
        $this->assertDatabaseHas('student_enrollments', ['academic_year_id' => $year->id, 'school_number' => '301', 'branch_id' => $branch->id]);
        $this->assertDatabaseHas('people', ['full_name' => 'Ali Veli Güncel']);
        $this->assertDatabaseMissing('student_enrollments', ['academic_year_id' => $year->id, 'school_number' => '303']);

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
        $this->assertEquals([
            'school_number' => 2, 'full_name' => 0, 'branch' => 1,
            'student_phone' => null, 'student_email' => null, 'address' => null,
            'guardian_name' => null, 'guardian_phone' => null, 'guardian_email' => null,
        ], $props['mapping']);
        $this->assertEquals(1, $props['summary']['eklenecek']);

        unlink($path);
    }

    public function test_sablon_indirilir_ve_icerigi_dogrudur(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);

        $response = $this->actingAs($user)->get('/students/import/template');
        $response->assertOk();
        $response->assertDownload('ogrenci-aktarim-sablonu.xlsx');

        $path = $response->baseResponse->getFile()->getPathname();
        $data = (new XlsxReader())->load($path)->getActiveSheet()->toArray(null, true, true, false);
        $this->assertSame(['Okul No', 'Ad Soyad', 'Sınıf', 'Öğrenci Telefon', 'Öğrenci E-posta', 'Adres', 'Veli Ad Soyad', 'Veli Telefon', 'Veli E-posta'], array_values($data[0]));

        $preview = $this->actingAs($user)->post('/students/import/preview', [
            'academic_year_id' => $year->id,
            'file' => $this->upload($path, 'ogrenci-aktarim-sablonu.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);
        $preview->assertOk();

        $props = $preview->viewData('page')['props'];
        $this->assertEquals([
            'school_number' => 0, 'full_name' => 1, 'branch' => 2,
            'student_phone' => 3, 'student_email' => 4, 'address' => 5,
            'guardian_name' => 6, 'guardian_phone' => 7, 'guardian_email' => 8,
        ], $props['mapping']);
        $this->assertEquals(0, $props['summary']['toplam']);
    }

    public function test_veli_ve_iletisim_bilgileriyle_import(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);

        $existing = $this->makeStudent($year, $branch, '145', 'Eski Kayıt');
        $guardianPerson = Person::create(['full_name' => 'Anne Kayıt', 'phone' => '1111111111']);
        $guardian = Guardian::create(['person_id' => $guardianPerson->id]);
        $existing->guardians()->attach($guardian->id, ['relationship' => 'anne', 'is_primary' => true]);

        $path = $this->makeFile('xlsx', ['SINIF', 'OKUL NO', 'ADI SOYADI', 'CİNSİYET', 'VELİ ADI SOYADI', 'VELİ CEP', 'TC KİMLİK NO', 'ÖĞRENCİ CEP', 'ADRES'], [
            ['9A', 301, 'Ahmet Bayram Gün', 'Erkek', 'Bayram Gün', 5462636932.0, 29573252678.0, 5333021071.0, 'Dikmekavak Mah. No:1'],
            ['9/A', 302, 'Velisiz Öğrenci', 'Kız', '', '', '', '', ''],
            ['9A', 145, 'Eski Kayıt Güncel', 'Erkek', 'Anne Kayıt', 2222222222.0, '', '', ''],
        ]);

        $preview = $this->actingAs($user)->post('/students/import/preview', [
            'academic_year_id' => $year->id,
            'file' => $this->upload($path, 'sms.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);
        $preview->assertOk();

        $props = $preview->viewData('page')['props'];
        $this->assertEquals([
            'school_number' => 1, 'full_name' => 2, 'branch' => 0,
            'student_phone' => 7, 'student_email' => null, 'address' => 8,
            'guardian_name' => 4, 'guardian_phone' => 5, 'guardian_email' => null,
        ], $props['mapping']);
        $this->assertEquals(
            ['toplam' => 3, 'eklenecek' => 2, 'guncellenecek' => 1, 'eklenecek_veli' => 1, 'guncellenecek_veli' => 1, 'atlandi' => 0, 'hatali' => 0],
            $props['summary']
        );

        $confirm = $this->actingAs($user)->post('/students/import/confirm', [
            'academic_year_id' => $year->id,
            'stored_path' => $props['storedPath'],
            'mapping' => $props['mapping'],
        ]);
        $confirm->assertOk();

        $summary = $confirm->viewData('page')['props']['summary'];
        $this->assertEquals(2, $summary['eklendi']);
        $this->assertEquals(1, $summary['guncellendi']);
        $this->assertEquals(1, $summary['eklenen_veli']);
        $this->assertEquals(1, $summary['guncellenen_veli']);

        // Yeni öğrenci: kişi + iletişim + ad ayrışımı
        $this->assertDatabaseHas('people', [
            'full_name' => 'Ahmet Bayram Gün', 'first_name' => 'Ahmet', 'last_name' => 'Bayram Gün',
            'phone' => '5333021071', 'address' => 'Dikmekavak Mah. No:1',
        ]);
        $this->assertDatabaseHas('student_enrollments', ['academic_year_id' => $year->id, 'school_number' => '301']);

        // Yeni veli: tek kayıt, ilişki ve birincil bayrağıyla bağlı
        $this->assertDatabaseHas('people', ['full_name' => 'Bayram Gün', 'first_name' => 'Bayram', 'last_name' => 'Gün', 'phone' => '5462636932']);
        $this->assertDatabaseHas('student_guardian', ['relationship' => 'veli', 'is_primary' => true]);
        $this->assertEquals(2, Guardian::count());

        // Velisiz öğrenci engellenmeden aktarıldı
        $this->assertDatabaseHas('student_enrollments', ['academic_year_id' => $year->id, 'school_number' => '302']);

        // Mevcut öğrenci + velisi güncellendi, yeni kayıt açılmadı
        $this->assertDatabaseHas('people', ['id' => $existing->person_id, 'full_name' => 'Eski Kayıt Güncel']);
        $this->assertDatabaseHas('people', ['id' => $guardianPerson->id, 'phone' => '2222222222']);
        $this->assertEquals(1, StudentEnrollment::where('academic_year_id', $year->id)->where('school_number', '145')->count());

        unlink($path);
    }

    public function test_ayni_yilda_mukerrer_numara_tek_kayit_olusturur(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);

        $path = $this->makeFile('xlsx', ['Okul No', 'Ad Soyad', 'Sınıf'], [
            [501, 'Birinci', '9A'],
            [501, 'İkinci', '9A'],
        ]);

        $preview = $this->actingAs($user)->post('/students/import/preview', [
            'academic_year_id' => $year->id,
            'file' => $this->upload($path, 'mukerrer.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);
        $preview->assertOk();
        $props = $preview->viewData('page')['props'];

        $confirm = $this->actingAs($user)->post('/students/import/confirm', [
            'academic_year_id' => $year->id,
            'stored_path' => $props['storedPath'],
            'mapping' => $props['mapping'],
        ]);
        $confirm->assertOk();

        $this->assertEquals(1, StudentEnrollment::where('academic_year_id', $year->id)->where('school_number', '501')->count());
        $this->assertEquals('Birinci', Person::whereHas('student.enrollments', fn ($q) => $q->where('school_number', '501'))->first()->full_name);

        unlink($path);
    }

    public function test_farkli_yilda_ayni_numara_izinli(): void
    {
        $user = User::factory()->create();
        $year1 = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch1 = Branch::create(['academic_year_id' => $year1->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $this->makeStudent($year1, $branch1, '145', 'Ali Veli');

        $year2 = AcademicYear::create(['name' => '2027-2028']);
        $branch2 = Branch::create(['academic_year_id' => $year2->id, 'name' => '10A', 'grade_level' => 10, 'section' => 'A']);

        $path = $this->makeFile('xlsx', ['Okul No', 'Ad Soyad', 'Sınıf'], [
            [145, 'Ali Veli', '10A'],
        ]);

        $preview = $this->actingAs($user)->post('/students/import/preview', [
            'academic_year_id' => $year2->id,
            'file' => $this->upload($path, 'yeniyil.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);
        $preview->assertOk();
        $props = $preview->viewData('page')['props'];
        $this->assertEquals(1, $props['summary']['eklenecek']);

        $confirm = $this->actingAs($user)->post('/students/import/confirm', [
            'academic_year_id' => $year2->id,
            'stored_path' => $props['storedPath'],
            'mapping' => $props['mapping'],
        ]);
        $confirm->assertOk();

        // Aynı öğrenci, iki yıl kaydı; eski yıl kaydı bozulmadı, kişi tekillendi
        $this->assertEquals(1, Student::count());
        $this->assertEquals(1, Person::count());
        $this->assertEquals(2, StudentEnrollment::where('school_number', '145')->count());
        $this->assertDatabaseHas('student_enrollments', ['academic_year_id' => $year2->id, 'school_number' => '145', 'branch_id' => $branch2->id]);
        $this->assertDatabaseHas('student_enrollments', ['academic_year_id' => $year1->id, 'school_number' => '145', 'branch_id' => $branch1->id]);

        unlink($path);
    }
}
