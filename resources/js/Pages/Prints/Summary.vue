<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PrintLayout from '../../Layouts/PrintLayout.vue';

const schoolName = computed(() => {
    const pageProps = usePage().props as unknown as { current_school: { name: string } | null };
    return pageProps.current_school?.name ?? '';
});

defineProps<{
    plan: { id: number; name: string | null };
    week: { name: string };
    summary: {
        total_students: number;
        used_rooms: { id: number; name: string }[];
        unused_rooms: { id: number; name: string }[];
        used_seats: number;
        empty_seats: number;
        violations: number;
    };
    branches: string[];
    matrix: { room: string; capacity: number; cells: number[]; total: number }[];
    branchTotals: number[];
}>();
</script>

<template>
    <PrintLayout title="Dağılım Özeti" :back-href="`/distribution/plans/${plan.id}`">
        <h1 class="text-xl font-bold">Dağılım Özeti - {{ schoolName }} - {{ week.name }}{{ plan.name ? ` · ${plan.name}` : '' }}</h1>

        <h2 class="mt-6 text-lg font-semibold">Genel Özet</h2>
        <table class="mt-2 w-full max-w-xl border-collapse text-sm">
            <tbody>
                <tr class="border-b border-gray-200">
                    <td class="py-1">Toplam Öğrenci</td>
                    <td class="py-1 text-right font-semibold">{{ summary.total_students }}</td>
                </tr>
                <tr class="border-b border-gray-200">
                    <td class="py-1">Kullanılan Salon</td>
                    <td class="py-1 text-right font-semibold">{{ summary.used_rooms.length }}</td>
                </tr>
                <tr class="border-b border-gray-200">
                    <td class="py-1">Dolu Koltuk</td>
                    <td class="py-1 text-right font-semibold">{{ summary.used_seats }}</td>
                </tr>
                <tr class="border-b border-gray-200">
                    <td class="py-1">Boş Koltuk</td>
                    <td class="py-1 text-right font-semibold">{{ summary.empty_seats }}</td>
                </tr>
            </tbody>
        </table>
        <p v-if="summary.unused_rooms.length > 0" class="mt-2 text-sm text-gray-600">
            Kullanılmayan salonlar: {{ summary.unused_rooms.map((r) => r.name).join(', ') }}
        </p>

        <h2 class="mt-6 text-lg font-semibold">Salon × Sınıf Dağılımı</h2>
        <table class="mt-2 border-collapse text-xs">
            <thead>
                <tr class="border-b-2 border-gray-800">
                    <th class="px-1 py-1 text-left">Salon</th>
                    <th class="px-1 py-1 text-right">Kapasite</th>
                    <th v-for="branch in branches" :key="branch" class="px-1 py-1 text-right">{{ branch }}</th>
                    <th class="px-1 py-1 text-right font-semibold">Toplam</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in matrix" :key="row.room" class="border-b border-gray-200">
                    <td class="px-1 py-1 font-medium">{{ row.room }}</td>
                    <td class="px-1 py-1 text-right text-gray-500">{{ row.capacity }}</td>
                    <td v-for="(cell, i) in row.cells" :key="i" class="px-1 py-1 text-right">{{ cell }}</td>
                    <td class="px-1 py-1 text-right font-semibold">{{ row.total }}</td>
                </tr>
                <tr class="border-t-2 border-gray-800 font-semibold">
                    <td class="px-1 py-1">Toplam</td>
                    <td v-for="(total, i) in branchTotals" :key="i" class="px-1 py-1 text-right">{{ total }}</td>
                    <td class="px-1 py-1 text-right">{{ summary.total_students }}</td>
                </tr>
            </tbody>
        </table>
    </PrintLayout>
</template>
