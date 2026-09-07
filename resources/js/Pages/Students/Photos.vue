<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Year {
    id: number;
    name: string;
    is_active: boolean;
}

defineProps<{
    years: Year[];
    activeYearId: number | null;
}>();

const form = useForm({
    academic_year_id: null as number | null,
    photos: [] as File[],
    zip_file: null as File | null,
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            photos: data.photos.length > 0 ? data.photos : undefined,
        }))
        .post('/students/photos');
}
</script>

<template>
    <AppLayout title="Toplu Fotoğraf Yükle">
        <div class="max-w-xl rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Toplu Fotoğraf Yükle</h1>
            <p class="mt-2 text-sm text-gray-600">
                Dosya adı okul numarası olmalı (örn. 145.jpg). Büyük fotoğraflar otomatik küçültülür.
                Çoklu dosya seçimi veya ZIP yükleyebilirsiniz.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="photo-year" class="block text-sm font-medium text-gray-700">Akademik yıl</label>
                    <select
                        id="photo-year"
                        v-model="form.academic_year_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option :value="null">Seçin</option>
                        <option v-for="year in years" :key="year.id" :value="year.id">
                            {{ year.name }}{{ year.is_active ? ' (aktif)' : '' }}
                        </option>
                    </select>
                    <p v-if="form.errors.academic_year_id" class="mt-1 text-sm text-red-600">
                        {{ form.errors.academic_year_id }}
                    </p>
                </div>

                <div>
                    <label for="photo-files" class="block text-sm font-medium text-gray-700">
                        Fotoğraflar (jpg, jpeg, png, webp)
                    </label>
                    <input
                        id="photo-files"
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp"
                        class="mt-1 block w-full text-sm text-gray-600"
                        @change="(e) => (form.photos = Array.from((e.target as HTMLInputElement).files ?? []))"
                    />
                    <p v-if="form.errors.photos" class="mt-1 text-sm text-red-600">{{ form.errors.photos }}</p>
                </div>

                <div>
                    <label for="photo-zip" class="block text-sm font-medium text-gray-700">veya ZIP dosyası</label>
                    <input
                        id="photo-zip"
                        type="file"
                        accept=".zip"
                        class="mt-1 block w-full text-sm text-gray-600"
                        @change="(e) => (form.zip_file = (e.target as HTMLInputElement).files?.[0] ?? null)"
                    />
                    <p v-if="form.errors.zip_file" class="mt-1 text-sm text-red-600">{{ form.errors.zip_file }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    Yükle ve eşleştir
                </button>
            </form>
        </div>
    </AppLayout>
</template>
