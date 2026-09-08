<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
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

interface Exam {
    id: number;
    name: string;
    exam_date: string | null;
    start_time: string | null;
    description: string | null;
}

const props = defineProps<{
    week: Week;
    exams: Exam[];
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

const examForm = useForm({ name: '', exam_date: '', start_time: '', description: '' });

function submitExam() {
    examForm.post(`/exam-weeks/${props.week.id}/exams`, {
        onSuccess: () => examForm.reset(),
    });
}

function deleteExam(id: number) {
    router.delete(`/exams/${id}`);
}

function formatDate(value: string | null): string {
    return value ? value.slice(0, 10).split('-').reverse().join('.') : '—';
}

function formatTime(value: string | null): string {
    return value ? value.slice(0, 5) : '';
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
                    <div class="text-xs text-gray-500">Dahil Öğrenci</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.room_count }}</div>
                    <div class="text-xs text-gray-500">Aktif Salon</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.capacity }}</div>
                    <div class="text-xs text-gray-500">Aktif Koltuk</div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <form class="rounded-lg bg-white p-6 shadow-sm" @submit.prevent="saveBranches">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-gray-900">Dağıtıma Dahil Şubeler</h2>
                    <button
                        type="submit"
                        title="Şubeleri Kaydet"
                        aria-label="Şubeleri Kaydet"
                        :disabled="branchForm.processing"
                        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </button>
                </div>
                <div class="mt-2 flex gap-3 text-sm">
                    <button
                        type="button"
                        class="text-indigo-600 hover:underline"
                        @click="branchForm.branch_ids = branches.map((b) => b.id)"
                    >
                        Tümünü seç
                    </button>
                    <button
                        type="button"
                        class="text-gray-500 hover:underline"
                        @click="branchForm.branch_ids = []"
                    >
                        Temizle
                    </button>
                </div>
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
            </form>

            <form class="rounded-lg bg-white p-6 shadow-sm" @submit.prevent="saveRooms">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-gray-900">Kullanılmasına İzin Verilen Salonlar</h2>
                    <button
                        type="submit"
                        title="Salonları Kaydet"
                        aria-label="Salonları Kaydet"
                        :disabled="roomForm.processing"
                        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </button>
                </div>
                <div class="mt-2 flex gap-3 text-sm">
                    <button
                        type="button"
                        class="text-indigo-600 hover:underline"
                        @click="roomForm.room_ids = rooms.map((r) => r.id)"
                    >
                        Tümünü seç
                    </button>
                    <button
                        type="button"
                        class="text-gray-500 hover:underline"
                        @click="roomForm.room_ids = []"
                    >
                        Temizle
                    </button>
                </div>
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
            </form>
        </div>

        <div class="mt-6 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">Sınavlar <span class="text-sm font-normal text-gray-400">(opsiyonel, duyuru amaçlı — dağıtıma etkisi yok)</span></h2>

            <ul v-if="exams.length > 0" class="mt-3 divide-y divide-gray-200">
                <li v-for="exam in exams" :key="exam.id" class="flex items-center justify-between gap-3 py-2">
                    <div class="text-sm">
                        <span class="font-medium text-gray-900">{{ exam.name }}</span>
                        <span class="ml-2 text-gray-500">{{ formatDate(exam.exam_date) }}{{ formatTime(exam.start_time) ? ` · ${formatTime(exam.start_time)}` : '' }}</span>
                        <p v-if="exam.description" class="text-gray-500">{{ exam.description }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm text-red-600 hover:bg-red-50"
                        @click="deleteExam(exam.id)"
                    >
                        Sil
                    </button>
                </li>
            </ul>
            <p v-else class="mt-2 text-sm text-gray-400">Henüz sınav eklenmedi.</p>

            <form class="mt-4 grid gap-3 border-t pt-4 md:grid-cols-5" @submit.prevent="submitExam">
                <div class="md:col-span-2">
                    <label for="exam-name" class="block text-sm font-medium text-gray-700">Sınav Adı</label>
                    <input
                        id="exam-name"
                        v-model="examForm.name"
                        type="text"
                        required
                        maxlength="100"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="examForm.errors.name" class="mt-1 text-sm text-red-600">{{ examForm.errors.name }}</p>
                </div>
                <div>
                    <label for="exam-date" class="block text-sm font-medium text-gray-700">Tarih</label>
                    <input
                        id="exam-date"
                        v-model="examForm.exam_date"
                        type="date"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="exam-time" class="block text-sm font-medium text-gray-700">Saat</label>
                    <input
                        id="exam-time"
                        v-model="examForm.start_time"
                        type="time"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="examForm.errors.start_time" class="mt-1 text-sm text-red-600">
                        {{ examForm.errors.start_time }}
                    </p>
                </div>
                <div class="flex items-end">
                    <button
                        type="submit"
                        title="Ekle"
                        aria-label="Ekle"
                        :disabled="examForm.processing"
                        class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
