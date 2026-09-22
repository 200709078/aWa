<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';
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
    educations: string[];
    education_rows: EduRow[];
    institution_name: string | null;
    faculty: string | null;
    department: string | null;
    company: string | null;
    job_city: string | null;
}

interface EduRow {
    id: number | null;
    city: string;
    institution_name: string;
    faculty: string;
    department: string;
}

function blankEduRow(): EduRow {
    return { id: null, city: '', institution_name: '', faculty: '', department: '' };
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
        new CustomEvent('kelebek-toast', {
            detail: { type: 'success', messages: ['VCF dosyası indiriliyor.'] },
        }),
    );
}

const createForm = useForm({
    graduation_year: new Date().getFullYear(),
    graduation_number: '',
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    educations: [] as EduRow[],
    company: '',
    job_city: '',
    photo: null as File | null,
});

const photoInput = ref<HTMLInputElement | null>(null);
const editPhotoInput = ref<HTMLInputElement | null>(null);

function submitCreate() {
    createForm.post('/mezunlar', {
        onSuccess: () => {
            creating.value = false;
            createForm.reset(
                'graduation_number',
                'first_name',
                'last_name',
                'phone',
                'email',
                'educations',
                'company',
                'job_city',
                'photo',
            );
            createForm.educations = [];
            if (photoInput.value) photoInput.value.value = '';
        },
    });
}

const editing = ref<Graduate | null>(null);
const creating = ref(false);

const createPhotoSrc = computed(() => {
    if (createForm.photo) return URL.createObjectURL(createForm.photo);
    return null;
});

const createDisplayName = computed(() => `${createForm.first_name} ${createForm.last_name}`.trim());

const editPhotoSrc = computed(() => {
    if (editForm.photo) return URL.createObjectURL(editForm.photo);
    if (editing.value?.photo_path) return `/storage/${editing.value.photo_path}`;
    return null;
});

const editDisplayName = computed(() => `${editForm.first_name} ${editForm.last_name}`.trim());

const editForm = useForm({
    graduation_year: 0,
    graduation_number: '',
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    educations: [] as EduRow[],
    company: '',
    job_city: '',
    photo: null as File | null,
});

function addEducationRow(form: { educations: EduRow[] }) {
    form.educations.push(blankEduRow());
}

function openEdit(graduate: Graduate) {
    editing.value = graduate;
    editForm.graduation_year = graduate.year;
    editForm.graduation_number = graduate.number;
    editForm.first_name = graduate.first_name ?? '';
    editForm.last_name = graduate.last_name ?? '';
    editForm.phone = graduate.phone ?? '';
    editForm.email = graduate.email ?? '';
    editForm.educations = graduate.education_rows.length > 0
        ? graduate.education_rows.map((row) => ({ ...row }))
        : [blankEduRow()];
    editForm.company = graduate.company ?? '';
    editForm.job_city = graduate.job_city ?? '';
    editForm.photo = null;
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.transform((data) => ({ ...data, _method: 'put' })).post(`/mezunlar/${editing.value.id}`, {
        onSuccess: () => (editing.value = null),
    });
}

