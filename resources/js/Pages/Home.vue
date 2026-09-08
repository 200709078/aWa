<script setup lang="ts">
import AppLayout from '../Layouts/AppLayout.vue';
</script>

<template>
        <AppLayout title="Kelebek Oturma Planı">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-8 text-center shadow-sm">
            <img :src="'/favicon.png'" alt="Kelebek logosu" class="mx-auto h-20 w-20 object-contain" />
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Kelebek Oturma Planı</h1>
            <p class="mt-3 text-gray-600">Yönetim Paneli</p>
            <p class="mt-6 text-right text-[16px] font-bold tracking-wide text-black">
                made by <span class="font-bold text-black">m</span><span class="font-bold text-blue-900">ADEM</span><span class="font-bold text-black">atik</span>
            </p>
        </div>
    </AppLayout>
</template>
