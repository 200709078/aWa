<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Graduate {
    id: number;
    year: number;
    number: string;
    full_name: string;
    phone: string | null;
    email: string | null;
    photo_path: string | null;
    education: string | null;
    company: string | null;
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
const withPhoto = ref(true);

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
        new CustomEvent('kelebek-toast', {
            detail: { type: 'success', messages: ['VCF dosyası indiriliyor.'] },
        }),
    );
}

const downloadUrl = computed(() => {
    const params = new URLSearchParams();
    if (filterYear.value) params.append('year', String(filterYear.value));
    if (filterSearch.value) params.append('q', filterSearch.value);
    params.append('photo', withPhoto.value ? '1' : '0');
    return `/mezunlar/vcf?${params.toString()}`;
});
</script>

<template>
    <AppLayout title="Mezunlar">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="shrink-0 text-2xl font-bold text-gray-900">Mezunlar</h1>
                <div v-if="totalGraduates > 0" class="flex min-w-52 flex-1 items-center justify-center gap-2">
                    <input
                        id="filter-search"
                        v-model="filterSearch"
                        type="text"
                        placeholder="Ad ara"
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
                </div>
                <a
                    :href="downloadUrl"
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700"
                    @click="notifyDownload"
                >
                    VCF İndir
                </a>
            </div>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label for="filter-year" class="block text-sm font-medium text-gray-700">Mezuniyet Yılı</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="filter-year"
                            v-model="filterYear"
                            :options="years.map((y) => ({ value: y, label: String(y) }))"
                            aria-label="Mezuniyet Yılı"
                            @change="jumpToYear"
                        />
                    </div>
                </div>
                <div class="flex h-9 items-center gap-2">
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="withPhoto"
                        aria-label="Fotoğrafları dahil et"
                        class="flex h-6 w-11 shrink-0 items-center rounded-full px-0.5 transition-colors"
                        :class="withPhoto ? 'bg-indigo-600' : 'bg-gray-300'"
                        @click="withPhoto = !withPhoto"
                    >
                        <span
                            class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                            :class="withPhoto ? 'translate-x-5' : 'translate-x-0'"
                        ></span>
                    </button>
                    <span class="text-sm text-gray-700">Fotoğraflar</span>
                </div>
                <button
                    v-if="search"
                    type="button"
                    class="inline-flex h-9 items-center justify-center rounded-md px-2 text-sm text-gray-500 hover:text-indigo-700 hover:underline"
                    @click="clearFilters()"
                >
                    Temizle
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Yıl</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">No</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Telefon</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">E-posta</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Eğitim</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İş</th>
                            <th v-if="withPhoto" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fotoğraf</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="graduate in graduates.data" :key="graduate.id">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ graduate.year }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-900">{{ graduate.number }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ graduate.full_name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ graduate.phone ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ graduate.email ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ graduate.education ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ graduate.company ?? '—' }}</td>
                            <td v-if="withPhoto" class="px-4 py-3">
                                <StudentAvatar
                                    :photo-url="graduate.photo_path ? `/storage/${graduate.photo_path}` : null"
                                    :full-name="graduate.full_name"
                                    img-class="h-10 w-8 rounded object-cover"
                                />
                            </td>
                        </tr>
                        <tr v-if="graduates.data.length === 0">
                            <td :colspan="withPhoto ? 8 : 7" class="px-4 py-6 text-center text-gray-500">Kayıt bulunamadı.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center gap-2">
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
        </div>
    </AppLayout>
</template>
