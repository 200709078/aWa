<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Year {
    id: number;
    name: string;
    is_active: boolean;
}

interface BranchOption {
    id: number;
    name: string;
}

interface Student {
    id: number;
    school_number: string;
    full_name: string;
    photo_path: string | null;
    is_active: boolean;
    branch: { id: number; name: string };
}

interface Paginator {
    data: Student[];
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    years: Year[];
    yearId: number | null;
    branches: BranchOption[];
    branchId: number | null;
    search: string;
    students: Paginator;
}>();

const filterYear = ref<number | null>(props.yearId);
const filterBranch = ref<number | null>(props.branchId);
const filterSearch = ref(props.search);

function applyFilters(resetBranch = false) {
    if (resetBranch) {
        filterBranch.value = null;
    }
    router.get(
        '/students',
        {
            academic_year_id: filterYear.value,
            branch_id: filterBranch.value,
            q: filterSearch.value || undefined,
        },
        { preserveState: true },
    );
}

function clearFilters() {
    filterBranch.value = null;
    filterSearch.value = '';
    router.get('/students', { academic_year_id: filterYear.value }, { preserveState: true });
}

const createForm = useForm({
    branch_id: props.branchId as number | null,
    school_number: '',
    full_name: '',
    is_active: true,
});

function submitCreate() {
    createForm.post('/students', {
        onSuccess: () => createForm.reset('school_number', 'full_name'),
    });
}

const editing = ref<Student | null>(null);
const editForm = useForm({
    branch_id: null as number | null,
    school_number: '',
    full_name: '',
    is_active: true,
});

function openEdit(student: Student) {
    editing.value = student;
    editForm.branch_id = student.branch.id;
    editForm.school_number = student.school_number;
    editForm.full_name = student.full_name;
    editForm.is_active = student.is_active;
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.put(`/students/${editing.value.id}`, {
        onSuccess: () => (editing.value = null),
    });
}

function toggle(url: string) {
    router.post(url);
}
</script>

<template>
    <AppLayout title="Öğrenciler">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold text-gray-900">Öğrenciler</h1>
                <div class="flex gap-2">
                    <Link
                        href="/students/photos"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100"
                    >
                        Toplu Fotoğraf
                    </Link>
                    <Link
                        href="/students/import"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        Excelden İçe Aktar
                    </Link>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div>
                    <label for="filter-year" class="block text-sm font-medium text-gray-700">Akademik yıl</label>
                    <select
                        id="filter-year"
                        v-model="filterYear"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="applyFilters(true)"
                    >
                        <option v-for="year in years" :key="year.id" :value="year.id">
                            {{ year.name }}{{ year.is_active ? ' (aktif)' : '' }}
                        </option>
                    </select>
                </div>
                <div>
                    <label for="filter-branch" class="block text-sm font-medium text-gray-700">Şube</label>
                    <select
                        id="filter-branch"
                        v-model="filterBranch"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="applyFilters()"
                    >
                        <option :value="null">Tümü</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }}
                        </option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label for="filter-search" class="block text-sm font-medium text-gray-700">Arama (numara veya ad)</label>
                    <div class="mt-1 flex gap-2">
                        <input
                            id="filter-search"
                            v-model="filterSearch"
                            type="text"
                            placeholder="örn. 145 veya Ali"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @keydown.enter.prevent="applyFilters()"
                        />
                        <button
                            type="button"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                            @click="applyFilters()"
                        >
                            Ara
                        </button>
                        <button
                            v-if="branchId || search"
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            @click="clearFilters()"
                        >
                            Temizle
                        </button>
                    </div>
                </div>
            </div>

            <form
                v-if="branches.length > 0"
                class="mt-4 grid grid-cols-2 gap-3 border-t pt-4 md:grid-cols-5"
                @submit.prevent="submitCreate"
            >
                <div>
                    <label for="student-branch" class="block text-sm font-medium text-gray-700">Şube</label>
                    <select
                        id="student-branch"
                        v-model="createForm.branch_id"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }}
                        </option>
                    </select>
                    <p v-if="createForm.errors.branch_id" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.branch_id }}
                    </p>
                </div>
                <div>
                    <label for="student-number" class="block text-sm font-medium text-gray-700">Okul no</label>
                    <input
                        id="student-number"
                        v-model="createForm.school_number"
                        type="text"
                        required
                        maxlength="20"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.school_number" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.school_number }}
                    </p>
                </div>
                <div class="col-span-2">
                    <label for="student-name" class="block text-sm font-medium text-gray-700">Ad soyad</label>
                    <input
                        id="student-name"
                        v-model="createForm.full_name"
                        type="text"
                        required
                        maxlength="100"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.full_name" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.full_name }}
                    </p>
                </div>
                <div class="flex items-end gap-2">
                    <label class="flex items-center gap-2 pb-2 text-sm text-gray-700">
                        <input v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300" />
                        Aktif
                    </label>
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Ekle
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Okul No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Şube</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fotoğraf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="student in students.data" :key="student.id">
                        <td class="whitespace-nowrap px-4 py-3 text-gray-900">{{ student.school_number }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ student.full_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ student.branch.name }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span v-if="student.photo_path" class="text-sm text-green-700">Foto var</span>
                            <span v-else class="text-sm text-gray-400">Foto yok</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span
                                v-if="student.is_active"
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
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="openEdit(student)"
                                >
                                    Düzenle
                                </button>
                                <button
                                    v-if="!student.is_active"
                                    type="button"
                                    class="rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-700"
                                    @click="toggle(`/students/${student.id}/activate`)"
                                >
                                    Aktif Yap
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="toggle(`/students/${student.id}/deactivate`)"
                                >
                                    Pasife Al
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="students.data.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Öğrenci bulunamadı.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="students.total > 0" class="mt-4 flex items-center justify-between text-sm text-gray-600">
            <span>{{ students.from }}-{{ students.to }} / Toplam {{ students.total }}</span>
            <div class="flex gap-2">
                <Link
                    v-if="students.prev_page_url"
                    :href="students.prev_page_url"
                    class="rounded-md border border-gray-300 bg-white px-3 py-1 hover:bg-gray-100"
                >
                    Önceki
                </Link>
                <Link
                    v-if="students.next_page_url"
                    :href="students.next_page_url"
                    class="rounded-md border border-gray-300 bg-white px-3 py-1 hover:bg-gray-100"
                >
                    Sonraki
                </Link>
            </div>
        </div>

        <div v-if="editing" class="fixed inset-0 z-10 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Öğrenciyi düzenle</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <label for="edit-student-branch" class="block text-sm font-medium text-gray-700">Şube</label>
                        <select
                            id="edit-student-branch"
                            v-model="editForm.branch_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label for="edit-student-number" class="block text-sm font-medium text-gray-700">Okul no</label>
                        <input
                            id="edit-student-number"
                            v-model="editForm.school_number"
                            type="text"
                            required
                            maxlength="20"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.school_number" class="mt-1 text-sm text-red-600">
                            {{ editForm.errors.school_number }}
                        </p>
                    </div>
                    <div>
                        <label for="edit-student-name" class="block text-sm font-medium text-gray-700">Ad soyad</label>
                        <input
                            id="edit-student-name"
                            v-model="editForm.full_name"
                            type="text"
                            required
                            maxlength="100"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.full_name" class="mt-1 text-sm text-red-600">
                            {{ editForm.errors.full_name }}
                        </p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300" />
                        Aktif
                    </label>
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
