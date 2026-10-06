<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import NavIcon from '../../Components/NavIcon.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';
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

interface StudentGuardian {
    id: number;
    relation: string | null;
    first_name: string | null;
    last_name: string | null;
    phone: string | null;
    is_primary: boolean;
}

interface Student {
    id: number;
    school_number: string;
    first_name: string | null;
    last_name: string | null;
    full_name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    photo_path: string | null;
    photo_version: number | null;
    is_active: boolean;
    seating_assignments_count: number;
    branch: { id: number | null; name: string };
    guardians: StudentGuardian[];
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
    allBranches: BranchOption[];
    branchId: number | null;
    page: number;
    search: string;
    students: Paginator;
    totalStudents: number;
}>();

const filterYear = ref<number | null>(props.yearId);
const filterBranch = ref<number | null>(props.branchId);
const filterSearch = ref(props.search);

const currentBranchName = computed(() => props.branches.find((b) => b.id === props.branchId)?.name ?? '');

function applyFilters() {
    router.get(
        '/students',
        {
            academic_year_id: filterYear.value,
            q: filterSearch.value || undefined,
        },
        { preserveState: true },
    );
}

function jumpToBranch() {
    const index = props.branches.findIndex((b) => b.id === filterBranch.value);
    router.get(
        '/students',
        {
            academic_year_id: filterYear.value,
            page: index >= 0 ? index + 1 : 1,
        },
        { preserveState: true },
    );
}

function changeYear() {
    filterBranch.value = null;
    router.get('/students', { academic_year_id: filterYear.value }, { preserveState: true });
}

function clearFilters() {
    filterBranch.value = null;
    filterSearch.value = '';
    router.get('/students', { academic_year_id: filterYear.value }, { preserveState: true });
}

const listQuery = computed(() => {
    const params = new URLSearchParams();
    if (props.yearId) params.set('academic_year_id', String(props.yearId));
    if (props.page > 1) params.set('page', String(props.page));
    if (props.search) params.set('q', props.search);
    const query = params.toString();
    return query ? `?${query}` : '';
});

const createUrl = computed(() => {
    const params = new URLSearchParams(listQuery.value.slice(1));
    if (props.branchId) params.set('branch_id', String(props.branchId));
    const query = params.toString();
    return `/students/ekle${query ? `?${query}` : ''}`;
});

function editUrl(id: number): string {
    return `/students/${id}/duzenle${listQuery.value}`;
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
                <h1 class="shrink-0 text-2xl font-bold text-gray-900">Öğrenciler ({{ totalStudents }} Kayıt)</h1>
                <div v-if="totalStudents > 0" class="flex min-w-52 flex-1 items-center justify-center gap-2">
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
                        v-if="search"
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                        @click="clearFilters()"
                    >
                        Temizle
                    </button>
                </div>
                <div class="w-40">
                    <DropdownSelect
                        id="filter-year"
                        v-model="filterYear"
                        :options="years.map((year) => ({ value: year.id, label: year.name }))"
                        aria-label="Akademik Yıl"
                        @change="changeYear"
                    />
                </div>
                <div class="w-32">
                    <DropdownSelect
                        id="filter-branch"
                        v-model="filterBranch"
                        :options="branches.map((branch) => ({ value: branch.id, label: branch.name }))"
                        aria-label="Sınıf"
                        @change="jumpToBranch"
                    />
                </div>
                <div class="ml-auto flex shrink-0 gap-2">
                    <Link
                        v-if="totalStudents > 0"
                        href="/students/photos"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm font-semibold text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        Toplu Fotoğraf Ekle
                    </Link>
                    <Link
                        href="/students/import"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm font-semibold text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        İçe Aktar
                    </Link>
                    <Link
                        :href="createUrl"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        Öğrenci Ekle
                    </Link>
                </div>
            </div>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Okul No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sınıf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fotoğraf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="student in students.data" :key="student.id">
                        <td class="whitespace-nowrap px-4 py-3 text-gray-900">{{ student.school_number }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ student.branch.name }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <StudentAvatar
                                :photo-url="student.photo_path ? `/storage/${student.photo_path}?v=${student.photo_version ?? 0}` : null"
                                :full-name="student.full_name"
                                img-class="block h-16 w-12 rounded object-cover"
                                placeholder-class="w-12"
                                circle-class="w-10 text-sm"
                            />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ student.full_name }}</td>
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
                            <div class="flex items-center justify-start gap-2">
                                <Link
                                    :href="editUrl(student.id)"
                                    title="Düzenle"
                                    aria-label="Düzenle"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                </Link>
                                <button
                                    type="button"
                                    :title="isUsed(student) ? 'Oturma planında kullanılan öğrenci arşivlenemez' : 'Arşivle'"
                                    aria-label="Arşivle"
                                    :disabled="isUsed(student)"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-red-200 bg-red-50 px-4 text-sm text-red-600 shadow-sm hover:bg-red-100 hover:text-red-700 focus:border-red-500 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="askDelete(student)"
                                >
                                    <NavIcon name="archive" cls="h-5 w-5" />
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
            <span v-if="search" class="flex-1 text-center">{{ students.from }}-{{ students.to }} / Toplam {{ students.total }}</span>
            <span v-else class="flex-1 text-center">{{ currentBranchName }} Sınıfı ({{ students.total }} öğrenci)</span>
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

        <div v-if="allBranches.length === 0" class="mt-6 w-full max-w-[80%] rounded-md bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
            Öğrenci eklemek için önce sınıf ekleyin.
            <Link href="/branches" class="font-semibold underline">Sınıflar</Link>
        </div>

        <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Öğrenciyi Arşive Gönder</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ deleting.school_number }} — {{ deleting.full_name }} arşive gönderilsin mi? Kayıt listeden gizlenir, Arşiv sayfasından geri alınabilir.
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
                        class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                        @click="confirmDelete"
                    >
                        Arşive Gönder
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
