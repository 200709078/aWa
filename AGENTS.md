# AGENTS.md — OAL Kelebek

Bu dosya, `~/Belgeler/OAL_Kelebek` projesinin geliştirme planıdır.

Amaç: OpenCode / Muse benzeri bir kodlama ajanına yalnızca örneğin:

> `AGENTS.md içindeki 1. adımı tamamen uygula. Sonraki adıma geçme.`

demek ve ilgili adımın eksiksiz uygulanmasını sağlamaktır.

---

# 0. Genel kurallar

## Proje

- Proje kökü: `~/Belgeler/OAL_Kelebek`
- Uygulama adı: `Kelebek`
- Production adresi: `https://kelebek.madematik.com`
- Uygulama kurum içi kullanılacaktır.
- Başlangıçta yalnızca 1–2 yetkili yönetici kullanacaktır.
- Öğrenci, veli veya öğretmen portalı yapılmayacaktır.
- Öğrencilere veya velilere web üzerinden sınav yeri duyurusu yapılmayacaktır.
- Temel amaç: sınav oturma planını hazırlamak, gerekirse elle düzenlemek ve gerekli çıktıları almaktır.

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

- Uygulama dili doğrudan Türkçe olacaktır.
- Locale: `tr`
- Fallback locale: `tr`
- Timezone: `Europe/Istanbul`

## Tasarım yaklaşımı

Tasarım sade, modern, temiz ve yönetim uygulamasına uygun olsun.

- Masaüstü kullanımına öncelik ver.
- Tablet ve mobilde bozulmayan responsive yapı kur.
- Kullanıcı arayüzünü gereksiz detaylarla kalabalıklaştırma.
- Butonların, tabloların, formların veya kontrollerin yerlerini bu dosyada tarif edilenlerden daha fazla katılaştırma.
- İşlevi doğru sağlayan modern tasarımı kendin oluştur.
- Gereksiz animasyon kullanma.
- Büyük UI frameworkleri ekleme; ihtiyaç yoksa Tailwind yeterlidir.

## Kod yaklaşımı

- Gereksiz abstraction oluşturma.
- Overengineering yapma.
- Gerekmiyorsa Repository, Domain, DTO, Action, Module gibi katmanlar ekleme.
- Laravel ve Vue standartlarına uygun, sade ve okunabilir kod yaz.
- İleride genişletmeye açık ol ama bugünden çok okullu SaaS karmaşıklığı kurma.
- Yine de veri modeli gelecekte başka okul/kullanıcı eklenmesini tamamen engellemesin.

## Test yaklaşımı

Gereksiz test aşamaları oluşturma.

Her adımda yalnızca kritik kontrolleri yap:

- Migration çalışıyor mu?
- Build başarılı mı?
- İlgili temel CRUD / akış gerçekten çalışıyor mu?
- Dağıtım algoritmasının kritik kuralları korunuyor mu?
- Import işlemi temel hataları yakalıyor mu?
- Çıktı ekranı yazdırılabiliyor mu?

Her küçük metoda ayrı unit test yazma.
Gereksiz mock, fixture, test factory veya kapsamlı test matrisi oluşturma.

Kritik iş mantığı olan dağıtım algoritması için sınırlı ama anlamlı otomatik testler yazılabilir.

## Her adımın sonunda

- Yaptığın işleri kısa şekilde özetle.
- Değişen önemli dosyaları belirt.
- Varsa hata/uyarı belirt.
- Gerekli kritik kontrolü çalıştır.
- Sonraki adıma kendiliğinden geçme.

---

# 1. Proje temelini hazırla

`~/Belgeler/OAL_Kelebek` klasörü Laravel projesinin doğrudan kökü olacak.

Ek bir `kelebek/` alt klasörü oluşturma.

Laravel 13 + Vue 3 + TypeScript + Inertia + Tailwind + Vite kur.

Frontend için pnpm kullan.

`.env` dosyasını local geliştirme için hazırla.

MySQL kullan:

- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=oal_kelebek`

Yerel kullanıcı adı/şifre mevcut sisteme göre ayarlanabiliyorsa ayarla; production bilgisi ekleme.

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

Başarılı olmalı.

---

# 2. Veritabanını ve temel veri modelini oluştur

MySQL içinde:

`oal_kelebek`

isimli veritabanını kullan.

Aşağıdaki ana tabloları oluştur.

## users

Laravel kullanıcı tablosunu sade yönetici girişine uygun kullan.

Temel alanlar:

- id
- name
- username veya email
- password
- timestamps

Register sistemi gerekmeyecek.

## academic_years

- id
- name — örn. `2026-2027`
- is_active
- timestamps

## branches

Öğrencinin gerçek şubesi.

Örn: `9A`, `9B`, `10C`

Alanlar:

- id
- academic_year_id
- name
- grade_level
- section
- is_active
- timestamps

Aynı academic year içinde branch adı unique olmalı.

## students

- id
- academic_year_id
- branch_id
- school_number
- full_name
- photo_path nullable
- is_active
- timestamps

`school_number` aynı academic year içinde unique olmalı.

## rooms

Sınav salonları.

- id
- name
- description nullable
- is_active
- sort_order
- timestamps

## seats

Salon içindeki fiziksel koltuklar.

- id
- room_id
- row
- column
- label nullable
- is_active
- sort_order nullable
- timestamps

Koltuk, herhangi bir sınıf seviyesi veya renkle ilişkili olmayacak.

Aynı room içinde `row + column` unique olmalı.

## exam_weeks

- id
- academic_year_id
- name
- description nullable
- starts_at nullable
- ends_at nullable
- is_active
- timestamps

Sınav tarihleri şu an zorunlu değildir; ancak sonradan entegre edilebilmesi için yapı buna uygun olsun.

## exam_week_branches

Bir sınav haftasına hangi şubelerin dahil olduğunu tut.

- exam_week_id
- branch_id

Composite unique kullan.

## exam_week_rooms

Bir sınav haftasında algoritmanın kullanmasına izin verilen salonları tut.

- exam_week_id
- room_id

Composite unique kullan.

Bu tablo “mutlaka kullanılacak salon” anlamına gelmez; yalnızca kullanılmasına izin verilen salonları belirtir.

## seating_plans

Bir sınav haftasında oluşturulan dağıtımı temsil eder.

- id
- exam_week_id
- name nullable
- status
- total_students
- used_room_count
- notes nullable
- created_by nullable
- timestamps

Status sade tutulabilir:
- draft
- final

## seating_assignments

- id
- seating_plan_id
- student_id
- seat_id
- timestamps

Aynı seating plan içinde:
- student bir kez atanmalı
- seat bir kez kullanılmalı

## exams

İleride sınav tarihleri / programı kullanılabilsin diye temel tablo oluştur.

Şimdilik uygulama bu tabloya bağımlı olmayacak.

Alanlar sade olsun:

- id
- exam_week_id
- name
- exam_date nullable
- start_time nullable
- description nullable
- timestamps

Bu yapıyı aktif ürün akışına bağlama.

Migration'ları çalıştır.

İlişkili Eloquent modellerini oluştur.

Gereksiz repository veya service katmanı oluşturma.

Kritik kontrol:

- `php artisan migrate:fresh`
- ilişkiler ve unique constraint'ler doğru çalışmalı.

Sonraki adıma geçme.

---

# 3. Basit yönetici girişi oluştur

Uygulama yalnızca yetkili kullanıcılar tarafından kullanılacak.

Basit authentication sistemi oluştur.

Gereksinimler:

- Login ekranı
- Logout
- Register kapalı
- Şifre sıfırlama zorunlu değil
- Sosyal login yok
- Rol/yetki sistemi yok
- Bütün uygulama sayfaları auth ile korunmalı
- Login dışında public yönetim ekranı olmasın

Başlangıçta tek admin kullanıcı oluşturmak için uygun seeder oluştur.

Default admin bilgilerini production için hard-code etme.

Local geliştirme için `.env` veya seeder üzerinden kolay değiştirilebilir yaklaşım kullan.

Kritik kontrol:

- giriş yapılmadan korumalı sayfaya erişilememeli
- giriş sonrası ana sayfa açılmalı
- logout çalışmalı

Sonraki adıma geçme.

---

# 4. Ana yönetim arayüzünü oluştur

Sade ve modern bir yönetim arayüzü oluştur.

İşlevsel ana bölümler şunlara erişim sağlamalı:

- Öğrenciler
- Şubeler
- Salonlar
- Sınav Haftaları
- Dağıtım
- Çıktılar / Raporlar
- Ayarlar gerekirse sınırlı

Tasarımda belirli buton veya combobox yerleşimlerini zorunlu kılma.

Masaüstünde verimli, mobil/tablette bozulmayan responsive yapı oluştur.

Bu adımda gerçek modüllerin tamamını geliştirme; navigasyon ve temel layout yeterli.

Sonraki adıma geçme.

---

# 5. Akademik yıl ve şube yönetimini oluştur

Academic year CRUD oluştur.

Gereksinimler:

- ekleme
- düzenleme
- aktif/pasif
- aktif akademik yılı seçebilme

Branch CRUD oluştur.

Gereksinimler:

- şube adı
- sınıf seviyesi
- şube harfi/bölümü
- academic year ilişkisi
- aktif/pasif

Örn:
- 9A
- 9B
- 10C
- 12D

Aynı academic year içinde aynı şube iki kez oluşturulmasın.

Kritik kontrol:
- temel CRUD çalışmalı
- unique constraint hatası kullanıcıya anlaşılır gösterilmeli

---

# 6. Öğrenci yönetimini oluştur

Öğrenciler için yönetim ekranı oluştur.

Gereksinimler:

- listeleme
- arama
- şubeye göre filtreleme
- ekleme
- düzenleme
- pasife alma veya silme
- okul numarası
- ad soyad
- şube
- fotoğraf durumu

Fotoğraf yoksa arayüzde:

`Foto yok`

göster.

Öğrenci sayfalarında büyük görsel galeri tasarımı yapma.

Toplu import bir sonraki adımda yapılacak.

---

# 7. Excel öğrenci içe aktarmayı oluştur

Excel öğrenci listesi içe aktarma V1 için zorunludur.

`.xlsx` ve mümkünse `.xls` destekle.

Gerekli paketi seç ve kur.

Akış:

1. Kullanıcı Excel dosyasını yükler.
2. Sistem başlıkları okumaya çalışır.
3. Temel alanları otomatik eşleştirmeye çalışır:
   - okul numarası
   - ad soyad
   - şube
4. Gerekirse kullanıcı sütun eşleştirmesi yapabilir.
5. Önizleme göster.
6. Hatalı satırları açıkça göster.
7. Kullanıcı onayından sonra aktar.

Şu tip sütun isimlerini normalize etmeye çalış:

- Okul No
- Öğrenci No
- Numara
- Ad Soyad
- Adı Soyadı
- Sınıf
- Şube
- Sınıfı

Şube yazımlarını mümkün olduğunca normalize et:

- `9A`
- `9/A`
- `9-A`
- `9 A`

gibi değerleri aynı şubeye dönüştür.

İçe aktarma sırasında:

- boş satırları atla
- okul numarası boşsa hata ver
- ad soyad boşsa hata ver
- şube bulunamazsa hata ver veya açıkça raporla
- duplicate okul numarasını kontrol et

Import sonunda özet göster:

- toplam satır
- eklendi
- güncellendi
- atlandı
- hatalı

Mevcut öğrenciyi güncelleme davranışı açık ve güvenli olsun.

Gereksiz gelişmiş import geçmişi sistemi kurma.

Kritik kontrol:
- küçük örnek dosyada import çalışmalı
- duplicate ve eksik alan hataları yakalanmalı

---

# 8. Toplu öğrenci fotoğrafı yüklemeyi oluştur

Öğrenci fotoğrafları çoğunlukla mevcuttur.

Dosya adları öğrenci numarasıdır:

- `145.jpg`
- `111.jpg`
- `1024.jpg`

Toplu yükleme destekle.

Mümkünse:
- çoklu dosya seçimi
- ZIP yükleme

desteklenebilir.

Dosya adından öğrenci numarasını bul.

Öğrenci ile eşleştir.

Büyük fotoğrafları yükleme sırasında otomatik küçült ve sıkıştır.

Orijinal çok büyük dosyaları olduğu gibi saklama.

Web ve A4 çıktı için yeterli kaliteyi koru.

Dosya formatlarını güvenli biçimde sınırla.

Fotoğraf bulunmayan öğrencilerde:
`Foto yok`

göster.

Yükleme sonunda özet göster:

- eşleşen fotoğraf
- eşleşmeyen dosya
- fotoğrafı olmayan öğrenci
- hatalı dosya

Fotoğraf işleme işlemi çok sayıda dosyada zaman alıyorsa Laravel queue kullanabilirsin.
Başlangıçta Redis kurmak zorunlu değildir; database queue yeterlidir.

Gereksiz karmaşık medya sistemi kurma.

---

# 9. Salon ve koltuk düzeni yönetimini oluştur

Salon CRUD oluştur.

Her salonun fiziksel koltuk düzeni tanımlanabilsin.

Seat verisi `row` ve `column` üzerinden tutulacak.

Amaç:
- yatay komşuluk hesaplanabilsin
- salon planı görsel olarak çizilebilsin
- aktif olmayan koltuklar kullanılmasın

Salon için:

- ad
- açıklama
- aktif/pasif
- sıralama

Koltuk düzeni için sade bir editör oluştur.

Örn. 5 satır × 6 sütun gibi düzenler kolay eklenebilsin.

Tek tek bazı koltukları pasif yapabilmek mümkün olsun.

Koltuk numaralandırması kullanıcıya anlaşılır gösterilsin.

Koltuk herhangi bir şube veya sınıf seviyesine bağlı olmayacak.

Kritik kontrol:
- salon kapasitesi aktif koltuk sayısından hesaplanmalı
- aynı row+column duplicate olamamalı

---

# 10. Sınav haftası yönetimini oluştur

Exam week CRUD oluştur.

Bir sınav haftasında kullanıcı:

- dağıtıma dahil olacak şubeleri seçebilmeli
- kullanılmasına izin verilen salonları seçebilmeli

Bazı şubeler belirli sınav haftalarında hiç dağıtıma dahil olmayabilir.

Bazı salonlar belirli sınav haftalarında hiç kullanılmayabilir.

Kullanıcıya özet göster:

- dahil öğrenci sayısı
- izin verilen salon sayısı
- toplam aktif koltuk kapasitesi

Sınav tarihi/programı alanları şu aşamada ana akışa zorunlu bağlanmasın.

---

# 11. Dağıtım motorunu oluştur

Bu projenin en kritik iş mantığıdır.

Dağıtım motorunu backend tarafında ayrı ve temiz bir service sınıfında tut.

Örn:
`app/Services/SeatingDistributionService.php`

Dağıtım kuralları:

## Kesin kural

Aynı seviyeden iki öğrenci yatay olarak yan yana oturmamalı.

Örn. `9A` ve `9B` öğrencileri aynı satırda komşu iki koltukta olamaz.

Yatay komşu:
- aynı row
- column farkı 1

## İzin verilen durum

Aynı seviyeden öğrenciler gerekirse arka arkaya oturabilir.

Yani dikey komşuluk yasak değildir.

## Yumuşak tercih

Mümkünse bir salonda aynı şubeden en az iki öğrenci bulunması tercih edilir.

Bu zorunlu değildir.

## Salon minimizasyonu

Sistem mümkün olan en az sayıda salonu kullanmaya çalışmalıdır.

Örnek:

- 300 öğrenci
- 12 salon
- her biri 30 kişilik

Önce 10 salonla geçerli çözüm ara.

10 salonla kurallar sağlanamıyorsa 11 salon dene.

11 ile olmazsa 12 salon dene.

Salon kapasiteleri farklıysa:
- mümkün olan en az salon
- mümkün olduğunca az boş koltuk

hedeflenmeli.

Yalnızca exam week içinde izin verilen salonlar kullanılabilir.

## Kapasite kontrolü

Dağıtım öncesinde:
- aktif öğrenci sayısı
- kullanılabilir aktif koltuk sayısı

kontrol edilmeli.

Toplam kapasite yetersizse dağıtımı başlatma.

## Sonuç

Dağıtım sonucu `seating_plans` ve `seating_assignments` içine kaydedilmeli.

Özet üret:

- toplam öğrenci
- kullanılan salon
- boş bırakılan salon
- kullanılan koltuk
- boş koltuk
- yatay aynı şube ihlali sayısı

İdeal durumda ihlal sayısı 0 olmalıdır.

Geçerli çözüm bulunamazsa açık hata ver; sonsuz döngü oluşturma.

`while(true)` / kontrolsüz tekrar kullanma.

Dağıtım algoritması için yalnızca kritik otomatik testler yaz:

- aynı seviye yatay yan yana gelmiyor
- kapasite yetersizken hata
- minimum salon yaklaşımı
- bütün öğrenciler tam bir kez atanıyor
- hiçbir koltuk iki kez kullanılmıyor

Gereksiz geniş test paketi yazma.

---

# 12. Dağıtım oluşturma ekranını yap

Kullanıcı seçili sınav haftası için dağıtım başlatabilsin.

Dağıtım öncesinde anlamlı bir özet göster:

- katılan şubeler
- öğrenci sayısı
- kullanılabilir salonlar
- kapasite
- tahmini minimum salon

Dağıtım oluşturulduğunda sonucu salon bazında göster.

Kullanıcı aynı sınav haftası için yeniden dağıtım oluşturabilsin.

Eski planı yanlışlıkla ezmek yerine yeni plan kaydı oluşturmak veya kontrollü şekilde değiştirmek daha güvenlidir.

Plan draft/final durumu kullanılabilir.

---

# 13. Salon planı manuel düzenlemeyi oluştur

Otomatik dağıtımdan sonra kullanıcı planı elle değiştirebilmeli.

Destekle:

## Aynı salon içinde

- öğrenciyi boş koltuğa taşıma
- iki öğrencinin yerini değiştirme
- drag & drop

## Salonlar arasında

- öğrenciyi başka salona taşıma
- hedef salondaki boş koltuğu seçme
- gerekirse iki öğrenciyi salonlar arasında takas etme

Arayüzde bu işlem “Salon Değiştir” gibi anlaşılır bir adla sunulabilir.

Manuel işlem sonunda aynı şubenin yatay yan yana gelmesi oluşuyorsa kullanıcıya uyarı göster.

Manuel düzenlemede bu kuralı kesin bloklamak zorunlu değildir.

Kullanıcı bilinçli olarak:
`Yine de uygula`

diyebilsin.

Ancak ihlal görünür olmalı.

Değişiklikler backend'e kaydedilmeli.

Mobilde temel kullanım bozulmasın; ancak sürükle-bırak masaüstü/tablet öncelikli olabilir.

---

# 14. Oturma planı görünümünü geliştir

Salon bazında oturma planını anlaşılır biçimde göster.

Her koltukta gerekli bilgiler görüntülenebilsin:

- öğrenci numarası
- ad soyad
- şube
- fotoğraf varsa fotoğraf

Fotoğraf yoksa:
`Foto yok`

göster.

Fotoğraf göster/gizle seçeneği olmalı.

Bu seçenek veriyi değiştirmemeli; yalnızca görünüm/çıktı tercihi olmalı.

---

# 15. Yazdırılabilir çıktıları oluştur

Uygulamanın ana kullanım amaçlarından biri çıktı almaktır.

A4 yazdırmaya uygun temiz görünümler oluştur.

En az şu çıktıları destekle:

## Salon oturma planı

Salon bazında.

Fotoğraflı veya fotoğrafsız seçilebilsin.

## Şube bazında sınav yeri listesi

Öğrencilerin kendi gerçek şubelerine göre listesi.

Örn. 9A öğrencilerinin hangi salonlarda olduğu.

## Salon öğrenci listesi

Bir salondaki öğrencilerin listesi.

## Dağılım özeti

Her salonda hangi şubeden kaç öğrenci olduğunu göster.

## Genel özet

- toplam öğrenci
- kullanılan salonlar
- salon kapasiteleri
- boş koltuk
- şube dağılımları

İlk aşamada tarayıcı yazdırma görünümü yeterlidir.

Kullanıcı:
`Yazdır -> PDF olarak kaydet`

kullanabilsin.

Gereksiz server-side PDF paketi ekleme.

İleride ihtiyaç olursa eklenebilir.

---

# 16. Fotoğraflı / fotoğrafsız çıktı seçeneklerini tamamla

Oturma planı ve uygun listelerde kullanıcı:

- Fotoğrafları göster
- Fotoğrafları gizle

seçeneğini kullanabilsin.

Fotoğraf gizliyken daha kompakt yazdırma düzeni kullanılabilir.

Öğrenci numarası ve şube gibi temel bilgileri gösterme seçenekleri gerekiyorsa sade biçimde eklenebilir.

---

# 17. Sınav tarihleri / sınav programı için temel entegrasyonu tamamla

Mevcut eski Excel uygulamasında sınav tarihleri yalnızca duyuru amaçlıydı ve daha sonra kapatıldı.

Bu özellik V1 için ana işlev değildir.

Ancak mevcut `exams` ve `exam_weeks` yapısını kullanarak sistem ileride:

- sınav adı
- tarih
- saat
- açıklama

bilgilerini destekleyebilecek durumda olsun.

Şu aşamada kapsamı büyütme.

Dağıtım motorunu sınav tarihine bağımlı hale getirme.

Sade bir opsiyonel yönetim ekranı eklenebilir veya veri modeli hazır bırakılabilir.

---

# 18. Demo / eski Excel verisini sisteme aktar

Masaüstünde:

`~/Masaüstü/kelebek.xls`

dosyası bulunuyor.

Bu dosya eski çalışan Excel/VBA uygulamasının kopyasıdır.

Yeni uygulama temel özellikleri tamamlandıktan sonra bu dosyadaki uygun verileri demo/başlangıç verisi olarak yeni veritabanına aktar.

Önce dosyayı analiz et.

Uygun veriler:

- şubeler
- öğrenciler
- okul numaraları
- ad soyad
- salonlar
- salon kapasiteleri
- koltuk düzenleri
- kullanılabilir diğer sabit referans verileri

Aktarımı yeni veri modeline göre yap.

Excel'deki renk kodlarını veya VBA'ya özgü yapıları yeni sisteme taşıma.

Eski VBA'daki renk tabanlı yerleştirme mantığını kullanma.

Mevcut `Sınav Yeri` alanları eski dağıtım sonucu olduğu için bunları kalıcı yeni plan olarak taşımak zorunlu değildir.

Gerekli değilse eski dağıtım sonucunu import etme.

`SınavTarihleri` bölümü ana demo verisi olarak zorunlu değildir.

Bu işlem için tek seferlik importer/command/seeder yaklaşımı kullanabilirsin.

Tekrar çalıştırıldığında duplicate veri oluşturmamasına dikkat et.

İşlem sonunda özet ver:

- şube sayısı
- öğrenci sayısı
- salon sayısı
- koltuk sayısı
- atlanan kayıtlar
- hatalar

---

# 19. Mevcut öğrenci fotoğraflarını demo veriye bağlama

Eğer masaüstünde veya belirtilen bir klasörde öğrenci fotoğrafları mevcutsa, dosya adındaki öğrenci numarasına göre mevcut demo öğrencilere bağlama için uygun import komutu hazırla.

Fotoğraflar:

`145.jpg`, `111.jpg` vb. formatta beklenir.

Büyük görseller yine resize/compress edilmelidir.

Fotoğraf olmayan öğrencilerde:
`Foto yok`

davranışı devam etmeli.

Fotoğraf klasörü bulunmuyorsa uygulama hata vermeden bu adımı atlayabilmeli.

---

# 20. Kullanılabilirlik ve hata mesajlarını gözden geçir

Ana akışlarda kullanıcı dostu Türkçe hata ve başarı mesajları kullan.

Özellikle:

- Excel import hatası
- duplicate öğrenci
- bulunamayan şube
- kapasite yetersiz
- dağıtım çözümsüz
- fotoğraf eşleşmedi
- manuel yer değiştirmede kural ihlali
- kayıt başarısız
- yazdırma durumu

gibi durumlar anlaşılır olmalı.

Teknik exception metinlerini doğrudan kullanıcıya gösterme.

Gereksiz toast yağmuru oluşturma.

---

# 21. Responsive ve son arayüz düzenlemelerini yap

Uygulamanın tüm ana ekranlarını gözden geçir.

Öncelik:
1. Masaüstü
2. Tablet
3. Mobil

Mobilde tüm yönetim fonksiyonlarının mükemmel olması zorunlu değildir; ancak uygulama kullanılabilir olmalı.

Özellikle:
- tablolar
- form alanları
- salon planı
- modallar
- navigasyon

taşmamalı veya bozulmamalı.

Tasarım sade ve modern kalsın.

---

# 22. Production hazırlığını yap

Production hedefi:

`https://kelebek.madematik.com`

