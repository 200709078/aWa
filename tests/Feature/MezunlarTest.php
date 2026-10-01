<?php

namespace Tests\Feature;

use App\Models\Graduate;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MezunlarTest extends TestCase
{
    use RefreshDatabase;

    private function makeGraduate(int $year, string $number, string $name, array $overrides = []): Graduate
    {
        $person = Person::create([
            'full_name' => $name,
            'phone' => $overrides['phone'] ?? null,
            'email' => $overrides['email'] ?? null,
            'photo_path' => $overrides['photo_path'] ?? null,
        ]);

        $graduate = Graduate::create([
            'person_id' => $person->id,
            'student_id' => null,
            'graduation_year' => $year,
            'graduation_number' => $number,
        ]);

        if (isset($overrides['education'])) {
            $person->educations()->create($overrides['education']);
        }
        if (isset($overrides['company'])) {
            $person->employments()->create(['company_name' => $overrides['company'], 'job_title' => $overrides['job'] ?? null]);
        }

        return $graduate;
    }

    public function test_listeleme_yil_filtresi_ve_sayfalama(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 32; $i++) {
            $this->makeGraduate(2009, (string) $i, "Mezun $i");
        }
        $this->makeGraduate(2010, '1', 'Başka Yıl');

        // 1. sayfa = en yeni yıl (2010), tamamı tek sayfada
        $response = $this->actingAs($user)->get('/mezunlar');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('year', 2010)
            ->where('graduates.total', 1)
            ->where('graduates.data.0.full_name', 'Başka Yıl')
            ->where('graduates.next_page_url', '/mezunlar?page=2')
            ->where('totalGraduates', 33)
        );

        // 2. sayfa = 2009, 32 kayıt tek sayfada
        $response = $this->actingAs($user)->get('/mezunlar?page=2');
        $response->assertInertia(fn ($page) => $page
            ->where('year', 2009)
            ->where('graduates.total', 32)
            ->where('graduates.prev_page_url', '/mezunlar?page=1')
            ->where('graduates.next_page_url', null)
        );

        // Arama tüm yıllarda düz liste
        $response = $this->actingAs($user)->get('/mezunlar?q=Mezun 3');
        $response->assertInertia(fn ($page) => $page->where('graduates.total', 4));

        $response = $this->actingAs($user)->get('/mezunlar?q=32');
        $response->assertInertia(fn ($page) => $page
            ->where('graduates.total', 1)
            ->where('graduates.data.0.full_name', 'Mezun 32')
        );
    }

    public function test_mezun_ekleme_guncelleme_silme(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/mezunlar', [
            'graduation_year' => 2024,
            'graduation_number' => '10',
            'first_name' => 'Yeni',
            'last_name' => 'Mezun',
            'phone' => '05320000000',
            'email' => 'yeni@example.com',
            'educations' => [
                ['city' => 'Çorum', 'institution_name' => 'Hitit Üniversitesi', 'faculty' => null, 'department' => 'Tıp'],
            ],
            'company' => 'Hastane',
        ])->assertRedirect();

        $this->assertDatabaseHas('people', ['full_name' => 'Yeni Mezun', 'first_name' => 'Yeni', 'last_name' => 'Mezun']);
        $this->assertDatabaseHas('graduates', ['graduation_year' => 2024, 'graduation_number' => '10']);
        $this->assertDatabaseHas('person_educations', ['institution_name' => 'Hitit Üniversitesi', 'city' => 'Çorum']);
        $this->assertDatabaseHas('person_employments', ['company_name' => 'Hastane']);

        $graduate = Graduate::where('graduation_number', '10')->first();
        $educationId = $graduate->person->educations()->first()->id;

        $this->actingAs($user)->post('/mezunlar', [
            'graduation_year' => 2024,
            'graduation_number' => '10',
            'first_name' => 'Başka',
            'last_name' => 'Biri',
        ])->assertSessionHasErrors('graduation_number');

        $this->actingAs($user)->put("/mezunlar/{$graduate->id}", [
            'graduation_year' => 2024,
            'graduation_number' => '11',
            'first_name' => 'Yeni Mezun',
            'last_name' => 'Güncel',
            'phone' => '',
            'educations' => [
                ['id' => $educationId, 'city' => 'Samsun', 'institution_name' => 'Hitit Üniversitesi', 'faculty' => null, 'department' => 'Tıp'],
                ['id' => null, 'city' => null, 'institution_name' => 'Anadolu Üniversitesi', 'faculty' => null, 'department' => null],
            ],
            'company' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('graduates', ['id' => $graduate->id, 'graduation_number' => '11']);
        $this->assertDatabaseHas('people', ['id' => $graduate->person_id, 'phone' => null]);
        $this->assertDatabaseHas('person_educations', ['id' => $educationId, 'city' => 'Samsun']);
        $this->assertDatabaseHas('person_educations', ['institution_name' => 'Anadolu Üniversitesi']);
        $this->assertEquals(2, $graduate->person->educations()->count());
        // Şirket temizlenince iş kaydı boş şirketle kalmamalı, mevcut kayıt korunur
        $this->assertDatabaseHas('person_employments', ['company_name' => 'Hastane']);

        $personId = $graduate->person_id;
        $this->actingAs($user)->delete("/mezunlar/{$graduate->id}")->assertRedirect();
        $this->assertDatabaseMissing('graduates', ['id' => $graduate->id]);
        $this->assertDatabaseMissing('people', ['id' => $personId]);
        $this->assertDatabaseMissing('person_educations', ['person_id' => $personId]);
    }

    public function test_mezun_vcf_fotografli_indirilir(): void
    {
        $user = User::factory()->create();

        $img = imagecreatetruecolor(100, 100);
        imagefill($img, 0, 0, imagecolorallocate($img, 100, 150, 200));
        $tmp = sys_get_temp_dir().'/mezun-foto.jpg';
        imagejpeg($img, $tmp, 90);
        imagedestroy($img);
        Storage::disk('public')->put('graduates/1.jpg', file_get_contents($tmp));
        @unlink($tmp);

        $this->makeGraduate(2009, '4', 'Burcu Dipdağ', [
            'phone' => '5548339382',
            'email' => 'burcu@ornek.com',
            'photo_path' => 'graduates/1.jpg',
            'education' => ['institution_name' => 'Hitit Üniversitesi', 'department' => 'Türk Dili ve Edebiyatı'],
            'company' => 'Artı Eğitim Kurumları',
        ]);
        $this->makeGraduate(2009, '5', 'Telefonsuz Mezun');

        $response = $this->actingAs($user)->get('/mezunlar/vcf?year=2009&photo=1');
        $response->assertOk();
        $response->assertDownload('mezunlar-2009-2.vcf');

        $content = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertEquals(2, substr_count($content, 'BEGIN:VCARD'));
        $unfolded = str_replace("\r\n ", '', $content);

        $this->assertStringContainsString('FN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:2009004=20Burcu=20Dipda=C4=9F', $unfolded);
        $this->assertStringContainsString('N;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Dipda=C4=9F;2009004=20Burcu;;;', $unfolded);
        $this->assertStringContainsString('TEL;CELL:05548339382', $unfolded);
        $this->assertStringContainsString('EMAIL;HOME:burcu@ornek.com', $content);
        $this->assertStringContainsString('ORG;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Hitit=20=C3=9Cniversitesi', $unfolded);
        $this->assertStringContainsString('TITLE;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:T=C3=BCrk=20Dili=20ve=20Edebiyat=C4=B1', $unfolded);
        $this->assertStringContainsString('NOTE;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Art=C4=B1=20E=C4=9Fitim=20Kurumlar=C4=B1', $unfolded);
        $this->assertStringContainsString('CATEGORIES;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Mezun=202009', $content);
        $this->assertEquals(1, substr_count($content, 'PHOTO;ENCODING=b;TYPE=JPEG:'));
        $this->assertEquals(1, substr_count($content, 'TEL;CELL:'));
        $this->assertStringContainsString('Telefonsuz', $content);

        Storage::disk('public')->delete('graduates/1.jpg');
    }
}
