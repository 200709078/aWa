<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface StudentEnrollment {
    id: number;
    year: string;
    academic_year_id: number;
    branch: string;
    school_number: string;
}

interface ArchivedStudent {
    id: number;
    full_name: string;
    photo_path: string | null;
    deleted_at: string | null;
    enrollments: StudentEnrollment[];
}

interface ArchivedGraduate {
    id: number;
    year: number;
    number: string;
    full_name: string;
    phone: string | null;
    photo_path: string | null;
    deleted_at: string | null;
}

interface Paginator<T> {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    tab: 'students' | 'graduates';
    search: string;
    students: Paginator<ArchivedStudent>;
    graduates: Paginator<ArchivedGraduate>;
    trashedStudents: number;
    trashedGraduates: number;
}>();

const filterSearch = ref(props.search);

function switchTab(tab: 'students' | 'graduates') {
    router.get('/arsiv', { tab, q: filterSearch.value || undefined }, { preserveState: true });
}

function applySearch() {
    router.get('/arsiv', { tab: props.tab, q: filterSearch.value || undefined }, { preserveState: true });
}

function clearSearch() {
    filterSearch.value = '';
    router.get('/arsiv', { tab: props.tab }, { preserveState: true });
}

const restoringStudent = ref<ArchivedStudent | null>(null);
const restoreNumbers = ref<Record<number, string>>({});
const restoreProcessing = ref(false);

function openRestoreStudent(student: ArchivedStudent) {
    restoringStudent.value = student;
    restoreNumbers.value = {};
    for (const e of student.enrollments) {
        restoreNumbers.value[e.id] = e.school_number;
    }
}

function submitRestoreStudent() {
    if (!restoringStudent.value) return;
    restoreProcessing.value = true;
    router.post(
        `/arsiv/ogrenciler/${restoringStudent.value.id}/restore`,
        { numbers: restoreNumbers.value },
        {
            preserveState: true,
            onSuccess: () => {
                restoringStudent.value = null;
                restoreProcessing.value = false;
            },
            onError: () => {
                restoreProcessing.value = false;
            },
            onFinish: () => {
                restoreProcessing.value = false;
            },
        },
    );
}

const forcingStudent = ref<ArchivedStudent | null>(null);

function submitForceStudent() {
    if (!forcingStudent.value) return;
    router.delete(`/arsiv/ogrenciler/${forcingStudent.value.id}`, {
        onFinish: () => (forcingStudent.value = null),
    });
}

const restoringGraduate = ref<ArchivedGraduate | null>(null);
const restoreGraduateNumber = ref('');
const restoreGraduateProcessing = ref(false);

function openRestoreGraduate(g: ArchivedGraduate) {
    restoringGraduate.value = g;
    restoreGraduateNumber.value = g.number;
}

function submitRestoreGraduate() {
    if (!restoringGraduate.value) return;
    restoreGraduateProcessing.value = true;
    router.post(
        `/arsiv/mezunlar/${restoringGraduate.value.id}/restore`,
        { graduation_number: restoreGraduateNumber.value },
        {
            preserveState: true,
            onSuccess: () => {
                restoringGraduate.value = null;
            },
            onFinish: () => {
                restoreGraduateProcessing.value = false;
            },
        },
    );
}

const forcingGraduate = ref<ArchivedGraduate | null>(null);

function submitForceGraduate() {
    if (!forcingGraduate.value) return;
    router.delete(`/arsiv/mezunlar/${forcingGraduate.value.id}`, {
        onFinish: () => (forcingGraduate.value = null),
    });
}
</script>

