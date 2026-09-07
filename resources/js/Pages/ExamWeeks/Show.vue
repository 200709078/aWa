<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Week {
    id: number;
    name: string;
    description: string | null;
    academic_year: string | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
}

interface BranchOption {
    id: number;
    name: string;
    active_students_count: number;
}

interface RoomOption {
    id: number;
    name: string;
    is_active: boolean;
    active_seats_count: number;
}

const props = defineProps<{
    week: Week;
    branches: BranchOption[];
    rooms: RoomOption[];
    selectedBranchIds: number[];
    selectedRoomIds: number[];
    summary: { student_count: number; room_count: number; capacity: number };
}>();

const branchForm = useForm({ branch_ids: [...props.selectedBranchIds] });
const roomForm = useForm({ room_ids: [...props.selectedRoomIds] });

function saveBranches() {
    branchForm.put(`/exam-weeks/${props.week.id}/branches`);
}

function saveRooms() {
    roomForm.put(`/exam-weeks/${props.week.id}/rooms`);
}
</script>

<template>
    <AppLayout :title="week.name">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ week.name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ week.academic_year }}
                        <span v-if="week.starts_at || week.ends_at">
                            · {{ week.starts_at ?? '?' }} – {{ week.ends_at ?? '?' }}
                        </span>
                    </p>
                    <p v-if="week.description" class="mt-1 text-sm text-gray-600">{{ week.description }}</p>
                </div>
                <Link
                    href="/exam-weeks"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Haftalara dön
                </Link>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-3">
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.student_count }}</div>
                    <div class="text-xs text-gray-500">Dahil öğrenci</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.room_count }}</div>
                    <div class="text-xs text-gray-500">Aktif salon</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.capacity }}</div>
                    <div class="text-xs text-gray-500">Aktif koltuk</div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <form class="rounded-lg bg-white p-6 shadow-sm" @submit.prevent="saveBranches">
                <h2 class="font-semibold text-gray-900">Dağıtıma dahil şubeler</h2>
                <div class="mt-3 max-h-96 space-y-1 overflow-y-auto">
                    <label
                        v-for="branch in branches"
                        :key="branch.id"
                        class="flex items-center justify-between gap-2 rounded-md px-2 py-1 hover:bg-gray-50"
                    >
                        <span class="flex items-center gap-2 text-sm">
                            <input
                                v-model="branchForm.branch_ids"
                                type="checkbox"
                                :value="branch.id"
                                class="rounded border-gray-300"
                            />
                            {{ branch.name }}
                        </span>
                        <span class="text-xs text-gray-400">{{ branch.active_students_count }} öğrenci</span>
                    </label>
                </div>
                <p v-if="branchForm.errors.branch_ids" class="mt-2 text-sm text-red-600">
                    {{ branchForm.errors.branch_ids }}
                </p>
                <button
                    type="submit"
                    :disabled="branchForm.processing"
                    class="mt-3 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    Şubeleri Kaydet
                </button>
            </form>

            <form class="rounded-lg bg-white p-6 shadow-sm" @submit.prevent="saveRooms">
                <h2 class="font-semibold text-gray-900">Kullanılmasına izin verilen salonlar</h2>
                <div class="mt-3 max-h-96 space-y-1 overflow-y-auto">
                    <label
                        v-for="room in rooms"
                        :key="room.id"
                        class="flex items-center justify-between gap-2 rounded-md px-2 py-1 hover:bg-gray-50"
                    >
                        <span class="flex items-center gap-2 text-sm">
                            <input
                                v-model="roomForm.room_ids"
                                type="checkbox"
                                :value="room.id"
                                class="rounded border-gray-300"
                            />
                            {{ room.name }}
                            <span v-if="!room.is_active" class="text-xs text-gray-400">(pasif salon)</span>
                        </span>
                        <span class="text-xs text-gray-400">{{ room.active_seats_count }} koltuk</span>
                    </label>
                </div>
                <p v-if="roomForm.errors.room_ids" class="mt-2 text-sm text-red-600">
                    {{ roomForm.errors.room_ids }}
                </p>
                <button
                    type="submit"
                    :disabled="roomForm.processing"
                    class="mt-3 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    Salonları Kaydet
                </button>
            </form>
        </div>
    </AppLayout>
</template>
