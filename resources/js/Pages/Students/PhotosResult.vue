<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

interface FailedFile {
    filename: string;
    message: string;
}

interface BareStudent {
    school_number: string;
    full_name: string;
}

defineProps<{
    year: { id: number; name: string } | null;
    summary: {
        eslesen: number;
        eslesmeyen: number;
        fotografsiz: number;
        hatali: number;
    };
    unmatched: string[];
    failed: FailedFile[];
    withoutPhoto: BareStudent[];
    withoutPhotoTruncated: boolean;
}>();
</script>

<template>
    <AppLayout title="Fotoğraf Yükleme Sonucu">
        <div class="max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Fotoğraf Yükleme Sonucu</h1>
            <p v-if="year" class="mt-1 text-sm text-gray-600">Akademik yıl: {{ year.name }}</p>

            <dl class="mt-4 divide-y divide-gray-200">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Eşleşen fotoğraf</dt>
                    <dd class="font-semibold text-green-700">{{ summary.eslesen }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Eşleşmeyen dosya</dt>
                    <dd class="font-semibold text-yellow-700">{{ summary.eslesmeyen }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Fotoğrafı olmayan öğrenci</dt>
                    <dd class="font-semibold">{{ summary.fotografsiz }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Hatalı dosya</dt>
                    <dd class="font-semibold text-red-700">{{ summary.hatali }}</dd>
                </div>
            </dl>

            <Link
                href="/students"
                class="mt-4 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
            >
                Öğrenci listesine dön
            </Link>
        </div>

        <div v-if="unmatched.length > 0" class="mt-6 max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">Eşleşmeyen dosyalar ({{ unmatched.length }})</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                <li v-for="name in unmatched" :key="name">{{ name }}</li>
            </ul>
        </div>

        <div v-if="failed.length > 0" class="mt-6 max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">Hatalı dosyalar ({{ failed.length }})</h2>
            <ul class="mt-2 space-y-1 text-sm">
                <li v-for="file in failed" :key="file.filename" class="text-gray-600">
                    {{ file.filename }} — <span class="text-red-600">{{ file.message }}</span>
                </li>
            </ul>
        </div>

        <div v-if="withoutPhoto.length > 0" class="mt-6 max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">Fotoğrafı olmayan öğrenciler ({{ summary.fotografsiz }})</h2>
            <ul class="mt-2 space-y-1 text-sm text-gray-600">
                <li v-for="student in withoutPhoto" :key="student.school_number">
                    {{ student.school_number }} — {{ student.full_name }}
                </li>
            </ul>
            <p v-if="withoutPhotoTruncated" class="mt-2 text-sm text-gray-400">Liste ilk 100 kayıtla sınırlı.</p>
        </div>
    </AppLayout>
</template>
