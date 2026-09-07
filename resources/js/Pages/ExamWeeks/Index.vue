<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Week {
    id: number;
    name: string;
    description: string | null;
    academic_year_id: number;
    academic_year: string | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    branches_count: number;
    rooms_count: number;
    student_count: number;
    capacity: number;
}

interface Year {
    id: number;
    name: string;
    is_active: boolean;
}

defineProps<{
    weeks: Week[];
    years: Year[];
    activeYearId: number | null;
}>();

const createForm = useForm({
    academic_year_id: null as number | null,
    name: '',
    description: '',
    starts_at: '',
    ends_at: '',
});

function submitCreate() {
    createForm.post('/exam-weeks', {
        onSuccess: () => createForm.reset('name', 'description', 'starts_at', 'ends_at'),
    });
}

const editing = ref<Week | null>(null);
const editForm = useForm({
    academic_year_id: null as number | null,
    name: '',
    description: '',
    starts_at: '',
    ends_at: '',
});

function openEdit(week: Week) {
    editing.value = week;
    editForm.academic_year_id = week.academic_year_id;
    editForm.name = week.name;
    editForm.description = week.description ?? '';
    editForm.starts_at = week.starts_at ?? '';
    editForm.ends_at = week.ends_at ?? '';
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.put(`/exam-weeks/${editing.value.id}`, {
        onSuccess: () => (editing.value = null),
    });
}

function toggle(url: string) {
    router.post(url);
}

function dateRange(week: Week): string {
    if (week.starts_at && week.ends_at) return `${week.starts_at} – ${week.ends_at}`;
    return week.starts_at ?? week.ends_at ?? '—';
}
</script>

<template>
    <AppLayout title="Sınav Haftaları">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Sınav Haftaları</h1>

            <form v-if="years.length > 0" class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-6" @submit.prevent="submitCreate">
                <div>
                    <label for="week-year" class="block text-sm font-medium text-gray-700">Akademik yıl</label>
                    <select
                        id="week-year"
                        v-model="createForm.academic_year_id"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option :value="null">Seçin</option>
                        <option v-for="year in years" :key="year.id" :value="year.id">
                            {{ year.name }}{{ year.is_active ? ' (aktif)' : '' }}
                        </option>
                    </select>
                    <p v-if="createForm.errors.academic_year_id" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.academic_year_id }}
                    </p>
                </div>
                <div class="col-span-2">
                    <label for="week-name" class="block text-sm font-medium text-gray-700">Hafta adı</label>
                    <input
                        id="week-name"
                        v-model="createForm.name"
                        type="text"
                        required
                        maxlength="100"
                        placeholder="1. Dönem Sınavları"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <label for="week-start" class="block text-sm font-medium text-gray-700">Başlangıç</label>
                    <input
                        id="week-start"
                        v-model="createForm.starts_at"
                        type="date"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="week-end" class="block text-sm font-medium text-gray-700">Bitiş</label>
                    <input
                        id="week-end"
                        v-model="createForm.ends_at"
                        type="date"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.ends_at" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.ends_at }}
                    </p>
                </div>
                <div class="flex items-end">
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Ekle
                    </button>
                </div>
            </form>
            <div v-else class="mt-4 rounded-md bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                Önce bir akademik yıl ekleyin.
            </div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Hafta</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Şube</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Öğrenci</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Salon / Kapasite</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="week in weeks" :key="week.id">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ week.name }}</div>
                            <div class="text-xs text-gray-500">{{ week.academic_year }} · {{ dateRange(week) }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ week.branches_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ week.student_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                            {{ week.rooms_count }} / {{ week.capacity }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span
                                v-if="week.is_active"
                                class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"
                            >
                                Aktif
                            </span>
                            <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                Pasif
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Link
                                    :href="`/exam-weeks/${week.id}`"
                                    class="rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-700"
                                >
                                    İncele
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="openEdit(week)"
                                >
                                    Düzenle
                                </button>
                                <button
                                    v-if="!week.is_active"
                                    type="button"
                                    class="rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-700"
                                    @click="toggle(`/exam-weeks/${week.id}/activate`)"
                                >
                                    Aktif Yap
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="toggle(`/exam-weeks/${week.id}/deactivate`)"
                                >
                                    Pasife Al
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="weeks.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Henüz sınav haftası eklenmedi.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="editing" class="fixed inset-0 z-10 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Sınav haftasını düzenle</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <label for="edit-week-year" class="block text-sm font-medium text-gray-700">Akademik yıl</label>
                        <select
                            id="edit-week-year"
                            v-model="editForm.academic_year_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="year in years" :key="year.id" :value="year.id">{{ year.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit-week-name" class="block text-sm font-medium text-gray-700">Hafta adı</label>
                        <input
                            id="edit-week-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            maxlength="100"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="edit-week-desc" class="block text-sm font-medium text-gray-700">Açıklama</label>
                        <input
                            id="edit-week-desc"
                            v-model="editForm.description"
                            type="text"
                            maxlength="500"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="edit-week-start" class="block text-sm font-medium text-gray-700">Başlangıç</label>
                            <input
                                id="edit-week-start"
                                v-model="editForm.starts_at"
                                type="date"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="edit-week-end" class="block text-sm font-medium text-gray-700">Bitiş</label>
                            <input
                                id="edit-week-end"
                                v-model="editForm.ends_at"
                                type="date"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-if="editForm.errors.ends_at" class="mt-1 text-sm text-red-600">
                                {{ editForm.errors.ends_at }}
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            @click="editing = null"
                        >
                            Vazgeç
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
