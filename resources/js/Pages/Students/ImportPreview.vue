<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';

interface Column {
    index: number;
    letter: string;
    header: string;
}

interface ImportGuardianPreview {
    relation: string;
    name: string;
    phone: string;
    is_primary: boolean;
    action: 'add' | 'update' | null;
}

interface PreviewRow {
    line: number;
    school_number: string;
    full_name: string;
    branch: string;
    gender: string;
    student_phone: string;
    student_email: string;
    guardian_selector: string;
    mother_name: string;
    mother_phone: string;
    father_name: string;
    father_phone: string;
    other_guardian_name: string;
    other_guardian_phone: string;
    address: string;
    guardians: ImportGuardianPreview[];
    action: 'add' | 'update' | 'skip' | 'error';
    message?: string;
}

interface Summary {
    toplam: number;
    eklenecek: number;
    guncellenecek: number;
    eklenecek_veli: number;
    guncellenecek_veli: number;
    atlandi: number;
    hatali: number;
}

interface Mapping {
    school_number: number;
    full_name: number;
    branch: number;
    gender: number | null;
    student_phone: number | null;
    student_email: number | null;
    guardian_selector: number | null;
    mother_name: number | null;
    mother_phone: number | null;
    father_name: number | null;
    father_phone: number | null;
    other_guardian_name: number | null;
    other_guardian_phone: number | null;
    address: number | null;
}

const props = defineProps<{
    year: { id: number; name: string };
    storedPath: string;
    columns: Column[];
    mapping: Mapping;
    rows: PreviewRow[];
    summary: Summary;
}>();

const form = useForm({
    academic_year_id: props.year.id,
    stored_path: props.storedPath,
    mapping: { ...props.mapping },
});

function refreshPreview() {
    form.post('/students/import/preview');
}

function confirmImport() {
    form.post('/students/import/confirm');
}

const extraErrors = computed(() => form.errors as Record<string, string | undefined>);

const columnOptions = computed(() => props.columns.map((col) => ({ value: col.index, label: `${col.letter}: ${col.header || '(boş başlık)'}` })));

const optionalFields: { key: Exclude<keyof Mapping, 'school_number' | 'full_name' | 'branch'>; label: string; id: string }[] = [
    { key: 'gender', label: 'Cinsiyet Sütunu', id: 'map-gender' },
    { key: 'student_phone', label: 'Öğrenci Telefonu Sütunu', id: 'map-student-phone' },
    { key: 'student_email', label: 'Öğrenci E-postası Sütunu', id: 'map-student-email' },
    { key: 'guardian_selector', label: 'Velisi Kim Sütunu', id: 'map-guardian-selector' },
    { key: 'mother_name', label: 'Anne Ad Soyad Sütunu', id: 'map-mother-name' },
    { key: 'mother_phone', label: 'Anne Telefonu Sütunu', id: 'map-mother-phone' },
    { key: 'father_name', label: 'Baba Ad Soyad Sütunu', id: 'map-father-name' },
    { key: 'father_phone', label: 'Baba Telefonu Sütunu', id: 'map-father-phone' },
    { key: 'other_guardian_name', label: 'Diğer Veli Adı Sütunu', id: 'map-other-guardian-name' },
    { key: 'other_guardian_phone', label: 'Diğer Veli Telefonu Sütunu', id: 'map-other-guardian-phone' },
    { key: 'address', label: 'Adres Sütunu', id: 'map-address' },
];

const actionLabel: Record<PreviewRow['action'], string> = {
    add: 'Eklenecek',
    update: 'Güncellenecek',
    skip: 'Atlanacak',
    error: 'Hatalı',
};

const actionClass: Record<PreviewRow['action'], string> = {
    add: 'bg-green-100 text-green-800',
    update: 'bg-blue-100 text-blue-800',
    skip: 'bg-gray-100 text-gray-600',
    error: 'bg-red-100 text-red-800',
};
</script>

