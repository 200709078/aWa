<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

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

const startForm = useForm({ exam_week_id: props.selectedWeekId as number | null, name: '' });

function start() {
    startForm.exam_week_id = selectedWeek.value;
    startForm.post('/distribution');
}

const pageErrors = computed(() => usePage().props.errors as Record<string, string | undefined>);

function planName(plan: Plan): string {
    return plan.name || `Plan #${plan.id}`;
}
</script>

<template>
    <AppLayout title="Dağıtım">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Dağıtım</h1>

            <div v-if="weeks.length === 0" class="mt-4 rounded-md bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                Önce bir sınav haftası ekleyin.
            </div>

            <div v-else class="mt-4 grid gap-3 md:grid-cols-3">
                <div>
                    <label for="dist-week" class="block text-sm font-medium text-gray-700">Sınav haftası</label>
                    <select
                        id="dist-week"
                        v-model="selectedWeek"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="changeWeek"
                    >
                        <option v-for="week in weeks" :key="week.id" :value="week.id">
                            {{ week.name }}{{ week.is_active ? ' (aktif)' : '' }}
                        </option>
                    </select>
                </div>
                <form class="md:col-span-2" @submit.prevent="start">
                    <label for="dist-name" class="block text-sm font-medium text-gray-700">Plan adı (isteğe bağlı)</label>
                    <div class="mt-1 flex gap-2">
                        <input
                            id="dist-name"
                            v-model="startForm.name"
                            type="text"
                            maxlength="100"
                            placeholder="örn. 1. deneme"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <button
                            type="submit"
                            :disabled="startForm.processing || !summary?.feasible"
                            class="whitespace-nowrap rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Dağıtımı Başlat
                        </button>
                    </div>
                </form>
            </div>
            <p v-if="pageErrors.exam_week_id" class="mt-2 text-sm text-red-600">{{ pageErrors.exam_week_id }}</p>
        </div>

        <div v-if="summary" class="mt-6 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">Dağıtım öncesi özet — {{ summary.week.name }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-5">
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.branchCount }}</div>
                    <div class="text-xs text-gray-500">Şube</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.studentCount }}</div>
                    <div class="text-xs text-gray-500">Öğrenci</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.roomCount }}</div>
                    <div class="text-xs text-gray-500">İzinli salon</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.capacity }}</div>
                    <div class="text-xs text-gray-500">Koltuk</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.minRooms ?? '—' }}</div>
                    <div class="text-xs text-gray-500">Tahmini min. salon</div>
                </div>
            </div>
            <p v-if="!summary.feasible" class="mt-3 rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                Kapasite yetersiz veya dağıtılacak öğrenci/salon yok. Dağıtım başlatılamaz.
            </p>
            <div class="mt-3 grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <h3 class="font-medium text-gray-700">Dahil şubeler</h3>
                    <ul class="mt-1 space-y-1 text-gray-600">
                        <li v-for="branch in summary.branches" :key="branch.id">
                            {{ branch.name }} ({{ branch.active_students_count }})
                        </li>
                        <li v-if="summary.branches.length === 0" class="text-gray-400">Şube seçilmedi.</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-medium text-gray-700">İzinli salonlar</h3>
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

        <div v-if="plans.length > 0" class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Öğrenci</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Salon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Oluşturan</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">İşlem</th>
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
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <Link
                                :href="`/distribution/plans/${plan.id}`"
                                class="rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-700"
                            >
                                İncele
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
