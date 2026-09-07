<script setup lang="ts">
import AppLayout from '../Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout title="Panel">
        <div class="rounded-lg bg-white p-8 text-center shadow-sm">
            <img :src="'/favicon.png'" alt="Kelebek logosu" class="mx-auto h-20 w-20 object-contain" />
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Kelebek</h1>
            <p class="mt-3 text-gray-600">Sınav oturma planı yönetim paneli</p>
                <p class="mt-6 text-sm text-gray-500">Kurulum tamamlandı.</p>
        </div>
    </AppLayout>
</template>
