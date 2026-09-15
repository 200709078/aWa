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

interface PreviewRow {
    line: number;
    school_number: string;
    full_name: string;
    branch: string;
    action: 'add' | 'update' | 'skip' | 'error';
    message?: string;
}

interface Summary {
    toplam: number;
    eklenecek: number;
    guncellenecek: number;
    atlandi: number;
    hatali: number;
}

const props = defineProps<{
    year: { id: number; name: string };
    storedPath: string;
    columns: Column[];
    mapping: { school_number: number; full_name: number; branch: number };
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
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="row in rows" :key="row.line">
                        <td class="whitespace-nowrap px-4 py-2 text-gray-500">{{ row.line }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-900">{{ row.school_number }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-900">{{ row.full_name }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ row.branch }}</td>
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
