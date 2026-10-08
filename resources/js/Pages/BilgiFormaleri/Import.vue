<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps<{
    years: { id: number; name: string; is_active: boolean }[];
    activeYearId: number | null;
}>();

const form = useForm({
    academic_year_id: props.activeYearId,
    file: null as File | null,
    overwrite: false,
});

const fileInput = ref<HTMLInputElement | null>(null);

function openFileDialog() {
    fileInput.value?.click();
}

function onFile(e: Event) {
    form.file = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function submit() {
    form.post('/bilgi-formlari/ice-aktar');
}
</script>

<template>
    <AppLayout title="Bilgi Formu İçe Aktar">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-2xl font-bold text-gray-900">Bilgi Formu İçe Aktar</h1>
                <Link
                    href="/bilgi-formlari"
                    title="Geri dön"
                    aria-label="Geri dön"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </Link>
            </div>
            <p class="mt-2 text-sm text-gray-600">
                Google Form çıktısı (.xlsx/.xls) yükleyin. Satırlar <strong>okul numarası</strong> ile eşleştirilir;
                sınıf, numara ve ad-soyad asla değiştirilmez. Sınıfı uyuşmayan satır işlenmez, kontrol listesine düşer.
                Dilerseniz boş şablonu indirip doldurun:
                <a href="/bilgi-formlari/ice-aktar/sablon" class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">Excel şablonunu indir</a>.
            </p>
            <form class="mt-4 max-w-lg space-y-4" @submit.prevent="submit">
                <div>
                    <span class="block text-sm font-medium text-gray-700">Akademik Yıl (numara eşleşmesi bu yıla göre yapılır)</span>
                    <div class="mt-1">
                        <DropdownSelect
                            id="bf-import-year"
                            v-model="form.academic_year_id"
                            :options="years.map((y) => ({ value: y.id, label: y.name }))"
                            aria-label="Akademik Yıl"
                        />
                    </div>
                    <p v-if="form.errors.academic_year_id" class="mt-1 text-sm text-red-600">{{ form.errors.academic_year_id }}</p>
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-700">Excel Dosyası</span>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".xlsx,.xls"
                        class="hidden"
                        @change="onFile"
                    />
                    <button
                        type="button"
                        class="mt-1 flex h-9 w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-gray-50 px-3 text-sm text-gray-700 shadow-sm hover:bg-indigo-50 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                        @click="openFileDialog"
                    >
                        <span class="truncate">{{ form.file ? form.file.name : 'Dosya seçin (.xlsx, .xls)' }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    </button>
                    <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">{{ form.errors.file }}</p>
                </div>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input v-model="form.overwrite" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300" />
                    <span>
                        <strong>Dolu alanların üzerine yaz.</strong>
                        Kapalıyken dolu bilgi korunur, yalnız boş alanlar doldurulur. Açıkken dosyadaki cevaplar yazar.
                    </span>
                </label>
                <div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        İçe Aktar
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
