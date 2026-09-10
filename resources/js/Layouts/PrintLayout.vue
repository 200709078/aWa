<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ title: string; backHref: string }>();

const today = new Date().toLocaleDateString('tr-TR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

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

    <div class="bg-white p-6 print:px-[10mm] print:pb-[4mm] print:pt-[8mm]">
        <div class="mb-2 hidden items-center justify-between text-xs text-gray-600 print:flex">
            <span>{{ today }}</span>
            <span
                >made by <span class="font-bold text-black">m</span
                ><span class="font-bold text-blue-900">ADEM</span><span class="font-bold text-black">atik</span></span
            >
        </div>
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
                class="inline-flex items-center justify-center gap-0 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                @click="printPage"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mr-1.5 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 14h12v8H6z" /></svg>
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

<style>
@page {
    size: A4;
    margin: 0;
}
</style>
