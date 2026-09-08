<script setup lang="ts">
import AppLayout from '../Layouts/AppLayout.vue';
</script>

<template>
        <AppLayout title="Kelebek Oturma Planı">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-8 text-center shadow-sm">
            <img :src="'/favicon.png'" alt="Kelebek logosu" class="mx-auto h-20 w-20 object-contain" />
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Kelebek Oturma Planı</h1>
            <p class="mt-3 text-gray-600">Yönetim Paneli</p>
        </div>
    </AppLayout>
</template>
