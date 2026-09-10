<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
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
    seating_assignments_count: number;
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

const deleting = ref<Student | null>(null);

function isUsed(student: Student): boolean {
    return student.seating_assignments_count > 0;
}

function askDelete(student: Student) {
    deleting.value = student;
}

function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/students/${deleting.value.id}`, {
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <AppLayout title="Öğrenciler">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="shrink-0 text-2xl font-bold text-gray-900">Öğrenciler</h1>
                <div class="flex min-w-52 flex-1 items-center justify-center gap-2">
                    <input
                        id="filter-search"
                        v-model="filterSearch"
                        type="text"
                        placeholder="Numara veya ad ara"
                        aria-label="Öğrenci ara"
                        class="block h-9 w-64 rounded-md border-gray-300 bg-gray-50 px-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @keydown.enter.prevent="applyFilters()"
                    />
                    <button
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                        @click="applyFilters()"
                    >
                        Ara
                    </button>
                    <button
                        v-if="branchId || search"
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                        @click="clearFilters()"
                    >
                        Temizle
                    </button>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Link
                        href="/students/photos"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm font-semibold text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        Toplu Fotoğraf
                    </Link>
                    <Link
                        href="/students/import"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm font-semibold text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        Excelden İçe Aktar
                    </Link>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 md:ml-auto md:max-w-md md:grid-cols-2">
                <div>
                    <label for="filter-year" class="block text-sm font-medium text-gray-700">Akademik Yıl</label>
                    <select
                        id="filter-year"
                        v-model="filterYear"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="applyFilters(true)"
                    >
                        <option v-for="year in years" :key="year.id" :value="year.id">
                            {{ year.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label for="filter-branch" class="block text-sm font-medium text-gray-700">Sınıf</label>
                    <select
                        id="filter-branch"
                        v-model="filterBranch"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="applyFilters()"
                    >
                        <option :value="null">Tümü</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Okul No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sınıf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fotoğraf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="student in students.data" :key="student.id">
                        <td class="whitespace-nowrap px-4 py-3 text-gray-900">{{ student.school_number }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ student.full_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ student.branch.name }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <StudentAvatar
                                :photo-url="student.photo_path ? `/storage/${student.photo_path}` : null"
                                :full-name="student.full_name"
                                img-class="block h-auto w-10 rounded object-contain"
                                placeholder-class="w-10"
                                circle-class="w-8 text-xs"
                            />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="student.is_active"
                                    :title="student.is_active ? 'Pasif' : 'Aktif'"
                                    @click="
                                        toggle(
                                            student.is_active
                                                ? `/students/${student.id}/deactivate`
                                                : `/students/${student.id}/activate`,
                                        )
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                                        student.is_active ? 'bg-indigo-600' : 'bg-gray-300',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5',
                                            student.is_active ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                                        ]"
                                    />
                                </button>
                                <span
                                    v-if="student.is_active"
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
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                    @click="openEdit(student)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                </button>
                                <button
                                    type="button"
                                    :title="isUsed(student) ? 'Oturma planında kullanılan öğrenci silinemez' : 'Sil'"
                                    aria-label="Sil"
                                    :disabled="isUsed(student)"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-red-600 shadow-sm hover:bg-red-50 hover:text-red-700 focus:border-red-500 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="askDelete(student)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
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
        <p v-if="$page.props.errors.student" class="mt-4 rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
            {{ $page.props.errors.student }}
        </p>

        <div v-if="students.total > 0" class="mt-4 flex w-full max-w-[80%] items-center text-sm text-gray-600">
            <div class="flex w-24 justify-start">
                <Link
                    v-if="students.prev_page_url"
                    :href="students.prev_page_url"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    Önceki
                </Link>
                <span
                    v-else
                    aria-disabled="true"
                    class="cursor-not-allowed rounded-md border border-gray-200 bg-gray-50 px-3 py-1 text-gray-400"
                >
                    Önceki
                </span>
            </div>
            <span class="flex-1 text-center">{{ students.from }}-{{ students.to }} / Toplam {{ students.total }}</span>
            <div class="flex w-24 justify-end">
                <Link
                    v-if="students.next_page_url"
                    :href="students.next_page_url"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    Sonraki
                </Link>
                <span
                    v-else
                    aria-disabled="true"
                    class="cursor-not-allowed rounded-md border border-gray-200 bg-gray-50 px-3 py-1 text-gray-400"
                >
                    Sonraki
                </span>
            </div>
        </div>

        <div v-if="branches.length > 0" class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Yeni Öğrenci Ekle</h2>

            <form class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5" @submit.prevent="submitCreate">
                <div>
                    <label for="student-branch" class="block text-sm font-medium text-gray-700">Sınıf</label>
                    <select
                        id="student-branch"
                        v-model="createForm.branch_id"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                    <label for="student-number" class="block text-sm font-medium text-gray-700">Okul No</label>
                    <input
                        id="student-number"
                        v-model="createForm.school_number"
                        type="text"
                        required
                        maxlength="20"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.school_number" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.school_number }}
                    </p>
                </div>
                <div class="col-span-2">
                    <label for="student-name" class="block text-sm font-medium text-gray-700">Ad Soyad</label>
                    <input
                        id="student-name"
                        v-model="createForm.full_name"
                        type="text"
                        required
                        maxlength="100"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.full_name" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.full_name }}
                    </p>
                </div>
                <div class="flex items-end">
                    <button
                        type="submit"
                        title="Ekle"
                        aria-label="Ekle"
                        :disabled="createForm.processing"
                        class="inline-flex h-9 w-full items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </button>
                </div>
            </form>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-sm overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Öğrenciyi Düzenle</h2>
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
                                class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                @click="editing = null"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="edit-student-branch" class="block text-sm font-medium text-gray-700">Sınıf</label>
                        <select
                            id="edit-student-branch"
                            v-model="editForm.branch_id"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label for="edit-student-number" class="block text-sm font-medium text-gray-700">Okul No</label>
                        <input
                            id="edit-student-number"
                            v-model="editForm.school_number"
                            type="text"
                            required
                            maxlength="20"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.school_number" class="mt-1 text-sm text-red-600">
                            {{ editForm.errors.school_number }}
                        </p>
                    </div>
                    <div>
                        <label for="edit-student-name" class="block text-sm font-medium text-gray-700">Ad Soyad</label>
                        <input
                            id="edit-student-name"
                            v-model="editForm.full_name"
                            type="text"
                            required
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.full_name" class="mt-1 text-sm text-red-600">
                            {{ editForm.errors.full_name }}
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Öğrenciyi Sil</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ deleting.school_number }} — {{ deleting.full_name }} silinsin mi? Bu işlem geri alınamaz.
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
