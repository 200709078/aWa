<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

interface ImportError {
    line: number;
    school_number: string;
    full_name: string;
    branch: string;
    message?: string;
}

defineProps<{
    year: { id: number; name: string } | null;
    summary: {
        toplam: number;
        eklendi: number;
        guncellendi: number;
        atlandi: number;
        hatali: number;
    };
    errors: ImportError[];
}>();
</script>

<template>
    <AppLayout title="Aktarma Sonucu">
        <div class="max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Aktarma Sonucu</h1>
            <p v-if="year" class="mt-1 text-sm text-gray-600">Akademik yıl: {{ year.name }}</p>

            <dl class="mt-4 divide-y divide-gray-200">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Toplam satır</dt>
                    <dd class="font-semibold">{{ summary.toplam }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Eklendi</dt>
                    <dd class="font-semibold text-green-700">{{ summary.eklendi }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Güncellendi</dt>
                    <dd class="font-semibold text-blue-700">{{ summary.guncellendi }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Atlandı</dt>
                    <dd class="font-semibold">{{ summary.atlandi }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Hatalı</dt>
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

        <div v-if="errors.length > 0" class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Satır</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Veri</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Hata</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="row in errors" :key="row.line">
                        <td class="whitespace-nowrap px-4 py-2 text-gray-500">{{ row.line }}</td>
                        <td class="px-4 py-2 text-gray-900">{{ row.school_number }} / {{ row.full_name }} / {{ row.branch }}</td>
                        <td class="px-4 py-2 text-sm text-red-600">{{ row.message }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
