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
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Çıktılar</h1>
            <p class="mt-1 text-sm text-gray-500">
                Her plan için yazdırılabilir çıktılar yeni sekmede açılır. Tarayıcıdan Yazdır → PDF olarak kaydet
                kullanılabilir.
            </p>
        </div>

        <div v-if="plans.length === 0" class="mt-6 rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
            Henüz dağıtım planı yok. Önce Dağıtım sayfasından plan oluşturun.
        </div>

        <div v-for="plan in plans" :key="plan.id" class="mt-6 rounded-lg bg-white p-6 shadow-sm">
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
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Salon Planları (Fotoğraflı)
                </a>
                <a
                    :href="printUrl(plan, 'seating') + '?photo=0'"
                    target="_blank"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Salon Planları (Fotoğrafsız)
                </a>
                <a
                    :href="printUrl(plan, 'branches')"
                    target="_blank"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Şube Listeleri
                </a>
                <a
                    :href="printUrl(plan, 'rooms')"
                    target="_blank"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Salon Listeleri
                </a>
                <a
                    :href="printUrl(plan, 'summary')"
                    target="_blank"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Dağılım Özeti
                </a>
            </div>
        </div>
    </AppLayout>
</template>
