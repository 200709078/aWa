<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
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

interface Row {
    id: number;
    school_number: string;
    full_name: string;
    branch_name: string;
    photo_path: string | null;
    photo_version: number | null;
    has_form: boolean;
    form_updated_at: string | null;
    badges: string[];
}

interface Paginator {
    data: Row[];
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
    page: number;
    search: string;
    status: string;
    students: Paginator;
    totalStudents: number;
}>();

const filterYear = ref<number | null>(props.yearId);
const filterBranch = ref<number | null>(props.branchId);
const filterSearch = ref(props.search);
const filterStatus = ref(props.status);

const statusOptions = [
    { value: 'tumu', label: 'Tümü' },
    { value: 'var', label: 'Formu Olan' },
    { value: 'yok', label: 'Formu Olmayan' },
];

function baseParams() {
    return {
        academic_year_id: filterYear.value,
        branch_id: filterBranch.value || undefined,
        q: filterSearch.value || undefined,
        durum: filterStatus.value !== 'tumu' ? filterStatus.value : undefined,
    };
}

function applyFilters() {
    router.get('/bilgi-formlari', baseParams(), { preserveState: true });
}

function changeYear() {
    filterBranch.value = null;
    router.get('/bilgi-formlari', { academic_year_id: filterYear.value }, { preserveState: true });
}

function clearFilters() {
    filterBranch.value = null;
    filterSearch.value = '';
    filterStatus.value = 'tumu';
    router.get('/bilgi-formlari', { academic_year_id: filterYear.value }, { preserveState: true });
}

function jumpToBranch() {
    const index = props.branches.findIndex((b) => b.id === filterBranch.value);
    router.get(
        '/bilgi-formlari',
        {
            academic_year_id: filterYear.value,
            page: index >= 0 ? index + 1 : 1,
        },
        { preserveState: true },
    );
}

const currentBranchName = computed(() => props.branches.find((b) => b.id === props.branchId)?.name ?? '');

const selected = ref<number[]>([]);

const pageIds = computed(() => props.students.data.map((r) => r.id));

const allPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selected.value.includes(id)));

function toggleAllPage() {
    if (allPageSelected.value) {
        selected.value = selected.value.filter((id) => !pageIds.value.includes(id));
    } else {
        selected.value = [...new Set([...selected.value, ...pageIds.value])];
    }
}

function toggleOne(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((v) => v !== id)
        : [...selected.value, id];
}

const printUrl = computed(() =>
    selected.value.length > 0 ? `/bilgi-formlari/yazdir?ids=${encodeURIComponent(selected.value.join(','))}` : '',
);

const riskUrl = computed(() =>
    props.branchId ? `/bilgi-formlari/risk-haritasi?branch_id=${props.branchId}` : '',
);

function openPrint(url: string, width = 850, height = 900) {
    const left = Math.max(0, Math.round((window.screen.width - width) / 2));
    const top = Math.max(0, Math.round((window.screen.height - height) / 2));
    const features = `width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,resizable=no,scrollbars=yes`;
    const win = window.open(url, 'awa-print', features);
    if (!win) window.open(url, '_blank');
}

function printOne(id: number) {
    openPrint(`/bilgi-formlari/yazdir?ids=${id}`);
}

function printSelected() {
    if (printUrl.value) openPrint(printUrl.value);
}

function printRisk() {
    if (riskUrl.value) openPrint(riskUrl.value, 1150, 800);
}

function editUrl(id: number): string {
    const params = new URLSearchParams();
    if (props.yearId) params.set('academic_year_id', String(props.yearId));
    if (props.branchId) params.set('branch_id', String(props.branchId));
    if (props.search) params.set('q', props.search);
    if (props.status !== 'tumu') params.set('durum', props.status);
    const query = params.toString();
    return `/bilgi-formlari/${id}/duzenle${query ? `?${query}` : ''}`;
}
</script>

