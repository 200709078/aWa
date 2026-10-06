<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import NavIcon from '../../Components/NavIcon.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';
import type { EduRow } from '../../types/graduateForm';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Graduate {
    id: number;
    year: number;
    number: string;
    full_name: string;
    first_name: string | null;
    last_name: string | null;
    phone: string | null;
    email: string | null;
    photo_path: string | null;
    photo_version: number | null;
    educations: string[];
    education_rows: EduRow[];
    institution_name: string | null;
    faculty: string | null;
    department: string | null;
    company: string | null;
    job_city: string | null;
}

interface Paginator {
    data: Graduate[];
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    years: number[];
    year: number | null;
    page: number;
    search: string;
    graduates: Paginator;
    totalGraduates: number;
}>();

const filterYear = ref<number | null>(props.year);
const filterSearch = ref(props.search);

function jumpToYear() {
    const index = props.years.indexOf(filterYear.value as number);
    router.get('/mezunlar', { page: index >= 0 ? index + 1 : 1 });
}

function applyFilters() {
    router.get(
        '/mezunlar',
        {
            q: filterSearch.value || undefined,
        },
        { preserveState: true },
    );
}

function clearFilters() {
    filterSearch.value = '';
    router.get('/mezunlar', { page: props.page }, { preserveState: true });
}

function notifyDownload() {
    window.dispatchEvent(
        new CustomEvent('awa-toast', {
            detail: { type: 'success', messages: ['VCF dosyası indiriliyor.'] },
        }),
    );
}

const deleting = ref<Graduate | null>(null);

const listQuery = computed(() => {
    const params = new URLSearchParams();
    if (props.page > 1) params.set('page', String(props.page));
    if (props.search) params.set('q', props.search);
    const query = params.toString();
    return query ? `?${query}` : '';
});

const createUrl = computed(() => `/mezunlar/ekle${listQuery.value}`);

function editUrl(id: number): string {
    return `/mezunlar/${id}/duzenle${listQuery.value}`;
}

function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/mezunlar/${deleting.value.id}`, {
        onFinish: () => (deleting.value = null),
    });
}

const downloadUrl = computed(() => {
    const params = new URLSearchParams();
    if (filterYear.value) params.append('year', String(filterYear.value));
    if (filterSearch.value) params.append('q', filterSearch.value);
    params.append('photo', '1');
    return `/mezunlar/vcf?${params.toString()}`;
});
</script>

<template>
    <AppLayout title="Mezunlar">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="shrink-0 text-2xl font-bold text-gray-900">Mezunlar ({{ totalGraduates }} Kayıt)</h1>
                <div v-if="totalGraduates > 0" class="flex min-w-52 flex-1 items-center justify-center gap-2">
                    <input
                        id="filter-search"
                        v-model="filterSearch"
                        type="text"
                        placeholder="No veya ad ara"
                        aria-label="Mezun ara"
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
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md px-2 text-sm text-gray-500 hover:text-indigo-700 hover:underline"
                        @click="clearFilters()"
                    >
                        Temizle
                    </button>
                </div>
                <div class="w-32">
                    <DropdownSelect
                        id="filter-year"
                        v-model="filterYear"
                        :options="years.map((y) => ({ value: y, label: String(y) }))"
                        aria-label="Mezuniyet Yılı"
                        @change="jumpToYear"
                    />
                </div>
                <a
                    :href="downloadUrl"
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700"
                    @click="notifyDownload"
                >
                    VCF İndir
                </a>
                <Link
                    :href="createUrl"
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    Mezun Ekle
                </Link>
            </div>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Yıl</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">No</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fotoğraf</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Telefon</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Eğitim</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="graduate in graduates.data" :key="graduate.id">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ graduate.year }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-900">{{ graduate.number }}</td>
                            <td class="px-4 py-3">
                                <StudentAvatar
                                    :photo-url="graduate.photo_path ? `/storage/${graduate.photo_path}?v=${graduate.photo_version ?? 0}` : null"
                                    :full-name="graduate.full_name"
                                    img-class="h-16 w-12 rounded object-cover"
                                />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ graduate.full_name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ graduate.phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                <div v-for="(item, i) in graduate.educations" :key="i">• {{ item }}</div>
                                <span v-if="graduate.educations.length === 0">—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-left">
                                <div class="flex items-center justify-start gap-2">
                                    <Link
                                        :href="editUrl(graduate.id)"
                                        title="Düzenle"
                                        aria-label="Düzenle"
                                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                    </Link>
                                    <button
                                        type="button"
                                        title="Arşivle"
                                        aria-label="Arşivle"
                                        class="inline-flex h-9 items-center justify-center rounded-md border border-red-200 bg-red-50 px-4 text-sm text-red-600 shadow-sm hover:bg-red-100 hover:text-red-700 focus:border-red-500 focus:ring-red-500"
                                        @click="deleting = graduate"
                                    >
                                        <NavIcon name="archive" cls="h-5 w-5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="graduates.data.length === 0">
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">Kayıt bulunamadı.</td>
                        </tr>
                    </tbody>
                </table>
        </div>

        <div class="mt-4 flex w-full max-w-[80%] items-center text-sm text-gray-600">
                <div class="flex w-24 justify-start">
                    <Link
                        v-if="graduates.prev_page_url"
                        :href="graduates.prev_page_url"
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
                <span v-if="search" class="flex-1 text-center">{{ graduates.from }}-{{ graduates.to }} / Toplam {{ graduates.total }}</span>
                <span v-else class="flex-1 text-center">{{ year }} Mezunları ({{ graduates.total }} kayıt)</span>
                <div class="flex w-24 justify-end">
                    <Link
                        v-if="graduates.next_page_url"
                        :href="graduates.next_page_url"
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


        <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Mezunu Arşive Gönder</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ deleting.year }} / {{ deleting.number }} — {{ deleting.full_name }} arşive gönderilsin mi? Kayıt listeden gizlenir, Arşiv sayfasından geri alınabilir.
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