Hosting Laravel 13 destekliyor.

Subdomain document root Laravel projesinin:

`public`

klasörünü göstermeli.

Production için:

- `.env` ayarlarını örnek olarak dokümante et
- `APP_ENV=production`
- `APP_DEBUG=false`
- doğru `APP_URL`
- MySQL production bağlantısı
- storage link gerekiyorsa belirt
- cache/config optimize komutlarını belirt
- queue kullanılıyorsa production worker gereksinimini belirt

Projeyi hosting'e kendiliğinden yükleme; yalnızca kullanıcı açıkça isterse deployment yap.

Production'da `.env` veya hassas dosyaların web üzerinden erişilebilir olmadığını doğrula.

---

# 23. Son kritik kontrol

Projeyi gereksiz test turuna sokma.

Yalnızca aşağıdaki kritik akışları kontrol et:

1. Login
2. Academic year / branch oluşturma
3. Excel öğrenci import
4. Fotoğraf import
5. Salon ve koltuk oluşturma
6. Exam week oluşturma
7. Şube ve salon seçimi
8. Dağıtım oluşturma
9. Aynı şube yatay yan yana gelmiyor
10. Minimum salon yaklaşımı çalışıyor
11. Manuel koltuk değişimi
12. Salon değiştirme
13. Fotoğraflı/fotoğrafsız görünüm
14. Yazdırma görünümü
15. `pnpm build`
16. Laravel production için temel hata bırakmıyor

