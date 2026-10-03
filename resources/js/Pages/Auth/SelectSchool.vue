<script setup lang="ts">
import { router } from '@inertiajs/vue3';

interface School {
    id: number;
    name: string;
    kurum_kodu: string;
}

defineProps<{
    userName: string;
    schools: School[];
}>();

function choose(id: number) {
    router.post('/select-school', { school_id: id });
}

function backToLogin() {
    router.post('/logout');
}
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-sm rounded-lg bg-white p-8 shadow">
            <img :src="'/favicon.png'" alt="aWa logosu" class="mx-auto h-16 w-16 object-contain" />
            <h1 class="mt-4 text-center text-2xl font-bold text-gray-900">Okul Seçin</h1>
            <p class="mt-1 text-center text-sm text-gray-500">{{ userName }} — devam etmek için okulunuzu seçin</p>

            <div class="mt-6 space-y-2">
                <button
                    v-for="school in schools"
                    :key="school.id"
                    type="button"
                    class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-gray-50 px-4 py-3 text-left text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                    @click="choose(school.id)"
                >
                    <span class="font-semibold">{{ school.name }}</span>
                    <span class="text-xs text-gray-500">{{ school.kurum_kodu }}</span>
                </button>
            </div>

            <button
                type="button"
                class="mt-4 w-full text-center text-sm text-gray-500 hover:text-gray-700"
                @click="backToLogin"
            >
                Farklı kullanıcı ile giriş yap
            </button>
        </div>
        <div
            class="pointer-events-none fixed bottom-2 left-3 z-50 select-none text-[20px] font-bold tracking-wide text-black"
        >
            made by <span class="font-bold text-black">m</span><span class="font-bold text-blue-900">ADEM</span><span class="font-bold text-black">atik</span>
        </div>
    </main>
</template>
