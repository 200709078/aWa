<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ title: string; backHref: string }>();

function printPage() {
    window.print();
}

function closePage() {
    window.close();
    setTimeout(() => {
        if (!window.closed) window.location.href = props.backHref;
    }, 300);
}
</script>

<template>
    <Head :title="title" />

    <div class="bg-white p-6 print:p-0">
        <div class="no-print mb-4 flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                @click="closePage"
            >
                Kapat
            </button>
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
