<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

interface RowMsg {
    line: number;
    number: string;
    message: string;
}

const props = defineProps<{
    year: { id: number; name: string };
    summary: Record<string, number>;
    errors: RowMsg[];
    warnings: RowMsg[];
    unmapped: string[];
}>();
</script>

<template>
    <AppLayout title="İçe Aktarma Sonucu">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-2xl font-bold text-gray-900">İçe Aktarma Sonucu — {{ year.name }}</h1>
                <Link
                    href="/bilgi-formlari"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                >
                    Listeye Dön
                </Link>
            </div>
            <dl class="mt-4 grid max-w-lg grid-cols-2 gap-3 text-sm">
                <div class="rounded-md bg-gray-50 px-4 py-2"><dt class="text-gray-500">Toplam satır</dt><dd class="text-lg font-bold text-gray-900">{{ summary.toplam ?? 0 }}</dd></div>
                <div class="rounded-md bg-gray-50 px-4 py-2"><dt class="text-gray-500">İşlendi</dt><dd class="text-lg font-bold text-gray-900">{{ summary.islendi ?? 0 }}</dd></div>
                <div class="rounded-md bg-green-50 px-4 py-2"><dt class="text-green-700">Form oluşturuldu</dt><dd class="text-lg font-bold text-green-800">{{ summary.form_olustu ?? 0 }}</dd></div>
                <div class="rounded-md bg-green-50 px-4 py-2"><dt class="text-green-700">Form güncellendi</dt><dd class="text-lg font-bold text-green-800">{{ summary.form_guncellendi ?? 0 }}</dd></div>
                <div class="rounded-md bg-red-50 px-4 py-2"><dt class="text-red-700">Atlandı</dt><dd class="text-lg font-bold text-red-800">{{ summary.atlandi ?? 0 }}</dd></div>
            </dl>

            <div v-if="warnings.length > 0" class="mt-6">
                <h2 class="font-semibold text-amber-800">Uyarılar (işlenmedi, kontrol edin)</h2>
                <ul class="mt-2 space-y-1 text-sm text-gray-700">
                    <li v-for="(w, i) in warnings" :key="i" class="rounded-md bg-amber-50 px-3 py-2">
                        Satır {{ w.line }} · No {{ w.number }} — {{ w.message }}
                    </li>
                </ul>
            </div>

            <div v-if="errors.length > 0" class="mt-6">
                <h2 class="font-semibold text-red-800">Hatalar</h2>
                <ul class="mt-2 space-y-1 text-sm text-gray-700">
                    <li v-for="(e, i) in errors" :key="i" class="rounded-md bg-red-50 px-3 py-2">
                        Satır {{ e.line }} · No {{ e.number }} — {{ e.message }}
                    </li>
                </ul>
            </div>

            <div v-if="unmapped.length > 0" class="mt-6">
                <h2 class="font-semibold text-gray-800">Eşleşmeyen sütunlar (yok sayıldı)</h2>
                <p class="mt-1 text-sm text-gray-600">{{ unmapped.join(' · ') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
