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
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Sınav Haftaları</h1>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Hafta</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Şube</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Öğrenci Sayısı</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Salon / Kapasite</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
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
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="week.is_active"
                                    :title="week.is_active ? 'Pasif' : 'Aktif'"
                                    @click="
                                        toggle(
                                            week.is_active
                                                ? `/exam-weeks/${week.id}/deactivate`
                                                : `/exam-weeks/${week.id}/activate`,
                                        )
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                                        week.is_active ? 'bg-indigo-600' : 'bg-gray-300',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5',
                                            week.is_active ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                                        ]"
                                    />
                                </button>
                                <span
                                    v-if="week.is_active"
                                    class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"
                                >
                                    Aktif
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600"
                                >
                                    Pasif
                                </span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-left">
                            <div class="flex justify-start gap-2">
                                <Link
                                    :href="`/exam-weeks/${week.id}`"
                                    title="İncele"
                                    aria-label="İncele"
                                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </Link>
                                <button
                                    type="button"
                                    title="Düzenle"
                                    aria-label="Düzenle"
                                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="openEdit(week)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
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

        <div class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Yeni Sınav Haftası Ekle</h2>

            <form v-if="years.length > 0" class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-6" @submit.prevent="submitCreate">
                <div>
                    <label for="week-year" class="block text-sm font-medium text-gray-700">Akademik Yıl</label>
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
                    <label for="week-name" class="block text-sm font-medium text-gray-700">Hafta Adı</label>
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
                        title="Ekle"
                        aria-label="Ekle"
                        :disabled="createForm.processing"
                        class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </button>
                </div>
            </form>
            <div v-else class="mt-4 rounded-md bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                Önce bir akademik yıl ekleyin.
            </div>
        </div>

        <div v-if="editing" class="fixed inset-0 z-10 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Sınav Haftasını Düzenle</h2>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                title="Kaydet"
                                aria-label="Kaydet"
                                :disabled="editForm.processing"
                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            </button>
                            <button
                                type="button"
                                title="Vazgeç"
                                aria-label="Vazgeç"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                @click="editing = null"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="edit-week-year" class="block text-sm font-medium text-gray-700">Akademik Yıl</label>
                        <select
                            id="edit-week-year"
                            v-model="editForm.academic_year_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="year in years" :key="year.id" :value="year.id">{{ year.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit-week-name" class="block text-sm font-medium text-gray-700">Hafta Adı</label>
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
                </form>
            </div>
        </div>
    </AppLayout>
</template>
