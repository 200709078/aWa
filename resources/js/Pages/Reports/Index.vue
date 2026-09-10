<script setup lang="ts">
import AppLayout from '../../Layouts/AppLayout.vue';

interface Plan {
    id: number;
    name: string;
    week: string | null;
    status: string;
    total_students: number;
    used_room_count: number;
}

defineProps<{ plans: Plan[] }>();

function printUrl(plan: Plan, type: string): string {
    return `/distribution/plans/${plan.id}/print/${type}`;
}
</script>

<template>
    <AppLayout title="Çıktılar">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Çıktılar</h1>
            <p class="mt-1 text-sm text-gray-500">
                Her plan için yazdırılabilir çıktılar yeni sekmede açılır. Tarayıcıdan Yazdır → PDF olarak kaydet
                kullanılabilir.
            </p>
        </div>

        <div v-if="plans.length === 0" class="mt-6 rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
            Henüz dağıtım planı yok. Önce Dağıtım sayfasından plan oluşturun.
        </div>

        <div v-for="plan in plans" :key="plan.id" class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-semibold text-gray-900">{{ plan.name }}</h2>
                    <p class="text-sm text-gray-500">
                        {{ plan.week }} · {{ plan.total_students }} öğrenci · {{ plan.used_room_count }} salon ·
                        {{ plan.status === 'final' ? 'Final' : 'Taslak' }}
                    </p>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <a
                    :href="printUrl(plan, 'seating') + '?photo=1'"
                    target="_blank"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mr-1.5 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 14h12v8H6z" /></svg>
                    Salon Planları
                </a>
                <a
                    :href="printUrl(plan, 'branches')"
                    target="_blank"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mr-1.5 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 14h12v8H6z" /></svg>
                    Sınıf Listeleri
                </a>
                <a
                    :href="printUrl(plan, 'rooms')"
                    target="_blank"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mr-1.5 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 14h12v8H6z" /></svg>
                    Salon Listeleri
                </a>
                <a
                    :href="printUrl(plan, 'summary')"
                    target="_blank"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mr-1.5 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 14h12v8H6z" /></svg>
                    Dağılım Özeti
                </a>
            </div>
        </div>
    </AppLayout>
</template>
