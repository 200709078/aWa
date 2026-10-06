<script setup lang="ts">
import AppLayout from '../Layouts/AppLayout.vue';
</script>

<template>
        <AppLayout title="Okul Yönetim Sistemi">
        <div class="grid w-full max-w-[80%] gap-6 md:grid-cols-3">
        <a href="/distribution" class="flex-1 rounded-lg bg-white p-8 text-center shadow-sm transition-shadow hover:shadow-md">
            <img :src="'/kelebek.png'" alt="Kelebek logosu" class="mx-auto h-20 w-20 object-contain" />
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Kelebek Oturma Planı</h1>
            <p class="mx-auto mt-3 max-w-xl text-left text-sm leading-6 text-gray-600">
                Sınav oturma planlarını dakikalar içinde hazırlayın: şubeleri ve salonları tanımlayın,
                öğrencileri Excel'den aktarın, aynı seviyeden öğrencileri yan yana getirmeyen
                otomatik dağıtımla salonlara yerleştirin ve çıktıyı yazdırın.
            </p>
        </a>
        <a href="/personel" class="flex-1 rounded-lg bg-white p-8 text-center shadow-sm transition-shadow hover:shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor" aria-hidden="true" class="mx-auto h-20 w-20 text-black">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
            </svg>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Personel Yönetimi</h1>
            <p class="mx-auto mt-3 max-w-xl text-left text-sm leading-6 text-gray-600">
                Öğretmen ve personel kayıtlarını tutun, görev ve branş bilgilerini yönetin,
                yaka kartlarını hazırlayıp yazdırın.
            </p>
        </a>
        <a href="/rehber-aktarma" class="flex-1 rounded-lg bg-white p-8 text-center shadow-sm transition-shadow hover:shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor" aria-hidden="true" class="mx-auto h-20 w-20 text-black">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 3.5h11a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2Z" />
                <path stroke-linecap="round" d="M9 3.5V2.5M15 3.5V2.5" />
                <circle cx="9.8" cy="10" r="1.9" />
                <path stroke-linecap="round" d="M6.8 16.2c.6-1.7 1.7-2.6 3-2.6s2.4.9 3 2.6" />
                <path stroke-linecap="round" d="M14.8 9.3h2.4M14.8 12h2.4M14.8 14.7h2.4" />
            </svg>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Rehber Aktarma</h1>
            <p class="mx-auto mt-3 max-w-xl text-left text-sm leading-6 text-gray-600">
                Öğrenci, veli telefon numaralarını saniyeler içerisinde VCF formatına dönüştürerek
                bilgisayarınıza, telefonunuza veya başka bir rehber yönetme uygulamasına aktarın.
            </p>
        </a>
        <a href="/bilgi-formlari" class="flex-1 rounded-lg bg-white p-8 text-center shadow-sm transition-shadow hover:shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor" aria-hidden="true" class="mx-auto h-20 w-20 text-black">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
            </svg>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Rehberlik</h1>
            <p class="mx-auto mt-3 max-w-xl text-left text-sm leading-6 text-gray-600">
                Öğrenci bilgi formlarını içe aktarın, her öğrenci için bilgi formu
                ve her sınıf için risk haritası çıktısı alın.
            </p>
        </a>
        <a href="/mezunlar" class="flex-1 rounded-lg bg-white p-8 text-center shadow-sm transition-shadow hover:shadow-md">
            <img :src="'/favicon.png'" alt="Mezun Takip" class="mx-auto h-20 w-20 object-contain" />
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Mezun Takip</h1>
            <p class="mx-auto mt-3 max-w-xl text-left text-sm leading-6 text-gray-600">
                Mezunların iletişim, üniversite ve iş bilgilerini yıllara göre arşivleyin,
                mezun rehberi oluşturun.
            </p>
        </a>
        </div>
    </AppLayout>
</template>
