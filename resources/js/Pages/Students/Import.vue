<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';

interface Year {
    id: number;
    name: string;
    is_active: boolean;
}

const props = defineProps<{
    years: Year[];
    activeYearId: number | null;
}>();

const form = useForm({
    academic_year_id: props.activeYearId,
    file: null as File | null,
});

function submit() {
    form.post('/students/import/preview');
}
</script>

<template>
    <AppLayout title="Excel'den Öğrenci Aktar">
        <div class="max-w-xl rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Excel'den Öğrenci Aktar</h1>
            <p class="mt-2 text-sm text-gray-600">
                .xlsx veya .xls dosyası yükleyin. Sütunlar otomatik eşleştirilir, önizlemede düzeltip onaylarsınız.
                Dilerseniz boş şablonu indirip doldurun:
                <a href="/students/import/template" class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">Excel şablonunu indir</a>.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="import-year" class="block text-sm font-medium text-gray-700">Akademik Yıl</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="import-year"
                            v-model="form.academic_year_id"
                            :options="[{ value: null, label: 'Seçin' }, ...years.map((year) => ({ value: year.id, label: `${year.name}${year.is_active ? ' (aktif)' : ''}` }))]"
                            aria-label="Akademik Yıl"
                        />
                    </div>
                    <p v-if="form.errors.academic_year_id" class="mt-1 text-sm text-red-600">
                        {{ form.errors.academic_year_id }}
                    </p>
                </div>

                <div>
                    <label for="import-file" class="block text-sm font-medium text-gray-700">Excel Dosyası</label>
                    <input
                        id="import-file"
                        type="file"
                        accept=".xlsx,.xls"
                        class="mt-1 block w-full text-sm text-gray-600"
                        @change="(e) => (form.file = (e.target as HTMLInputElement).files?.[0] ?? null)"
                    />
                    <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">{{ form.errors.file }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    Önizlemeye devam et
                </button>
            </form>
        </div>
    </AppLayout>
</template>