Bulunan kritik hataları düzelt.

Yeni özellik ekleme.

---

# 24. Git / GitHub başlangıcı

Bu adımı yalnızca kullanıcı isterse uygula.

Projede git başlat.

İlk temiz çalışan hali commit et.

GitHub CLI mevcut ve login yapılmışsa kullanıcı onayıyla private repository oluştur.

Önerilen repository adı:

`OAL_Kelebek`

Private repository kullan.

`.env`, `vendor`, `node_modules` ve hassas dosyaların commit edilmediğini doğrula.

Remote oluşturma veya push işlemini kullanıcı istemeden yapma.

---

# Temel iş kuralları — kısa referans

Bu bölüm tüm geliştirme boyunca korunmalıdır.

## Terimler

- Şube = öğrencinin gerçek sınıfı (`9A`, `10C`)
- Salon = öğrencinin sınava girdiği fiziksel oda
- Koltuk = salon içindeki oturma yeri

## Dağıtım

- Her öğrenci her uygun salonda/koltukta oturabilir.
- Sınıf seviyesi veya renk tabanlı koltuk kısıtı yoktur.
- Aynı seviyeden öğrenciler yatay yan yana oturmamalıdır.
- Aynı seviyeden öğrenciler gerekirse arka arkaya oturabilir.
- Mümkünse bir salonda aynı şubeden en az iki öğrenci bulunması tercih edilir.
- Mümkün olan en az sayıda salon kullanılmalıdır.
- Kullanıcı bazı şubeleri sınav haftasından hariç tutabilir.
- Kullanıcı bazı salonları kullanım dışı bırakabilir.
- Sistem yalnızca izin verilen salonlar arasından minimum sayıda salon seçmeye çalışır.

## Manuel düzenleme

- Aynı salon içinde drag & drop
- Boş koltuğa taşıma
- Öğrenci takası
- Salonlar arası taşıma
- Salonlar arası takas
- Kural ihlalinde uyarı
- Kullanıcı isterse manuel olarak ihlali kabul edebilir

## Öğrenciler

- Excel import zorunlu temel özelliktir.
- Öğrenci fotoğrafları toplu yüklenir.
- Fotoğraf dosya adı öğrenci numarasıdır.
- Büyük fotoğraflar küçültülür/sıkıştırılır.
- Fotoğraf yoksa `Foto yok` gösterilir.

## Çıktılar

- Fotoğraflı veya fotoğrafsız oturma planı
- Salon listesi
- Şube bazında sınav yeri listesi
- Dağılım özeti
- Genel özet
- Yazdırılabilir A4 görünüm

## Kullanıcılar

- Başlangıçta 1–2 yönetici
- Öğrenci/veli/öğretmen portalı yok
- Public sınav yeri sorgulama yok
- Register yok