const deleting = ref<Graduate | null>(null);

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
                <h1 class="shrink-0 text-2xl font-bold text-gray-900">Mezunlar</h1>
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
                <button
                    type="button"
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700"
                    @click="creating = true"
                >
                    Mezun Ekle
                </button>
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
                                    :photo-url="graduate.photo_path ? `/storage/${graduate.photo_path}` : null"
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
                                <div class="flex justify-start gap-2">
                                    <button
                                        type="button"
                                        title="Düzenle"
                                        aria-label="Düzenle"
                                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                        @click="openEdit(graduate)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                    </button>
                                    <button
                                        type="button"
                                        title="Sil"
                                        aria-label="Sil"
                                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-red-600 shadow-sm hover:bg-red-50 hover:text-red-700 focus:border-red-500 focus:ring-red-500"
                                        @click="deleting = graduate"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
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

        <div v-if="creating" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitCreate">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Yeni Mezun Ekle</h2>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                title="Kaydet"
                                aria-label="Kaydet"
                                :disabled="createForm.processing"
                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            </button>
                            <button
                                type="button"
                                title="Vazgeç"
                                aria-label="Vazgeç"
                                class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                @click="creating = false"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                <div>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            title="Fotoğraf seç"
                            aria-label="Fotoğraf seç"
                            class="relative block h-[212px] w-[182px] shrink-0 overflow-hidden rounded-md border border-gray-300 bg-gray-50 hover:ring-2 hover:ring-indigo-500"
                            @click="photoInput?.click()"
                        >
                            <StudentAvatar
                                :photo-url="createPhotoSrc"
                                :full-name="createDisplayName"
                                img-class="h-full w-full object-cover"
                                placeholder-class="h-full w-full"
                                circle-class="w-10 text-sm"
                            />
                        </button>
                        <input
                            id="graduate-photo"
                            ref="photoInput"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            class="hidden"
                            @change="(e) => (createForm.photo = (e.target as HTMLInputElement).files?.[0] ?? null)"
                        />
                        <div class="min-w-0 flex-1 space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="graduate-number" class="block text-sm font-medium text-gray-700">Mezuniyet No</label>
                                    <input
                                        id="graduate-number"
                                        v-model="createForm.graduation_number"
                                        type="text"
                                        required
                                        maxlength="20"
                                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p v-if="createForm.errors.graduation_number" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.graduation_number }}
                                    </p>
                                </div>
                                <div>
                                    <label for="graduate-year" class="block text-sm font-medium text-gray-700">Mezuniyet Yılı</label>
                                    <input
                                        id="graduate-year"
                                        v-model.number="createForm.graduation_year"
                                        type="number"
                                        required
                                        min="1900"
                                        max="2100"
                                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p v-if="createForm.errors.graduation_year" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.graduation_year }}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <label for="graduate-first" class="block text-sm font-medium text-gray-700">Ad</label>
                                <input
                                    id="graduate-first"
                                    v-model="createForm.first_name"
                                    type="text"
                                    required
                                    maxlength="50"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p v-if="createForm.errors.first_name" class="mt-1 text-sm text-red-600">
                                    {{ createForm.errors.first_name }}
                                </p>
                            </div>
                            <div>
                                <label for="graduate-last" class="block text-sm font-medium text-gray-700">Soyad</label>
                                <input
                                    id="graduate-last"
                                    v-model="createForm.last_name"
                                    type="text"
                                    required
                                    maxlength="50"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p v-if="createForm.errors.last_name" class="mt-1 text-sm text-red-600">
                                    {{ createForm.errors.last_name }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-if="createForm.errors.photo" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.photo }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="graduate-phone" class="block text-sm font-medium text-gray-700">Telefon</label>
                        <input
                            id="graduate-phone"
                            v-model="createForm.phone"
                            type="text"
                            maxlength="30"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label for="graduate-email" class="block text-sm font-medium text-gray-700">E-posta</label>
                        <input
                            id="graduate-email"
                            v-model="createForm.email"
                            type="email"
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between">
                        <span class="block text-sm font-medium text-gray-700">Eğitim</span>
                        <button
                            type="button"
                            class="inline-flex h-7 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-3 text-xs text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                            @click="addEducationRow(createForm)"
                        >
                            + Satır Ekle
                        </button>
                    </div>
                    <div
                        v-for="(row, i) in createForm.educations"
                        :key="i"
                        class="mt-2 space-y-3 rounded-md border border-gray-200 p-3"
                    >
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label :for="`graduate-ecity-${i}`" class="block text-sm font-medium text-gray-700">Şehir</label>
                                <input
                                    :id="`graduate-ecity-${i}`"
                                    v-model="row.city"
                                    type="text"
                                    maxlength="100"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label :for="`graduate-einst-${i}`" class="block text-sm font-medium text-gray-700">Üniversite / Okul</label>
                                <input
                                    :id="`graduate-einst-${i}`"
                                    v-model="row.institution_name"
                                    type="text"
                                    required
                                    maxlength="100"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label :for="`graduate-efac-${i}`" class="block text-sm font-medium text-gray-700">Fakülte</label>
                                <input
                                    :id="`graduate-efac-${i}`"
                                    v-model="row.faculty"
                                    type="text"
                                    maxlength="100"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label :for="`graduate-edep-${i}`" class="block text-sm font-medium text-gray-700">Bölüm</label>
                                <input
                                    :id="`graduate-edep-${i}`"
                                    v-model="row.department"
                                    type="text"
                                    maxlength="100"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button
                                type="button"
                                class="text-xs text-red-600 hover:underline"
                                @click="createForm.educations.splice(i, 1)"
                            >
                                Satırı sil
                            </button>
                        </div>
                    </div>
                    <p v-if="createForm.errors.educations" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.educations }}
                    </p>
                </div>
                <div>
                    <label for="graduate-city" class="block text-sm font-medium text-gray-700">Şehir</label>
                    <input
                        id="graduate-city"
                        v-model="createForm.job_city"
                        type="text"
                        maxlength="100"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="graduate-company" class="block text-sm font-medium text-gray-700">İşyeri</label>
                    <textarea
                        id="graduate-company"
                        v-model="createForm.company"
                        rows="2"
                        maxlength="100"
                        class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                </div>
                </form>
            </div>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Mezunu Düzenle</h2>
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
                    <div class="flex gap-3">
                        <button
                            type="button"
                            title="Fotoğraf seç"
                            aria-label="Fotoğraf seç"
                            class="relative block h-[212px] w-[182px] shrink-0 overflow-hidden rounded-md border border-gray-300 bg-gray-50 hover:ring-2 hover:ring-indigo-500"
                            @click="editPhotoInput?.click()"
                        >
                            <StudentAvatar
                                :photo-url="editPhotoSrc"
                                :full-name="editDisplayName"
                                img-class="h-full w-full object-cover"
                                placeholder-class="h-full w-full"
                                circle-class="w-10 text-sm"
                            />
                        </button>
                        <input
                            id="edit-graduate-photo"
                            ref="editPhotoInput"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            class="hidden"
                            @change="(e) => (editForm.photo = (e.target as HTMLInputElement).files?.[0] ?? null)"
                        />
                        <div class="min-w-0 flex-1 space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="edit-graduate-number" class="block text-sm font-medium text-gray-700">Mezuniyet No</label>
                                    <input
                                        id="edit-graduate-number"
                                        v-model="editForm.graduation_number"
                                        type="text"
                                        required
                                        maxlength="20"
                                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p v-if="editForm.errors.graduation_number" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.graduation_number }}
                                    </p>
                                </div>
                                <div>
                                    <label for="edit-graduate-year" class="block text-sm font-medium text-gray-700">Mezuniyet Yılı</label>
                                    <input
                                        id="edit-graduate-year"
                                        v-model.number="editForm.graduation_year"
                                        type="number"
                                        required
                                        min="1900"
                                        max="2100"
                                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label for="edit-graduate-first" class="block text-sm font-medium text-gray-700">Ad</label>
                                <input
                                    id="edit-graduate-first"
                                    v-model="editForm.first_name"
                                    type="text"
                                    required
                                    maxlength="50"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p v-if="editForm.errors.first_name" class="mt-1 text-sm text-red-600">
                                    {{ editForm.errors.first_name }}
                                </p>
                            </div>
                            <div>
                                <label for="edit-graduate-last" class="block text-sm font-medium text-gray-700">Soyad</label>
                                <input
                                    id="edit-graduate-last"
                                    v-model="editForm.last_name"
                                    type="text"
                                    required
                                    maxlength="50"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p v-if="editForm.errors.last_name" class="mt-1 text-sm text-red-600">
                                    {{ editForm.errors.last_name }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-if="editForm.errors.photo" class="mt-1 text-sm text-red-600">
                        {{ editForm.errors.photo }}
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="edit-graduate-phone" class="block text-sm font-medium text-gray-700">Telefon</label>
                            <input
                                id="edit-graduate-phone"
                                v-model="editForm.phone"
                                type="text"
                                maxlength="30"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="edit-graduate-email" class="block text-sm font-medium text-gray-700">E-posta</label>
                            <input
                                id="edit-graduate-email"
                                v-model="editForm.email"
                                type="email"
                                maxlength="100"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="block text-sm font-medium text-gray-700">Eğitim</span>
                            <button
                                type="button"
                                class="inline-flex h-7 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-3 text-xs text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                                @click="addEducationRow(editForm)"
                            >
                                + Satır Ekle
                            </button>
                        </div>
                        <div
                            v-for="(row, i) in editForm.educations"
                            :key="row.id ?? `yeni-${i}`"
                            class="mt-2 space-y-3 rounded-md border border-gray-200 p-3"
                        >
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label :for="`edit-ecity-${i}`" class="block text-sm font-medium text-gray-700">Şehir</label>
                                    <input
                                        :id="`edit-ecity-${i}`"
                                        v-model="row.city"
                                        type="text"
                                        maxlength="100"
                                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label :for="`edit-einst-${i}`" class="block text-sm font-medium text-gray-700">Üniversite / Okul</label>
                                    <input
                                        :id="`edit-einst-${i}`"
                                        v-model="row.institution_name"
                                        type="text"
                                        required
                                        maxlength="100"
                                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label :for="`edit-efac-${i}`" class="block text-sm font-medium text-gray-700">Fakülte</label>
                                    <input
                                        :id="`edit-efac-${i}`"
                                        v-model="row.faculty"
                                        type="text"
                                        maxlength="100"
                                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label :for="`edit-edep-${i}`" class="block text-sm font-medium text-gray-700">Bölüm</label>
                                    <input
                                        :id="`edit-edep-${i}`"
                                        v-model="row.department"
                                        type="text"
                                        maxlength="100"
                                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="editForm.educations.splice(i, 1)"
                                >
                                    Satırı sil
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="edit-graduate-city" class="block text-sm font-medium text-gray-700">Şehir</label>
                            <input
                                id="edit-graduate-city"
                                v-model="editForm.job_city"
                                type="text"
                                maxlength="100"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="edit-graduate-company" class="block text-sm font-medium text-gray-700">İşyeri</label>
                            <textarea
                                id="edit-graduate-company"
                                v-model="editForm.company"
                                rows="2"
                                maxlength="100"
                                class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Mezunu Sil</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ deleting.year }} / {{ deleting.number }} — {{ deleting.full_name }} silinsin mi? Bu işlem geri alınamaz.
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
