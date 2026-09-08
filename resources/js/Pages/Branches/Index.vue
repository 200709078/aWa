<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Year {
    id: number;
    name: string;
    is_active: boolean;
}

interface Branch {
    id: number;
    academic_year_id: number;
    name: string;
    grade_level: number;
    section: string;
    is_active: boolean;
    students_count: number;
}

const props = defineProps<{
    years: Year[];
    selectedYearId: number | null;
    branches: Branch[];
}>();

const filterYear = ref<number | null>(props.selectedYearId);

function applyFilter() {
    createForm.academic_year_id = filterYear.value;
    router.get('/branches', { academic_year_id: filterYear.value }, { preserveState: true });
}

const createForm = useForm({
    academic_year_id: props.selectedYearId as number | null,
    name: '',
    grade_level: null as number | null,
    section: '',
    is_active: true,
});

function submitCreate() {
    createForm.post('/branches', {
        onSuccess: () =>
            createForm.reset('name', 'grade_level', 'section'),
    });
}

const editing = ref<Branch | null>(null);
const editForm = useForm({
    academic_year_id: null as number | null,
    name: '',
    grade_level: null as number | null,
    section: '',
    is_active: true,
});

function openEdit(branch: Branch) {
    editing.value = branch;
    editForm.academic_year_id = branch.academic_year_id;
    editForm.name = branch.name;
    editForm.grade_level = branch.grade_level;
    editForm.section = branch.section;
    editForm.is_active = branch.is_active;
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.put(`/branches/${editing.value.id}`, {
        onSuccess: () => (editing.value = null),
    });
}

function toggle(url: string) {
    router.post(url);
}
</script>

<template>
    <AppLayout title="Şubeler">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold text-gray-900">Şubeler</h1>
                <div class="flex items-center gap-2">
                    <label for="year-filter" class="text-sm text-gray-600">Akademik Yıl</label>
                    <select
                        id="year-filter"
                        v-model="filterYear"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="applyFilter"
                    >
                        <option v-for="year in years" :key="year.id" :value="year.id">
                            {{ year.name }}{{ year.is_active ? ' (aktif)' : '' }}
                        </option>
                    </select>
                    <Link href="/academic-years" class="text-sm text-indigo-600 hover:underline">
                        Yılları yönet
                    </Link>
                </div>
            </div>

            <div v-if="years.length === 0" class="mt-4 rounded-md bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                Önce bir akademik yıl ekleyin.
                <Link href="/academic-years" class="font-semibold underline">Akademik Yıllar</Link>
            </div>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sınıf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Seviye</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Şube</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Öğrenci</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="branch in branches" :key="branch.id">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ branch.name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ branch.grade_level }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ branch.section }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ branch.students_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="branch.is_active"
                                    :title="branch.is_active ? 'Pasif' : 'Aktif'"
                                    @click="
                                        toggle(
                                            branch.is_active
                                                ? `/branches/${branch.id}/deactivate`
                                                : `/branches/${branch.id}/activate`,
                                        )
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                                        branch.is_active ? 'bg-indigo-600' : 'bg-gray-300',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5',
                                            branch.is_active ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                                        ]"
                                    />
                                </button>
                                <span
                                    v-if="branch.is_active"
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
                                <button
                                    type="button"
                                    title="Düzenle"
                                    aria-label="Düzenle"
                                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="openEdit(branch)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="branches.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Bu yılda şube bulunmuyor.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="years.length > 0" class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Yeni Şube Ekle</h2>

            <form class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-6" @submit.prevent="submitCreate">
                <div class="col-span-2">
                    <label for="branch-name" class="block text-sm font-medium text-gray-700">Sınıf Adı</label>
                    <input
                        id="branch-name"
                        v-model="createForm.name"
                        type="text"
                        required
                        maxlength="10"
                        placeholder="9A"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <label for="branch-grade" class="block text-sm font-medium text-gray-700">Seviye</label>
                    <input
                        id="branch-grade"
                        v-model.number="createForm.grade_level"
                        type="number"
                        required
                        min="1"
                        max="12"
                        placeholder="9"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.grade_level" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.grade_level }}
                    </p>
                </div>
                <div>
                    <label for="branch-section" class="block text-sm font-medium text-gray-700">Şube</label>
                    <input
                        id="branch-section"
                        v-model="createForm.section"
                        type="text"
                        required
                        maxlength="10"
                        placeholder="A"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.section" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.section }}
                    </p>
                </div>
                <div class="flex items-end md:col-span-2">
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
            <p v-if="createForm.errors.academic_year_id" class="mt-2 text-sm text-red-600">
                {{ createForm.errors.academic_year_id }}
            </p>
        </div>

        <div v-if="editing" class="fixed inset-0 z-10 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-sm overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Şubeyi Düzenle</h2>
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
                        <label for="edit-branch-year" class="block text-sm font-medium text-gray-700">Akademik Yıl</label>
                        <select
                            id="edit-branch-year"
                            v-model="editForm.academic_year_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="year in years" :key="year.id" :value="year.id">{{ year.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit-branch-name" class="block text-sm font-medium text-gray-700">Sınıf Adı</label>
                        <input
                            id="edit-branch-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            maxlength="10"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">{{ editForm.errors.name }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="edit-branch-grade" class="block text-sm font-medium text-gray-700">Seviye</label>
                            <input
                                id="edit-branch-grade"
                                v-model.number="editForm.grade_level"
                                type="number"
                                required
                                min="1"
                                max="12"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-if="editForm.errors.grade_level" class="mt-1 text-sm text-red-600">
                                {{ editForm.errors.grade_level }}
                            </p>
                        </div>
                        <div>
                            <label for="edit-branch-section" class="block text-sm font-medium text-gray-700">Şube</label>
                            <input
                                id="edit-branch-section"
                                v-model="editForm.section"
                                type="text"
                                required
                                maxlength="10"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-if="editForm.errors.section" class="mt-1 text-sm text-red-600">
                                {{ editForm.errors.section }}
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
