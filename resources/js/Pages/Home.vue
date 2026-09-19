<script setup lang="ts">
import AppLayout from '../Layouts/AppLayout.vue';
</script>

<template>
        <AppLayout title="Okul Yönetim Sistemi">
        <div class="flex w-full max-w-[80%] flex-col gap-6 md:flex-row">
        <div class="flex-1 rounded-lg bg-white p-8 text-center shadow-sm">
            <img :src="'/favicon.png'" alt="Kelebek logosu" class="mx-auto h-20 w-20 object-contain" />
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Kelebek Oturma Planı</h1>
            <p class="mx-auto mt-3 max-w-xl text-left text-sm leading-6 text-gray-600">
                Sınav oturma planlarını dakikalar içinde hazırlayın: şubeleri ve salonları tanımlayın,
                öğrencileri Excel'den aktarın, aynı seviyeden öğrencileri yan yana getirmeyen
                otomatik dağıtımla salonlara yerleştirin ve çıktıyı yazdırın.
            </p>
        </div>
        <div class="flex-1 rounded-lg bg-white p-8 text-center shadow-sm">
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
        </div>
        <div class="flex-1 rounded-lg bg-white p-8 text-center shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor" aria-hidden="true" class="mx-auto h-20 w-20 text-black">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4 2.5 8.5 12 13l9.5-4.5L12 4Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 10.5V15c0 1.6 2.5 3 5.5 3s5.5-1.4 5.5-3v-4.5" />
                <path stroke-linecap="round" d="M21.5 8.5V14" />
            </svg>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Mezun Takip</h1>
            <p class="mx-auto mt-3 max-w-xl text-left text-sm leading-6 text-gray-600">
                Mezunların iletişim, üniversite ve iş bilgilerini yıllara göre arşivleyin,
                mezun rehberi oluşturun. Bu bölüm yakında aktif olacak.
            </p>
        </div>
        </div>
    </AppLayout>
</template>
