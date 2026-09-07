<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => {
    const props = usePage().props as unknown as { auth: { user: { name: string } | null } };
    return props.auth.user;
});

function logout() {
    router.post('/logout');
}
</script>

<template>
    <main class="min-h-screen bg-gray-50">
        <header class="flex items-center justify-between bg-white px-6 py-3 shadow-sm">
            <span class="font-semibold text-gray-900">Kelebek</span>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-600">{{ user?.name }}</span>
                <button
                    type="button"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                    @click="logout"
                >
                    Çıkış
                </button>
            </div>
        </header>

        <div class="flex items-center justify-center px-4 py-16">
            <div class="text-center">
                <img :src="'/favicon.png'" alt="Kelebek logosu" class="mx-auto h-20 w-20 object-contain" />
                <h1 class="mt-4 text-4xl font-bold tracking-tight text-gray-900">Kelebek</h1>
                <p class="mt-3 text-lg text-gray-600">Exam Seating Plan Management System</p>
                <p class="mt-6 text-sm text-gray-500">System setup successful.</p>
            </div>
        </div>
    </main>
</template>