<template>
    <AppLayout title="Bilgi Formları">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="shrink-0 text-2xl font-bold text-gray-900">Bilgi Formları ({{ totalStudents }} Kayıt)</h1>
                <div class="ml-auto flex shrink-0 gap-2">
                    <button
                        type="button"
                        :disabled="!riskUrl"
                        title="Seçili sınıfın risk haritası"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm font-semibold text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="printRisk()"
                    >
                        Risk Haritası
                    </button>
                    <button
                        type="button"
                        :disabled="!printUrl"
                        title="Seçilenleri yazdır"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm font-semibold text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="printSelected()"
                    >
                        Yazdır{{ selected.length > 0 ? ` (${selected.length})` : '' }}
                    </button>
                    <Link
                        href="/bilgi-formlari/ice-aktar"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        İçe Aktar
                    </Link>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <div class="flex shrink-0 items-center gap-2">
                    <input
                        v-model="filterSearch"
                        type="text"
                        placeholder="Numara veya ad ara"
                        aria-label="Öğrenci ara"
                        class="block h-9 w-56 rounded-md border-gray-300 bg-gray-50 px-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @keydown.enter.prevent="applyFilters()"
                    />
                    <button
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                        @click="applyFilters()"
                    >
                        Ara
                    </button>
                    <button
                        v-if="search || status !== 'tumu'"
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md px-2 text-sm text-gray-500 hover:text-indigo-700 hover:underline"
                        @click="clearFilters()"
                    >
                        Temizle
                    </button>
                </div>
                <div class="w-36">
                    <DropdownSelect
                        id="bf-year"
                        v-model="filterYear"
                        :options="years.map((y) => ({ value: y.id, label: y.name }))"
                        aria-label="Akademik Yıl"
                        @change="changeYear"
                    />
                </div>
                <div class="w-28">
                    <DropdownSelect
                        id="bf-branch"
                        v-model="filterBranch"
                        :options="branches.map((b) => ({ value: b.id, label: b.name }))"
                        aria-label="Sınıf"
                        @change="jumpToBranch"
                    />
                </div>
                <div class="w-36">
                    <DropdownSelect
                        id="bf-status"
                        v-model="filterStatus"
                        :options="statusOptions"
                        aria-label="Durum"
                        @change="applyFilters"
                    />
                </div>
            </div>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <input
                                type="checkbox"
                                :checked="allPageSelected"
                                :indeterminate="selected.length > 0 && !allPageSelected"
                                title="Sayfadakileri seç"
                                aria-label="Sayfadakileri seç"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                @change="toggleAllPage()"
                            />
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Okul No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sınıf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fotoğraf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Form</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rozetler</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="row in students.data" :key="row.id">
                        <td class="whitespace-nowrap px-4 py-3">
                            <input
                                type="checkbox"
                                :checked="selected.includes(row.id)"
                                :aria-label="`${row.full_name} seç`"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                @change="toggleOne(row.id)"
                            />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-900">{{ row.school_number }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ row.branch_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <StudentAvatar
                                :photo-url="row.photo_path ? `/storage/${row.photo_path}?v=${row.photo_version ?? 0}` : null"
                                :full-name="row.full_name"
                                img-class="block h-16 w-12 rounded object-cover"
                                placeholder-class="w-12"
                                circle-class="w-10 text-sm"
                            />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ row.full_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span
                                v-if="row.has_form"
                                class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"
                                :title="row.form_updated_at ?? ''"
                            >
                                Var{{ row.form_updated_at ? ` · ${row.form_updated_at}` : '' }}
                            </span>
                            <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Yok</span>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="row.badges.length > 0" class="flex flex-wrap gap-1">
                                <span
                                    v-for="badge in row.badges"
                                    :key="badge"
                                    class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800"
                                >
                                    {{ badge }}
                                </span>
                            </div>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-left">
                            <div class="flex items-center justify-start gap-2">
                                <Link
                                    :href="editUrl(row.id)"
                                    title="Formu düzenle"
                                    aria-label="Formu düzenle"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                </Link>
                                <button
                                    type="button"
                                    title="Yazdır"
                                    aria-label="Yazdır"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                                    @click="printOne(row.id)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 14h12v8H6z" /></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="students.data.length === 0">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">Kayıt bulunamadı.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="students.total > 0" class="mt-4 flex w-full max-w-[80%] items-center text-sm text-gray-600">
            <div class="flex w-24 justify-start">
                <Link
                    v-if="students.prev_page_url"
                    :href="students.prev_page_url"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                >
                    Önceki
                </Link>
            </div>
            <span v-if="search" class="flex-1 text-center">Toplam {{ students.total }} kayıt</span>
            <span v-else class="flex-1 text-center">[{{ currentBranchName }}] Toplam {{ students.total }} kayıt</span>
            <div class="flex w-24 justify-end">
                <Link
                    v-if="students.next_page_url"
                    :href="students.next_page_url"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                >
                    Sonraki
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
