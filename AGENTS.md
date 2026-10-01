# AGENTS.md — OAL Kelebek

Bu dosya `~/Belgeler/OAL_Kelebek` projesinin ana geliştirme rehberidir.

Amaç, OpenCode / Muse gibi bir kodlama ajanına yalnızca örneğin:

> `AGENTS.md içindeki 2. adımı tamamen uygula. Sonraki adıma geçme.`

demek ve ilgili adımı eksiksiz uygulatmaktır.

---

# 0. GENEL KURALLAR

**Durum: ✅ Uyuluyor**

## Proje

- Proje kökü: `~/Belgeler/OAL_Kelebek`
- Uygulama adı: `Kelebek`
- Production adresi: `https://awa.madematik.com`
- Uygulama kurum içi kullanılacaktır.
- Başlangıçta yalnızca 1–2 yetkili yönetici kullanacaktır.
- Öğrenci, veli veya öğretmen portalı yapılmayacaktır.
- Öğrenci/veli tarafında web üzerinden sınav yeri duyurusu yapılmayacaktır.
- Temel amaç: sınav oturma planını hazırlamak, gerektiğinde elle düzenlemek ve gerekli çıktıları almaktır.
- İleride mezun rehberi, SMS export ve kişi yönetimi genişletilecektir.

## Teknoloji

- Laravel 13
- PHP 8.3+
- Vue 3
- TypeScript
- Inertia
- Tailwind CSS
- Vite
- MySQL
- pnpm

Uygulama Laravel monolith olacaktır.

Ayrı frontend, ayrı REST API, NestJS, Next.js, Docker, microservice veya gereksiz servisler oluşturma.

## Dil ve zaman dilimi

- Uygulama dili: Türkçe
- `APP_LOCALE=tr`
- `APP_FALLBACK_LOCALE=tr`
- Timezone: `Europe/Istanbul`

## Tasarım yaklaşımı

Tasarım sade, modern, temiz ve yönetim uygulamasına uygun olsun.

- Masaüstü kullanımına öncelik ver.
- Tablet ve mobilde bozulmayan responsive yapı kur.
- Gereksiz görsel karmaşa ve animasyon kullanma.
- Belirli buton, combobox, sidebar veya piksel yerleşimlerini zorunlu kılma.
- İşlevi doğru sağlayan modern arayüzü kendin tasarla.
- Büyük UI frameworkleri ekleme; ihtiyaç yoksa Tailwind yeterlidir.

## Kod yaklaşımı

- Gereksiz abstraction oluşturma.
- Overengineering yapma.
- Gerekmiyorsa Repository, Domain, DTO, Action, Module gibi katmanlar ekleme.
- Laravel ve Vue standartlarına uygun, sade ve okunabilir kod yaz.
- İleride genişletmeye açık ol.
- Bugünden gereksiz SaaS karmaşıklığı kurma.
- Mevcut çalışan Kelebek özelliklerini yeni veri modeline uyarlarken kırma.

## Test yaklaşımı

Gereksiz test aşamaları oluşturma.

Her adımda yalnızca kritik kontrolleri yap:

- Migration çalışıyor mu?
- Build başarılı mı?
- İlgili temel CRUD / akış çalışıyor mu?
- Dağıtım algoritmasının kritik kuralları korunuyor mu?
- Import işlemi temel hataları yakalıyor mu?
- Çıktı ekranı yazdırılabiliyor mu?

Her küçük metoda ayrı test yazma.
Gereksiz mock, fixture, test factory veya kapsamlı test matrisi oluşturma.

Kritik iş mantığı olan dağıtım algoritması için sınırlı ama anlamlı otomatik testler yazılabilir.

## Her adımın sonunda

- Yaptığın işleri kısa şekilde özetle.
- Değişen önemli dosyaları belirt.
- Varsa hata/uyarı belirt.
- Gerekli kritik kontrolü çalıştır.
- Sonraki adıma kendiliğinden geçme.

---

# 1. PROJE TEMELİNİ HAZIRLA

**Durum: ✅ Yapıldı**

`~/Belgeler/OAL_Kelebek` Laravel projesinin doğrudan kökü olacak.

Ek `kelebek/` alt klasörü oluşturma.

Laravel 13 + Vue 3 + TypeScript + Inertia + Tailwind + Vite kur.