<template>
    <AppLayout title="Aktarma Önizleme">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Aktarma Önizleme</h1>
            <p class="mt-1 text-sm text-gray-600">Akademik Yıl: {{ year.name }}</p>

            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.toplam }}</div>
                    <div class="text-xs text-gray-500">Toplam Satır</div>
                </div>
                <div class="rounded-md bg-green-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold text-green-800">{{ summary.eklenecek }}</div>
                    <div class="text-xs text-gray-500">Eklenecek</div>
                </div>
                <div class="rounded-md bg-blue-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold text-blue-800">{{ summary.guncellenecek }}</div>
                    <div class="text-xs text-gray-500">Güncellenecek</div>
                </div>
                <div class="rounded-md bg-green-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold text-green-800">{{ summary.eklenecek_veli }}</div>
                    <div class="text-xs text-gray-500">Eklenecek Veli</div>
                </div>
                <div class="rounded-md bg-blue-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold text-blue-800">{{ summary.guncellenecek_veli }}</div>
                    <div class="text-xs text-gray-500">Güncellenecek Veli</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.atlandi }}</div>
                    <div class="text-xs text-gray-500">Atlanacak</div>
                </div>
                <div class="rounded-md bg-red-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold text-red-800">{{ summary.hatali }}</div>
                    <div class="text-xs text-gray-500">Hatalı</div>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div>
                    <label for="map-number" class="block text-sm font-medium text-gray-700">Okul Numarası Sütunu</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="map-number"
                            v-model="form.mapping.school_number"
                            :options="columns.map((col) => ({ value: col.index, label: `${col.letter}: ${col.header || '(boş başlık)'}` }))"
                            aria-label="Okul Numarası Sütunu"
                        />
                    </div>
                </div>
                <div>
                    <label for="map-name" class="block text-sm font-medium text-gray-700">Ad Soyad Sütunu</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="map-name"
                            v-model="form.mapping.full_name"
                            :options="columns.map((col) => ({ value: col.index, label: `${col.letter}: ${col.header || '(boş başlık)'}` }))"
                            aria-label="Ad Soyad Sütunu"
                        />
                    </div>
                </div>
                <div>
                    <label for="map-branch" class="block text-sm font-medium text-gray-700">Şube Sütunu</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="map-branch"
                            v-model="form.mapping.branch"
                            :options="columns.map((col) => ({ value: col.index, label: `${col.letter}: ${col.header || '(boş başlık)'}` }))"
                            aria-label="Şube Sütunu"
                        />
                    </div>
                </div>
                <div v-for="field in optionalFields" :key="field.key">
                    <label :for="field.id" class="block text-sm font-medium text-gray-700">{{ field.label }} (opsiyonel)</label>
                    <div class="mt-1">
                        <DropdownSelect
                            :id="field.id"
                            v-model="form.mapping[field.key]"
                            :options="[{ value: null, label: 'Eşleştirme' }, ...columnOptions]"
                            :aria-label="field.label"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    :disabled="form.processing"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                    @click="refreshPreview"
                >
                    Önizlemeyi güncelle
                </button>
                <button
                    type="button"
                    :disabled="form.processing || summary.eklenecek + summary.guncellenecek === 0"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    @click="confirmImport"
                >
                    Aktarımı onayla
                </button>
            </div>
            <p v-if="extraErrors.file" class="mt-2 text-sm text-red-600">{{ extraErrors.file }}</p>
        </div>

        <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Satır</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Okul No</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Şube</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Cinsiyet</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Öğr. Tel</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Velisi Kim</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Anne</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Baba</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Diğer Veli</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="row in rows" :key="row.line">
                        <td class="whitespace-nowrap px-4 py-2 text-gray-500">{{ row.line }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-900">{{ row.school_number }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-900">{{ row.full_name }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ row.branch }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ row.gender }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ row.student_phone }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ row.guardian_selector }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-900">
                            {{ row.mother_name }}
                            <span v-if="row.mother_name" class="ml-1 text-xs text-gray-500">{{ row.mother_phone }}</span>
                            <span v-if="row.guardians.some((g) => g.relation === 'anne' && g.is_primary)" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Birincil</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-900">
                            {{ row.father_name }}
                            <span v-if="row.father_name" class="ml-1 text-xs text-gray-500">{{ row.father_phone }}</span>
                            <span v-if="row.guardians.some((g) => g.relation === 'baba' && g.is_primary)" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Birincil</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-900">
                            {{ row.other_guardian_name }}
                            <span v-if="row.other_guardian_name" class="ml-1 text-xs text-gray-500">{{ row.other_guardian_phone }}</span>
                            <span v-if="row.guardians.some((g) => g.relation === 'veli' && g.is_primary)" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Birincil</span>
                        </td>
                        <td class="px-4 py-2">
                            <span
                                class="rounded-full px-2 py-1 text-xs font-semibold"
                                :class="actionClass[row.action]"
                            >
                                {{ actionLabel[row.action] }}
                            </span>
                            <p v-if="row.message" class="mt-1 text-xs text-red-600">{{ row.message }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
