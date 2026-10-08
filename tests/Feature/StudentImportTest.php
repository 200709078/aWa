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
            'school_number' => 1, 'full_name' => 2, 'branch' => 0, 'gender' => null,
            'student_phone' => null, 'student_email' => null, 'guardian_selector' => null,
            'mother_name' => null, 'mother_phone' => null, 'father_name' => null, 'father_phone' => null,
            'other_guardian_name' => null, 'other_guardian_phone' => null, 'address' => null,
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
            'school_number' => 2, 'full_name' => 0, 'branch' => 1, 'gender' => null,
            'student_phone' => null, 'student_email' => null, 'guardian_selector' => null,
            'mother_name' => null, 'mother_phone' => null, 'father_name' => null, 'father_phone' => null,
            'other_guardian_name' => null, 'other_guardian_phone' => null, 'address' => null,
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
        $this->assertSame(['Okul No', 'Ad Soyad', 'Sınıf', 'Cinsiyet', 'Öğrenci Telefon', 'Öğrenci E-posta', 'Velisi Kim', 'Anne Ad Soyad', 'Anne Cep', 'Baba Ad Soyad', 'Baba Cep', 'Diğer Veli Ad Soyad', 'Diğer Veli Cep', 'Adres'], array_values($data[0]));

        $preview = $this->actingAs($user)->post('/students/import/preview', [
            'academic_year_id' => $year->id,
            'file' => $this->upload($path, 'ogrenci-aktarim-sablonu.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);
        $preview->assertOk();

        $props = $preview->viewData('page')['props'];
        $this->assertEquals([
            'school_number' => 0, 'full_name' => 1, 'branch' => 2, 'gender' => 3,
            'student_phone' => 4, 'student_email' => 5, 'guardian_selector' => 6,
            'mother_name' => 7, 'mother_phone' => 8, 'father_name' => 9, 'father_phone' => 10,
            'other_guardian_name' => 11, 'other_guardian_phone' => 12, 'address' => 13,
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
            'school_number' => 1, 'full_name' => 2, 'branch' => 0, 'gender' => 3,
            'student_phone' => 7, 'student_email' => null, 'guardian_selector' => null,
            'mother_name' => null, 'mother_phone' => null, 'father_name' => null, 'father_phone' => null,
            'other_guardian_name' => 4, 'other_guardian_phone' => 5, 'address' => 8,
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
            'full_name' => 'Ahmet Bayram Gün', 'first_name' => 'Ahmet Bayram', 'last_name' => 'Gün',
            'phone' => '5333021071', 'address' => 'Dikmekavak Mah. No:1', 'gender' => 'Erkek',
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

    public function test_yeni_sablon_anne_baba_birincil_veli_ve_cinsiyet(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);

        $header = ['Okul No', 'Ad Soyad', 'Sınıf', 'Cinsiyet', 'Öğrenci Telefon', 'Öğrenci E-posta', 'Velisi Kim', 'Anne Ad Soyad', 'Anne Cep', 'Baba Ad Soyad', 'Baba Cep', 'Diğer Veli Ad Soyad', 'Diğer Veli Cep', 'Adres'];
        $path = $this->makeFile('xlsx', $header, [
            [301, 'Anne Öğrenci', '9A', 'Kız', 5330000001, 'anne@x.com', 'Anne', 'Anne Adı', 5320000001, 'Baba Adı', 5320000002, '', '', 'Adres 1'],
            [302, 'Baba Öğrenci', '9A', 'Erkek', 5330000002, '', 'Baba', 'Anne İki', 5320000011, 'Baba İki', 5320000012, '', '', 'Adres 2'],
            [303, 'Diger Öğrenci', '9A', 'Erkek', '', '', 'Diğer', '', '', '', '', 'Dede Veli', 5320000031, 'Adres 3'],
        ]);

        $preview = $this->actingAs($user)->post('/students/import/preview', [
            'academic_year_id' => $year->id,
            'file' => $this->upload($path, 'yeni-sablon.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);
        $preview->assertOk();

        $props = $preview->viewData('page')['props'];
        $this->assertEquals([
            'school_number' => 0, 'full_name' => 1, 'branch' => 2, 'gender' => 3,
            'student_phone' => 4, 'student_email' => 5, 'guardian_selector' => 6,
            'mother_name' => 7, 'mother_phone' => 8, 'father_name' => 9, 'father_phone' => 10,
            'other_guardian_name' => 11, 'other_guardian_phone' => 12, 'address' => 13,
        ], $props['mapping']);
        $this->assertEquals(
            ['toplam' => 3, 'eklenecek' => 3, 'guncellenecek' => 0, 'eklenecek_veli' => 5, 'guncellenecek_veli' => 0, 'atlandi' => 0, 'hatali' => 0],
            $props['summary']
        );

        $confirm = $this->actingAs($user)->post('/students/import/confirm', [
            'academic_year_id' => $year->id,
            'stored_path' => $props['storedPath'],
            'mapping' => $props['mapping'],
        ]);
        $confirm->assertOk();

        $summary = $confirm->viewData('page')['props']['summary'];
        $this->assertEquals(3, $summary['eklendi']);
        $this->assertEquals(5, $summary['eklenen_veli']);
        $this->assertEquals(0, $summary['guncellenen_veli']);

        // Cinsiyet kişi kaydına işlendi.
        $this->assertDatabaseHas('people', ['full_name' => 'Anne Öğrenci', 'gender' => 'Kız', 'phone' => '5330000001']);
        $this->assertDatabaseHas('people', ['full_name' => 'Baba Öğrenci', 'gender' => 'Erkek']);

        // 301: anne birincil, baba değil.
        $s301 = Student::whereHas('enrollments', fn ($q) => $q->where('academic_year_id', $year->id)->where('school_number', '301'))->first();
        $this->assertNotNull($s301);
        $this->assertEquals('anne', $s301->guardians()->wherePivot('is_primary', true)->first()?->pivot->relationship);
        $this->assertEquals(1, $s301->guardians()->wherePivot('is_primary', true)->count());
        $this->assertDatabaseHas('people', ['full_name' => 'Anne Adı', 'phone' => '5320000001']);
        $this->assertDatabaseHas('people', ['full_name' => 'Baba Adı', 'phone' => '5320000002']);

        // 302: baba birincil.
        $s302 = Student::whereHas('enrollments', fn ($q) => $q->where('academic_year_id', $year->id)->where('school_number', '302'))->first();
        $this->assertNotNull($s302);
        $this->assertEquals('baba', $s302->guardians()->wherePivot('is_primary', true)->first()?->pivot->relationship);

        // 303: diğer veli (veli) birincil.
        $s303 = Student::whereHas('enrollments', fn ($q) => $q->where('academic_year_id', $year->id)->where('school_number', '303'))->first();
        $this->assertNotNull($s303);
        $primary303 = $s303->guardians()->wherePivot('is_primary', true)->first();
        $this->assertNotNull($primary303);
        $this->assertEquals('veli', $primary303->pivot->relationship);
        $this->assertEquals('Dede Veli', $primary303->person->full_name);

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