Frontend için pnpm kullan.

`.env` local geliştirme için hazır olsun.

MySQL kullan:

- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=awa`

Locale ve timezone:

- `APP_LOCALE=tr`
- `APP_FALLBACK_LOCALE=tr`
- `Europe/Istanbul`

Masaüstündeki:

`~/Masaüstü/kelebek.png`

dosyasını:

`public/favicon.png`

olarak kopyala ve favicon olarak kullan.

Başlangıç ekranı yalnızca sade bir kurulum doğrulama ekranı olabilir.

Bu adımda:
- özel veritabanı tabloları oluşturma
- authentication geliştirme
- öğrenci/salon sistemi yapma
- Excel import yapma
- fotoğraf sistemi yapma
- dağıtım algoritması yapma
- dashboard geliştirme
- deployment yapma

Kritik kontrol:
- `php artisan about`
- `pnpm build`

---

# 2. VERİTABANI MİMARİSİNİ NİHAİ HALE GETİR VE MEVCUT KELEBEK BAĞLANTILARINI UYARLA

**Durum: ✅ Yapıldı**

Bu adımda mevcut proje production'da olmadığı için eski migration'ları parça parça `add_*` migration'larıyla büyütme.

Mevcut create migration'larını nihai şemaya göre düzenle.
Yeni tablolar için temiz create migration'ları oluştur.
Sonunda `php artisan migrate:fresh` ile sıfırdan kurulabilsin.

Mevcut Kelebek tablolarında bulunan alanları tekrar oluşturma.
Mevcut migration ve modelleri önce incele, sonra nihai şemayı uygula.

## 2.1 Mevcut Kelebek tabloları korunacak

Aşağıdaki yapıların ana amacı korunmalı:

- `schools`
- `users`
- `school_user`
- `academic_years`
- `branches`
- `rooms`
- `seats`
- `exam_weeks`
- `exam_week_branches`
- `exam_week_rooms`
- `seating_plans`
- `seating_assignments`
- `exams`

Bu tabloları gereksiz yere yeniden tasarlama.

Ancak mevcut migration'larda sonradan eklenmiş `school_id`, `role` vb. alanlar varsa, temiz mimari için doğrudan ilgili create migration içine taşı.

## 2.2 Ortak kişi tablosu: `people`

Yeni tablo:

```text
people
------
id
school_id
first_name nullable
last_name nullable
full_name
phone nullable
email nullable
address nullable
photo_path nullable
timestamps
```

Kurallar:
- `school_id` mevcut `schools` tablosuna bağlı olacak.
- `full_name` zorunlu.
- `first_name` ve `last_name` nullable.
- `phone`, `email`, `address`, `photo_path` nullable.
- Telefon ve e-posta unique OLMAYACAK.
- Aynı kişi birden fazla role sahip olabilir.
- İletişim ve fotoğraf bilgisi kişi düzeyinde tutulacak.

## 2.3 `students`

Mevcut `students` tablosunu yeni yapıya göre düzenle.

Nihai temel yapı:

```text
students
--------
id
school_id
person_id
is_active
timestamps
```

Buradan kaldır:
- `full_name`
- `photo_path`
- `academic_year_id`
- `branch_id`
- `school_number`

Çünkü:
- ad/fotoğraf -> `people`
- akademik yıl/şube/okul numarası -> `student_enrollments`

`person_id` -> `people.id`

## 2.4 `student_enrollments`

Yeni tablo:

```text
student_enrollments
-------------------
id
student_id
academic_year_id
branch_id
school_number
status
timestamps
```

Amaç:
Bir öğrencinin yıllara göre şube ve okul numarası geçmişini tutmak.

Örnek:
- 2026-2027 -> 9A -> 145
- 2027-2028 -> 10B -> 145 veya farklı numara
- daha sonraki bir yılda 145 başka öğrenciye verilebilir

Kritik unique kural:

```text
UNIQUE (academic_year_id, school_number)
```

Okul numarası global unique OLMAYACAK.

Aynı öğrenci aynı akademik yılda birden fazla enrollment kaydına sahip olmamalı:

```text
UNIQUE (student_id, academic_year_id)
```

`status` sade tutulabilir:
- active
- transferred
- graduated
- inactive

## 2.5 `guardians`

Yeni tablo:

```text
guardians
---------
id
person_id
timestamps
```

Kişisel bilgiler `people` üzerinden gelir.

## 2.6 `student_guardian`

Yeni pivot tablo:

```text
student_guardian
----------------
student_id
guardian_id
relationship
is_primary
timestamps
```

`relationship` örnekleri:
- anne
- baba
- veli
- vasi

Bir öğrenci birden fazla veli/ebeveyn kaydına sahip olabilir.
Bir kişi birden fazla öğrencinin velisi olabilir.

## 2.7 `teachers`

Yeni tablo:

```text
teachers
--------
id
person_id
is_active
timestamps
```

Öğretmenin:
- adı
- telefon
- e-posta
- adres
- fotoğraf

bilgileri `people` üzerinden gelir.

Henüz öğretmen admin ekranı yapma.

## 2.8 `graduates`

Yeni tablo:

```text
graduates
---------
id
person_id
student_id nullable
graduation_year
graduation_number
notes nullable
timestamps
```

Kurallar:
- `student_id` nullable.
- Eski mezunlar için `student_id` NULL olabilir.
- Mevcut öğrenci ileride mezun olduğunda ilgili student kaydına bağlanabilir.
- Üniversite/fakülte/bölüm burada tutulmayacak.
- İş bilgisi burada tutulmayacak.

Unique:
```text
UNIQUE (graduation_year, graduation_number)
```

## 2.9 `person_educations`

Yeni tablo:

```text
person_educations
-----------------
id
person_id
institution_name
faculty nullable
department nullable
degree_level nullable
start_year nullable
graduation_year nullable
notes nullable
timestamps
```

Bir kişi birden fazla eğitim kaydına sahip olabilir.

Bu sadece mezunlar için değil, öğretmen ve diğer kişi türleri için de kullanılabilir.

## 2.10 `person_employments`

Yeni tablo:

```text
person_employments
------------------
id
person_id
company_name
job_title
city nullable
start_year nullable
end_year nullable
is_current
notes nullable
timestamps
```

Bir kişinin birden fazla iş geçmişi olabilir.

Bu tablo V1 çekirdek mimarisine dahildir; erteleme.

## 2.11 Mevcut Kelebek modellerini yeni yapıya uyarla

İlişkileri düzgün kur:

- School -> People
- Person -> Student
- Person -> Guardian
- Person -> Teacher
- Person -> Graduate
- Person -> Educations
- Person -> Employments
- Student -> Person
- Student -> Enrollments
- Student -> Guardians
- Enrollment -> AcademicYear
- Enrollment -> Branch
- Graduate -> Person
- Graduate -> Student nullable

## 2.12 Mevcut Kelebek kod bağlantılarını güncelle

Mevcut çalışan Kelebek admin ve iş mantığı yeni yapıya uyarlanacak.

Özellikle incele ve güncelle:

- `Student.php`
- `AcademicYear.php`
- `Branch.php`
- `StudentController.php`
- `StudentImportController.php`
- `StudentPhotoController.php`
- `SchoolScope.php`
- `SeatingDistributionService.php`
- `DistributionController.php`
- `PrintController.php`
- ilgili Vue öğrenci/import/çıktı ekranları

Mevcut kodda doğrudan:

```text
student.branch_id
student.academic_year_id
student.school_number
student.full_name
student.photo_path
```

kullanımları varsa yeni ilişkilere göre değiştir.

Yeni erişim mantığı:

```text
student -> person -> full_name
student -> person -> photo_path
student -> active/current enrollment -> branch
student -> active/current enrollment -> academic_year
student -> active/current enrollment -> school_number
```

Sınav haftasının akademik yılına göre doğru enrollment seçilmeli.

Dağıtım motoru öğrencinin şubesini artık `student_enrollments` üzerinden bulmalı.

Fotoğraf erişimi:

```text
student -> person -> photo_path
```

üzerinden çalışmalı.

## 2.13 Bu adımda YAPMA

Henüz:
- Mezunlar admin ekranı yapma
- VCF oluşturma ekranı yapma
- SMS/VeraSMS export ekranı yapma
- Veli yönetim ekranı yapma
- Öğretmen yönetim ekranı yapma
- Mezun eğitim/iş geçmişi admin ekranı yapma
- Public rehber yapma

Bu adım sadece:
1. veri tabanı mimarisi
2. model ilişkileri
3. mevcut Kelebek sisteminin yeni modele uyarlanması

içindir.

## 2.14 Kritik kontrol

- `php artisan migrate:fresh`
- mevcut login çalışmalı
- mevcut öğrenci listesi ekranı çalışmalı
- öğrenci import akışı çalışmalı
- fotoğraf import/gösterim akışı çalışmalı
- dağıtım motoru çalışmalı
- çıktı ekranları çalışmalı
- `pnpm build` başarılı olmalı

Gereksiz test paketi oluşturma.

Sonraki adıma geçme.

---

# 3. BASİT YÖNETİCİ GİRİŞİ

**Durum: ✅ Yapıldı**

Uygulama yalnızca yetkili kullanıcılar tarafından kullanılacak.

Gereksinimler:
- Login
- Logout
- Register kapalı
- Şifre sıfırlama zorunlu değil
- Sosyal login yok
- Gereksiz rol sistemi yok
- Bütün yönetim sayfaları auth ile korunmalı

Başlangıçta 1–2 kullanıcı yeterli.

Mevcut projede bu özellik zaten varsa yeniden oluşturma; yalnızca çalıştığını doğrula.

---

# 4. ANA YÖNETİM ARAYÜZÜ

**Durum: ✅ Yapıldı**

Sade ve modern admin arayüzü.

Ana bölümler:
- Öğrenciler
- Şubeler
- Salonlar
- Sınav Haftaları
- Dağıtım
- Çıktılar / Raporlar

İleride:
- Mezunlar
- Rehber / Kişiler
- SMS Export

eklenebilir.

Bu adımda tasarım ayrıntılarını katılaştırma.

---

# 5. AKADEMİK YIL VE ŞUBE YÖNETİMİ

**Durum: ✅ Yapıldı**

Academic year CRUD.

Branch CRUD.

Gereksinimler:
- ekleme
- düzenleme
- aktif/pasif
- aktif akademik yılı seçebilme

Şube örnekleri:
- 9A
- 9B
- 10C
- 12D

Aynı academic year içinde duplicate şube olmasın.

---

# 6. ÖĞRENCİ YÖNETİMİ

**Durum: ✅ Yapıldı** — tek formdan ad/soyad/ad soyad/telefon/e-posta/adres/fotoğraf/numara/şube/aktif yönetimi çalışıyor.

Öğrenci yönetimi yeni person-centric modele göre çalışmalı.

Admin kullanıcı tek form görmeli; backend gerekli tabloları yönetmeli.

Gerekli bilgiler:
- ad
- soyad
- ad soyad
- telefon
- e-posta
- adres
- fotoğraf
- okul numarası
- şube
- akademik yıl
- aktif/pasif

Arayüz kullanıcıya tablo yapısını hissettirmesin.

Fotoğraf yoksa:
`Foto yok`

göster.

---

# 7. EXCEL ÖĞRENCİ İÇE AKTARMA

**Durum: ✅ Yapıldı**

Excel import V1 için zorunlu.

`.xlsx` ve mümkünse `.xls` destekle.

Import artık yalnız öğrenci değil, kişi ve enrollment yapısını da günceller.

Temel alanlar:
- şube
- okul no
- ad soyad
- öğrenci telefon
- öğrenci e-posta
- adres
- veli adı soyadı
- veli telefon
- veli e-posta

Başlık örneklerini normalize et:
- Okul No
- Öğrenci No
- Numara
- Ad Soyad
- Adı Soyadı
- Sınıf
- Şube
- Sınıfı

Şube formatlarını normalize et:
- `9A`
- `9/A`
- `9-A`
- `9 A`

Import sırasında oluştur/güncelle:
- `people` öğrenci kaydı
- `students`
- `student_enrollments`
- gerekiyorsa veli için `people`
- `guardians`
- `student_guardian`

Kurallar:
- okul numarası o akademik yıl içinde unique
- boş okul no hata
- boş ad soyad hata
- bulunamayan şube hata
- boş satırları atla

Sonuç özeti:
- toplam
- eklendi
- güncellendi
- atlandı
- hatalı

---

# 8. TOPLU ÖĞRENCİ FOTOĞRAFI YÜKLEME

**Durum: ✅ Yapıldı**

Fotoğraf dosya adı öğrenci numarasıdır:

- `145.jpg`
- `111.jpg`
- `1024.jpg`

Fotoğraf eşleştirme ilgili akademik yıl enrollment'ındaki okul numarasına göre yapılmalı.

Fotoğraf `people.photo_path` alanına yazılmalı.

Destek:
- çoklu dosya seçimi
- mümkünse ZIP

Büyük görseller:
- resize
- compress

Orijinal dev dosyaları saklama.

Fotoğraf yoksa:
`Foto yok`

---

# 9. SALON VE KOLTUK DÜZENİ

**Durum: ✅ Yapıldı**

Salon CRUD.

Seat:
- room_id
- row
- column
- label nullable
- is_active
- sort_order nullable

Koltuk hiçbir şube veya sınıf seviyesiyle ilişkili olmayacak.

Salon kapasitesi aktif koltuk sayısından hesaplanmalı.

---

# 10. SINAV HAFTASI

**Durum: ✅ Yapıldı**

Bir sınav haftasında kullanıcı:

- dağıtıma dahil şubeleri seçebilmeli
- kullanılmasına izin verilen salonları seçebilmeli

Bazı şubeler o hafta hariç tutulabilir.
Bazı salonlar o hafta hariç tutulabilir.

Özet:
- dahil öğrenci sayısı
- izin verilen salon sayısı
- toplam kapasite

---

# 11. DAĞITIM MOTORU

**Durum: ✅ Yapıldı** — kesin kural, yumuşak tercih (yalnız kalanın yanına şube arkadaşı, çifti bozmadan), minimizasyon, kapasite kontrolü ve kritik testler tamam.

Backend service içinde tutulmalı.

Kritik kurallar:

## Kesin kural
Aynı seviyeden iki öğrenci yatay yan yana oturmamalı.

Yatay komşu:
- aynı row
- column farkı 1

## Serbest durum
Aynı şubeden öğrenciler gerekirse arka arkaya oturabilir.

## Yumuşak tercih
Mümkünse aynı salonda aynı şubeden en az iki öğrenci bulunsun.

## Salon minimizasyonu
Mümkün olan en az sayıda salon kullanılmalı.

Örnek:
- 300 öğrenci
- 12 salon
- her biri 30 kişi

Önce 10 salon dene.
Olmazsa 11.
Olmazsa 12.

Salon kapasiteleri farklıysa:
- minimum salon sayısı
- mümkün olduğunca az boş koltuk

hedeflenmeli.

Sadece exam week içinde izin verilen salonlar kullanılabilir.

Kapasite yetersizse dağıtımı başlatma.

Kontrolsüz sonsuz döngü kullanma.

Kritik testler:
- aynı seviye yatay yan yana gelmiyor
- kapasite yetersizken hata
- minimum salon yaklaşımı
- her öğrenci bir kez
- her koltuk bir kez

---

# 12. DAĞITIM EKRANI

**Durum: ✅ Yapıldı**

Dağıtım öncesi özet:
- katılan şubeler
- öğrenci sayısı
- salonlar
- kapasite
- tahmini minimum salon

Dağıtım sonucu salon bazında göster.

Aynı sınav haftası için yeniden dağıtım yapılabilsin.

Draft/final yaklaşımı kullanılabilir.

---

# 13. MANUEL SALON/KOLTUK DÜZENLEME

**Durum: ✅ Yapıldı**

Destekle:

## Aynı salon
- boş koltuğa taşıma
- öğrenci takası
- drag & drop

## Salonlar arası
- öğrenci taşıma
- salon değiştirme
- takas

Aynı şube yan yana gelirse uyarı göster.

Kullanıcı:
`Yine de uygula`

diyebilsin.

---

# 14. OTURMA PLANI

**Durum: ✅ Yapıldı**

Salon bazında göster.

Her koltukta:
- okul no
- ad soyad
- şube
- fotoğraf varsa fotoğraf

Fotoğraf yoksa:
`Foto yok`

Fotoğraf göster/gizle seçeneği veri değiştirmemeli.

---

# 15. YAZDIRILABİLİR ÇIKTILAR

**Durum: ✅ Yapıldı**

A4 uyumlu.

En az:
- salon oturma planı
- şube bazında sınav yeri listesi
- salon öğrenci listesi
- dağılım özeti
- genel özet

Fotoğraflı/fotoğrafsız seçenek.

İlk aşamada browser print yeterlidir.

Gereksiz server-side PDF paketi ekleme.

---

# 16. SINAV TARİHLERİ / PROGRAMI

**Durum: ✅ Yapıldı**

Mevcut eski Excel uygulamasında sınav tarihleri yalnız duyuru amaçlıydı.

V1 ana işlevi değildir.

Mevcut:
- `exam_weeks`
- `exams`

yapısı ileride tarih/saat/açıklama destekleyecek şekilde hazır kalmalı.

Dağıtım motoru sınav tarihine bağımlı olmasın.

---

# 17. MEZUN / SMS / VCF ALTYAPISI

**Durum: ✅ Yapıldı** — altyapı + VCF indir + Excele Dışa Aktar + Mezunlar listesi/VCF tamam; SMS export bilerek yok.

Bu bölümde SADECE veri mimarisi hazırdır.

Henüz admin ekranı veya export yapma.

Mevcut tablolar:
- `people`
- `guardians`
- `teachers`
- `graduates`
- `person_educations`
- `person_employments`

Bu yapı ileride şu özellikleri destekleyecek:

## SMS
Öğrenci ve veli iletişim bilgileri:
- `people.phone`
- `people.email`

üzerinden alınacak.

VeraSMS export ileride anlık üretilecek.
Şimdilik `sms_exports` veya `sms_logs` tablosu oluşturma.

## VCF / Mezun rehberi
Mezun VCF çıktısı ileride:
- `graduates`
- `people`
- `person_educations`
- `person_employments`

üzerinden anlık üretilecek.

Şimdilik `vcf_exports` tablosu oluşturma.
VCF ekranı yapma.

---

# 18. MEVCUT FOTOĞRAFLARI BAĞLAMA

**Durum: ✅ Yapıldı** — 1151 mezun + öğrenci fotoğrafları `people.photo_path` üzerinden bağlı.

Fotoğraf klasörü varsa öğrenci numarası üzerinden eşleştir.

Örnek:
- `145.jpg`
- `111.jpg`

Fotoğraf ilgili `people.photo_path` alanına bağlanmalı.

Fotoğraf yoksa hata verme.

---

# 19. KULLANILABİLİRLİK VE HATA MESAJLARI

**Durum: ✅ Yapıldı**

Türkçe ve anlaşılır mesajlar kullan.

Özellikle:
- Excel import hatası
- duplicate öğrenci
- bulunamayan şube
- kapasite yetersiz
- dağıtım çözümsüz
- fotoğraf eşleşmedi
- manuel kural ihlali
- kayıt başarısız

Teknik exception'ı doğrudan kullanıcıya gösterme.

---

# 20. RESPONSIVE SON KONTROL

**Durum: ⚠️ Kısmen** — viewport + responsive sınıflar var; formel cihaz testi yapılmadı.

Öncelik:
1. Masaüstü
2. Tablet
3. Mobil

Mobilde tüm yönetim fonksiyonları kusursuz olmak zorunda değil, ama bozulmamalı.

---

# 21. GIT / GITHUB

**Durum: ✅ Yapıldı**

Bu adımı yalnızca kullanıcı isterse uygula.

- git init
- ilk temiz çalışan commit
- kullanıcı isterse GitHub private repository
- önerilen repo adı: `OAL_Kelebek`

Commit etme:
- `.env`
- `vendor`
- `node_modules`
- hassas dosyalar

Kullanıcı istemeden remote oluşturma veya push yapma.

---

# 22. ÖĞRENCİ BİLGİ FORMU / EVRAK SİSTEMİ (İLERİDE)

**Durum: ⏳ Bekliyor** — kullanıcı düşünüp onaylayınca başlanacak. Kodlama yapılmadı.

## Fikir

Google Form yerine uygulamanın içinde (örn. `awa.madematik.com/form`) öğrencilerin giriş yapıp doldurduğu form sistemi. İlk evrak: Öğrenci Bilgi Formu (~61 alan, 5 sayfa: öğrenci + veli + anne + baba + notlar).

## Kararlaştırılanlar

- Excel ile aktarma yok; öğrenci giriş yapıp formu kendisi doldurur.
- Ad soyad, sınıf, okul no sabit/salt-okunur; kalanını öğrenci doldurup Kaydet ile veritabanına işler.
- Profil öğrenci başına tek satır, her yıl üzerine yazılır (upsert, yıllık tarihçe yok).
- Adres: `awa.madematik.com` altında herkese açık route; `madematik.com` şart değil.
- Yeni tablolar: `student_profiles` (~30 alan + notes), `guardians` tablosuna +8 kolon (eğitim, meslek, doğum yeri/tarihi, öz mü, sağ mı, engel, hastalık). `document_types` + yayın aralığı ile sonraki evraklar aynı hatta eklenir.

## Bilinen sıkıntılar (çözülmeden başlama)

1. Şifre dağıtım/destek yükü (300+ öğrenci, sıfırlama ekranı gerekir).
2. Başkası yerine doldurma riski.
3. Hassas veri (din/sağlık) + KVKK: aydınlatma ve rıza metni şart.
4. Onay kuyruğu: öğrenci verisi admin onayı olmadan profile işlenmemeli.
5. Üçüncü şahıs (anne/baba) verisinin doğruluğu ve rızası.
6. 60 alan tek oturuşta: taslak/otomatik kaydet gerekir.
7. Mobil-first form şart (öğrenciler telefondan girer).
8. Yıl devri: mezun hesap kapama + yeni kayıt hesap açma rutini.
9. Öğrenci guard ile admin paneli yetki ayrımı (sızma olmamalı).
10. Son gün yığılması (paylaşımlı hosting) + kademeli yayın.
11. Yayın ortasında soru değişirse versiyonlama.
12. Hatırlatma kanalı yok (SMS yok), takip manuel.

En kritik üçü: onay kuyruğu, yetki ayrımı, şifre operasyonu.

---

# TEMEL İŞ KURALLARI — KISA REFERANS

## Terimler

- Şube = öğrencinin gerçek sınıfı (`9A`, `10C`)
- Salon = sınava girdiği oda
- Koltuk = salon içindeki fiziksel oturma yeri

## Kişiler

Tüm kişi tipleri ortak `people` kaydına dayanır.

Ortak bilgiler:
- first_name
- last_name
- full_name
- phone
- email
- address
- photo_path

Rol örnekleri:
- öğrenci
- anne
- baba
- veli
- vasi
- öğretmen
- mezun

Aynı kişi birden fazla role sahip olabilir.

## Öğrenci kayıt geçmişi

Okul numarası öğrencinin kalıcı kimliği değildir.

Okul numarası yalnız o akademik yıl içinde unique'tir.

Bu yüzden:
- okul numarası `student_enrollments` içinde tutulur
- unique `(academic_year_id, school_number)`

## Dağıtım

- Her öğrenci her uygun salonda oturabilir.
- Renk/seviye tabanlı koltuk kısıtı yoktur.
- Aynı şube yatay yan yana gelmemelidir.
- Aynı şube arka arkaya oturabilir.
- Mümkünse aynı salonda aynı şubeden en az iki öğrenci olsun.
- Mümkün olan en az salon kullanılsın.
- Bazı şubeler exam week dışında bırakılabilir.
- Bazı salonlar exam week dışında bırakılabilir.

## Manuel düzenleme

- aynı salon içinde drag & drop
- boş koltuğa taşıma
- öğrenci takası
- salonlar arası taşıma
- salonlar arası takas
- kural ihlalinde uyarı
- kullanıcı isterse ihlali kabul edebilir

## Fotoğraflar

- dosya adı öğrenci numarası
- büyük fotoğraf resize/compress
- kişi fotoğrafı `people.photo_path`
- fotoğraf yoksa `Foto yok`

## Çıktılar

- fotoğraflı/fotoğrafsız oturma planı
- salon listesi
- şube bazında sınav yeri listesi
- dağılım özeti
- genel özet
- A4 yazdırma

## Mezun sistemi

- graduate temel mezuniyet kaydı
- birden fazla eğitim -> `person_educations`
- birden fazla iş geçmişi -> `person_employments`
- VCF ileride anlık üretilecek
- şu aşamada VCF ekranı yok

## SMS

- öğrenci/veli telefon ve e-posta `people` üzerinden
- VeraSMS export ileride anlık üretilecek
- şu aşamada SMS admin/export ekranı yok
