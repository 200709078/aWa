<?php

namespace Tests\Feature;

use App\Http\Controllers\StudentImportController;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Person;
use App\Models\User;
use App\Services\RehberExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Tests\TestCase;
use Tests\Traits\CreatesStudents;

class VcfExportTest extends TestCase
{
    use CreatesStudents;
    use RefreshDatabase;

    private function setupData(): AcademicYear
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $b9a = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $b10a = Branch::create(['academic_year_id' => $year->id, 'name' => '10A', 'grade_level' => 10, 'section' => 'A']);

        $s1 = $this->makeStudent($year, $b9a, '145', 'Ahmet Bayram Gün');
        $s1->person->update(['phone' => '5321234567']);

        $img = imagecreatetruecolor(100, 100);
        imagefill($img, 0, 0, imagecolorallocate($img, 100, 150, 200));
        $tmp = sys_get_temp_dir().'/vcf-foto.jpg';
        imagejpeg($img, $tmp, 90);
        imagedestroy($img);
        Storage::disk('public')->put('students/vcf-foto.jpg', file_get_contents($tmp));
        @unlink($tmp);
        $s1->person->update(['photo_path' => 'students/vcf-foto.jpg']);

        $this->makeStudent($year, $b10a, '200', 'Fotosuz Öğrenci');

        $gp = Person::create(['full_name' => 'Bayram Gün', 'phone' => '5462636932']);
        $guardian = Guardian::create(['person_id' => $gp->id]);
        $s1->guardians()->attach($guardian->id, ['relationship' => 'baba', 'is_primary' => true]);

        return $year;
    }

    public function test_ad_soyad_sondan_bolunur(): void
    {
        $this->assertEquals(['Ahmet Bayram', 'Gün'], StudentImportController::splitName('Ahmet Bayram Gün'));
        $this->assertEquals(['Ayşe', 'Yılmaz'], StudentImportController::splitName('Ayşe Yılmaz'));
        $this->assertEquals([null, null], StudentImportController::splitName('Tek'));
    }

    public function test_ozet_dogru_sayar(): void
    {
        $user = User::factory()->create();
        $year = $this->setupData();
        $branchIds = Branch::where('academic_year_id', $year->id)->pluck('id')->all();

        $response = $this->actingAs($user)->postJson('/rehber-aktarma/ozet', [
            'academic_year_id' => $year->id,
            'branch_ids' => $branchIds,
            'type' => 'all',
        ]);
        $response->assertOk();
        // 2 öğrenci + 1 veli; telefonu olmayan 1 öğrenci kart dışı
        $this->assertEquals(['students' => 2, 'guardians' => 1, 'cards' => 2, 'without_phone' => 1], $response->json());
    }

    public function test_vcf_indirme_icerigi_dogrudur(): void
    {
        $user = User::factory()->create();
        $year = $this->setupData();
        $branchIds = Branch::where('academic_year_id', $year->id)->pluck('id')->all();

        $query = http_build_query([
            'academic_year_id' => $year->id,
            'branch_ids' => $branchIds,
            'type' => 'all',
            'photo' => 1,
        ]);

        $response = $this->actingAs($user)->get("/rehber-aktarma/indir?{$query}");
        $response->assertOk();
        $response->assertDownload('2026-2027-tum-subeler-all-2.vcf');

        $content = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertEquals(2, substr_count($content, 'BEGIN:VCARD'));
        // Uzun satırlar 75 sekizlide katlanır; içerik doğrulaması için katlamayı aç
        $this->assertStringContainsString("\r\n ", $content);
        $content = str_replace("\r\n ", '', $content);

        // Öğrenci kartı: önek, sondan bölünmüş ad/soyad, başına sıfır eklenmiş telefon
        $this->assertStringContainsString('FN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:09A-145=20Ahmet=20Bayram=20G=C3=BCn', $content);
        $this->assertStringContainsString('N;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:G=C3=BCn;09A-145=20Ahmet=20Bayram;;;', $content);
        $this->assertStringContainsString('TEL;CELL:05321234567', $content);
        $this->assertStringContainsString('CATEGORIES:9A', $content);
        $this->assertStringContainsString('TITLE;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:=C3=96=C4=9Frenci', $content);

        // Veli kartı
        $this->assertStringContainsString('TEL;CELL:05462636932', $content);

        // Fotoğraf gömülü (öğrencide var, velide yok)
        $this->assertEquals(1, substr_count($content, 'PHOTO;ENCODING=b;TYPE=JPEG:'));

        // Telefonsuz öğrenci dosyada yok
        $this->assertStringNotContainsString('Fotosuz', $content);

        Storage::disk('public')->delete('students/vcf-foto.jpg');
    }

    public function test_tip_filtresi_calisir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupData();
        $branchIds = Branch::where('academic_year_id', $year->id)->pluck('id')->all();

        $service = new RehberExportService();
        $this->assertEquals(1, $service->summary($year->id, $branchIds, 'student')['cards']);
        $this->assertEquals(1, $service->summary($year->id, $branchIds, 'guardian')['cards']);
    }

    public function test_excel_disa_aktar_duzeni_dogrudur(): void
    {
        $user = User::factory()->create();
        $year = $this->setupData();
        $branchIds = Branch::where('academic_year_id', $year->id)->pluck('id')->all();

        $query = http_build_query([
            'academic_year_id' => $year->id,
            'branch_ids' => $branchIds,
            'type' => 'all',
        ]);

        $response = $this->actingAs($user)->get("/rehber-aktarma/excel?{$query}");
        $response->assertOk();
        $response->assertDownload('2026-2027-tum-subeler-all-excel-liste-4.xlsx');

        $data = (new XlsxReader())->load($response->baseResponse->getFile()->getPathname())
            ->getActiveSheet()->toArray(null, true, true, false);

        $this->assertSame(['SINIF', 'NO', 'ÖĞRENCİ ADI SOYADI', 'VELİ ADI SOYADI', 'VELİ TEL'], array_values($data[0]));
        $this->assertSame(['V09A', '145', 'Ahmet Bayram Gün', 'Bayram Gün', '05462636932'], array_values($data[1]));
        $this->assertSame(['O09A', '145', 'Ahmet Bayram Gün', 'Bayram Gün', '05321234567'], array_values($data[2]));

        Storage::disk('public')->delete('students/vcf-foto.jpg');
    }
}
