<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';

interface WeekOption {
    id: number;
    name: string;
    academic_year: { name: string } | null;
    is_active: boolean;
}

interface Summary {
    week: { id: number; name: string };
    branches: { id: number; name: string; active_students_count: number }[];
    branchCount: number;
    studentCount: number;
    rooms: { id: number; name: string; is_active: boolean; active_seats_count: number }[];
    roomCount: number;
    capacity: number;
    minRooms: number | null;
    feasible: boolean;
}

interface Plan {
    id: number;
    name: string | null;
    status: string;
    total_students: number;
    used_room_count: number;
    created_at: string;
    creator: { name: string } | null;
}

const props = defineProps<{
    weeks: WeekOption[];
    selectedWeekId: number | null;
    summary: Summary | null;
    plans: Plan[];
}>();

const selectedWeek = ref<number | null>(props.selectedWeekId);

function changeWeek() {
    router.get('/distribution', { exam_week_id: selectedWeek.value }, { preserveState: true });
}

const startForm = useForm({ exam_week_id: props.selectedWeekId as number | null });

function start() {
    startForm.exam_week_id = selectedWeek.value;
    startForm.post('/distribution');
}

const pageErrors = computed(() => usePage().props.errors as Record<string, string | undefined>);

const schoolName = computed(() => {
    const props = usePage().props as unknown as { current_school: { name: string } | null };
    return props.current_school?.name ?? '';
});

function planName(plan: Plan): string {
    return plan.name || `Plan #${plan.id}`;
}

const deleting = ref<Plan | null>(null);

function askDelete(plan: Plan) {
    deleting.value = plan;
}

function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/distribution/plans/${deleting.value.id}`, {
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <AppLayout title="Dağıtım">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900">Dağıtım</h1>
                <div v-if="weeks.length > 0" class="ml-auto w-64">
                    <label for="dist-week" class="block text-sm font-medium text-gray-700">Sınav Haftası</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="dist-week"
                            v-model="selectedWeek"
                            :options="weeks.map((week) => ({ value: week.id, label: `${week.name}${week.is_active ? ' (aktif)' : ''}` }))"
                            aria-label="Sınav Haftası"
                            @change="changeWeek"
                        />
                    </div>
                </div>
            </div>

            <form v-if="weeks.length > 0" class="mt-4" @submit.prevent="start">
                <button
                    type="submit"
                    :disabled="startForm.processing || !summary?.feasible"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    Dağıtımı Başlat
                </button>
            </form>

            <div v-if="weeks.length === 0" class="mt-4 rounded-md bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                Önce bir sınav haftası ekleyin.
            </div>
            <p v-if="pageErrors.exam_week_id" class="mt-2 text-sm text-red-600">{{ pageErrors.exam_week_id }}</p>
        </div>

        <div v-if="summary" class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">Dağıtım Öncesi Özet - {{ schoolName }} - {{ summary.week.name }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-5">
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.branchCount }}</div>
                    <div class="text-xs text-gray-500">Sınıf</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.studentCount }}</div>
                    <div class="text-xs text-gray-500">Öğrenci</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.roomCount }}</div>
                    <div class="text-xs text-gray-500">Toplam Salon</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.minRooms ?? '—' }}</div>
                    <div class="text-xs text-gray-500">Kullanılacak Salon</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.capacity }}</div>
                    <div class="text-xs text-gray-500">Koltuk Sayısı</div>
                </div>
            </div>
            <p v-if="!summary.feasible" class="mt-3 rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                Kapasite yetersiz veya dağıtılacak öğrenci/salon yok. Dağıtım başlatılamaz.
            </p>
            <div class="mt-3 grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <h3 class="font-medium text-gray-700">Dahil Sınıflar</h3>
                    <ul class="mt-1 space-y-1 text-gray-600">
                        <li v-for="branch in summary.branches" :key="branch.id">
                            {{ branch.name }} ({{ branch.active_students_count }})
                        </li>
                        <li v-if="summary.branches.length === 0" class="text-gray-400">Sınıf seçilmedi.</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-medium text-gray-700">Toplam Salonlar</h3>
                    <ul class="mt-1 space-y-1 text-gray-600">
                        <li v-for="room in summary.rooms" :key="room.id">
                            {{ room.name }} ({{ room.active_seats_count }})
                            <span v-if="!room.is_active" class="text-xs text-gray-400">(pasif)</span>
                        </li>
                        <li v-if="summary.rooms.length === 0" class="text-gray-400">Salon seçilmedi.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div v-if="plans.length > 0" class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Öğrenci</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Salon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Oluşturan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="plan in plans" :key="plan.id">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ planName(plan) }}</td>
                        <td class="px-4 py-3">
                            <span
                                v-if="plan.status === 'final'"
                                class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"
                            >
                                Final
                            </span>
                            <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                Taslak
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ plan.total_students }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ plan.used_room_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ plan.creator?.name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-left">
                            <div class="flex justify-start gap-2">
                                <Link
                                    :href="`/distribution/plans/${plan.id}`"
                                    title="İncele"
                                    aria-label="İncele"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </Link>
                                <button
                                    type="button"
                                    title="Sil"
                                    aria-label="Sil"
                                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-sm text-red-600 hover:bg-red-50"
                                    @click="askDelete(plan)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Planı Sil</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ planName(deleting) }} silinsin mi? Bu işlem geri alınamaz.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                        @click="deleting = null"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-red-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-red-700"
                        @click="confirmDelete"
                    >
                        Sil
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