<template>
    <AppLayout title="Arşiv">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="shrink-0 text-2xl font-bold text-gray-900">Arşiv</h1>
                <div class="flex gap-2">
                    <button
                        type="button"
                        :class="[
                            'inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-semibold shadow-sm',
                            tab === 'students'
                                ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                : 'border border-gray-300 bg-gray-50 text-gray-700 hover:bg-indigo-100 hover:text-indigo-800',
                        ]"
                        @click="switchTab('students')"
                    >
                        Öğrenciler ({{ trashedStudents }})
                    </button>
                    <button
                        type="button"
                        :class="[
                            'inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-semibold shadow-sm',
                            tab === 'graduates'
                                ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                : 'border border-gray-300 bg-gray-50 text-gray-700 hover:bg-indigo-100 hover:text-indigo-800',
                        ]"
                        @click="switchTab('graduates')"
                    >
                        Mezunlar ({{ trashedGraduates }})
                    </button>
                </div>
                <div class="flex min-w-52 flex-1 items-center justify-center gap-2">
                    <input
                        v-model="filterSearch"
                        type="text"
                        placeholder="Numara veya ad ara"
                        aria-label="Arşivde ara"
                        class="block h-9 w-64 rounded-md border-gray-300 bg-gray-50 px-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @keydown.enter.prevent="applySearch()"
                    />
                    <button
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                        @click="applySearch()"
                    >
                        Ara
                    </button>
                    <button
                        v-if="search"
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md px-2 text-sm text-gray-500 hover:text-indigo-700 hover:underline"
                        @click="clearSearch()"
                    >
                        Temizle
                    </button>
                </div>
            </div>
            <p class="mt-3 text-sm text-gray-600">
                Normal listelerdeki Sil işlemi kaydı buraya gönderir. Kalıcı silme yalnızca buradan yapılır ve geri alınamaz. Numarası başka kayda verilen arşiv kaydı, yeni boş bir numara girilerek geri alınabilir.
            </p>
            <p v-if="$page.props.errors.restore" class="mt-3 rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                {{ $page.props.errors.restore }}
            </p>
        </div>

        <div v-if="tab === 'students'" class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fotoğraf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Kayıtlar</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Arşiv Tarihi</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="student in students.data" :key="student.id">
                        <td class="whitespace-nowrap px-4 py-3">
                            <StudentAvatar
                                :photo-url="student.photo_path ? `/storage/${student.photo_path}` : null"
                                :full-name="student.full_name"
                                img-class="block h-16 w-12 rounded object-cover"
                                placeholder-class="w-12"
                                circle-class="w-10 text-sm"
                            />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ student.full_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <div v-for="e in student.enrollments" :key="e.id">{{ e.year }} — {{ e.branch }} — {{ e.school_number }}</div>
                            <span v-if="student.enrollments.length === 0">—</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ student.deleted_at ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    title="Arşivden çıkar"
                                    class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                                    @click="openRestoreStudent(student)"
                                >
                                    Geri Al
                                </button>
                                <button
                                    type="button"
                                    title="Kalıcı sil"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-red-600 shadow-sm hover:bg-red-50 hover:text-red-700"
                                    @click="forcingStudent = student"
                                >
                                    Kalıcı Sil
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="students.data.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Arşivde öğrenci yok.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="tab === 'graduates'" class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Yıl</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fotoğraf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Arşiv Tarihi</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="g in graduates.data" :key="g.id">
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ g.year }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-900">{{ g.number }}</td>
                        <td class="px-4 py-3">
                            <StudentAvatar
                                :photo-url="g.photo_path ? `/storage/${g.photo_path}` : null"
                                :full-name="g.full_name"
                                img-class="h-16 w-12 rounded object-cover"
                            />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ g.full_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ g.deleted_at ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    title="Arşivden çıkar"
                                    class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                                    @click="openRestoreGraduate(g)"
                                >
                                    Geri Al
                                </button>
                                <button
                                    type="button"
                                    title="Kalıcı sil"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-red-600 shadow-sm hover:bg-red-50 hover:text-red-700"
                                    @click="forcingGraduate = g"
                                >
                                    Kalıcı Sil
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="graduates.data.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Arşivde mezun yok.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="restoringStudent" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Arşivden Çıkar</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ restoringStudent.full_name }} geri alınsın mı? Numara doluysa yeni boş bir numara yazabilirsiniz.
                </p>
                <div class="mt-4 space-y-3">
                    <div v-for="e in restoringStudent.enrollments" :key="e.id">
                        <label :for="`restore-num-${e.id}`" class="block text-sm font-medium text-gray-700">
                            {{ e.year }} — {{ e.branch }} numarası
                        </label>
                        <input
                            :id="`restore-num-${e.id}`"
                            v-model="restoreNumbers[e.id]"
                            type="text"
                            maxlength="20"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100"
                        @click="restoringStudent = null"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        :disabled="restoreProcessing"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                        @click="submitRestoreStudent"
                    >
                        Geri Al
                    </button>
                </div>
            </div>
        </div>

        <div v-if="forcingStudent" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Kalıcı Sil</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ forcingStudent.full_name }} kalıcı olarak silinsin mi? Fotoğraf da silinir. Bu işlem geri alınamaz.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100"
                        @click="forcingStudent = null"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-red-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-red-700"
                        @click="submitForceStudent"
                    >
                        Kalıcı Sil
                    </button>
                </div>
            </div>
        </div>

        <div v-if="restoringGraduate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Arşivden Çıkar</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ restoringGraduate.year }} / {{ restoringGraduate.number }} — {{ restoringGraduate.full_name }} geri alınsın mı?
                </p>
                <div class="mt-4">
                    <label for="restore-graduate-number" class="block text-sm font-medium text-gray-700">Mezuniyet No</label>
                    <input
                        id="restore-graduate-number"
                        v-model="restoreGraduateNumber"
                        type="text"
                        maxlength="20"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100"
                        @click="restoringGraduate = null"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        :disabled="restoreGraduateProcessing"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                        @click="submitRestoreGraduate"
                    >
                        Geri Al
                    </button>
                </div>
            </div>
        </div>

        <div v-if="forcingGraduate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Kalıcı Sil</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ forcingGraduate.year }} / {{ forcingGraduate.number }} — {{ forcingGraduate.full_name }} kalıcı olarak silinsin mi? Bu işlem geri alınamaz.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100"
                        @click="forcingGraduate = null"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-red-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-red-700"
                        @click="submitForceGraduate"
                    >
                        Kalıcı Sil
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="(tab === 'students' ? students.total : graduates.total) > 0"
            class="mt-4 flex w-full max-w-[80%] items-center text-sm text-gray-600"
        >
            <div class="flex w-24 justify-start">
                <Link
                    v-if="(tab === 'students' ? students.prev_page_url : graduates.prev_page_url)"
                    :href="(tab === 'students' ? students.prev_page_url : graduates.prev_page_url) as string"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100"
                >
                    Önceki
                </Link>
            </div>
            <span class="flex-1 text-center">Toplam {{ tab === 'students' ? students.total : graduates.total }} arşiv kaydı</span>
            <div class="flex w-24 justify-end">
                <Link
                    v-if="(tab === 'students' ? students.next_page_url : graduates.next_page_url)"
                    :href="(tab === 'students' ? students.next_page_url : graduates.next_page_url) as string"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100"
                >
                    Sonraki
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
