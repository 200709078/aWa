<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';

interface Year {
    id: number;
    name: string;
    is_active: boolean;
}

interface BranchRow {
    id: number;
    name: string;
    is_active: boolean;
    student_count: number;
    plan_id: number | null;
    seat_count: number;
    seated_count: number;
}

const props = defineProps<{
    years: Year[];
    selectedYearId: number | null;
    branches: BranchRow[];
}>();

function changeYear(value: string | number | null) {
    const yearId = value === null || value === '' ? null : Number(value);
    router.get('/oturme-planlari', yearId ? { academic_year_id: yearId } : {});
}

function createPlan(branchId: number) {
    router.post('/oturme-planlari', { branch_id: branchId });
}
</script>

<template>
    <AppLayout title="Oturma Planları">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Sınıf Oturma Planları</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Her sınıf için derslik oturma planı hazırlayın. Plan ilk açılışta 36 koltukla (6×6) ve numara sırasına göre otomatik dizilir; sonrası elle düzenlenir.
                    </p>
                </div>
                <div class="w-56">
                    <DropdownSelect
                        id="plan-year"
                        :model-value="props.selectedYearId"
                        :options="years.map((y) => ({ value: y.id, label: `${y.name}${y.is_active ? ' (aktif)' : ''}` }))"
                        aria-label="Akademik Yıl"
                        @update:model-value="changeYear"
                    />
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sınıf</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Mevcut</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Plan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="branch in branches" :key="branch.id">
                            <td class="whitespace-nowrap px-4 py-2 font-semibold text-gray-900">{{ branch.name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ branch.student_count }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                <span v-if="branch.plan_id">Var ({{ branch.seated_count }}/{{ branch.seat_count }})</span>
                                <span v-else class="text-gray-400">Yok</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <Link
                                    v-if="branch.plan_id"
                                    :href="`/oturme-planlari/${branch.plan_id}`"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                                >
                                    Planı Aç
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700"
                                    @click="createPlan(branch.id)"
                                >
                                    Plan Oluştur
                                </button>
                            </td>
                        </tr>
                        <tr v-if="branches.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">Bu yılda sınıf bulunamadı.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
