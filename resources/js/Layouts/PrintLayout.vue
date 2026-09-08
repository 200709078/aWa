<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ title: string; backHref: string }>();

function printPage() {
    window.print();
}
</script>

<template>
    <Head :title="title" />

    <div class="bg-white p-6 print:p-0">
        <div class="no-print mb-4 flex flex-wrap items-center gap-2">
            <Link
                :href="backHref"
                class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
                Geri
            </Link>
            <button
                type="button"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                @click="printPage"
            >
                Yazdır
            </button>
            <slot name="actions" />
        </div>
        <slot />
        <div
            class="pointer-events-none fixed bottom-2 right-3 z-50 select-none text-[20px] font-bold tracking-wide text-black print:hidden"
        >
            made by <span class="font-bold text-black">m</span><span class="font-bold text-blue-900">ADEM</span><span class="font-bold text-black">atik</span>
        </div>
    </div>
</template>
