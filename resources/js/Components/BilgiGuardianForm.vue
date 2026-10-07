<script setup lang="ts">
import type { GuardianBlock } from '../types/bilgiFormu';

defineProps<{
    model: GuardianBlock;
    idPrefix: string;
    title: string;
}>();
</script>

<template>
    <div class="rounded-md border border-gray-200 p-3">
        <div class="flex items-center justify-between gap-2">
            <h3 class="font-semibold text-gray-800">{{ title }}</h3>
            <div class="flex shrink-0 items-center gap-2">
                <slot name="header-extra" />
            </div>
        </div>
        <div class="mt-2 grid grid-cols-2 gap-3">
            <div>
                <span class="block text-sm font-medium text-gray-700">Ad Soyad</span>
                <input
                    v-model="model.name"
                    type="text"
                    maxlength="100"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700">Telefon</span>
                <input
                    v-model="model.phone"
                    type="text"
                    maxlength="30"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <div>
                <span class="block text-sm font-medium text-gray-700">Doğum Yeri</span>
                <input
                    v-model="model.birth_place"
                    type="text"
                    maxlength="100"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700">Doğum Tarihi</span>
                <input
                    v-model="model.birth_date"
                    type="date"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <div>
                <span class="block text-sm font-medium text-gray-700">Eğitim Durumu</span>
                <input
                    v-model="model.education"
                    type="text"
                    maxlength="50"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700">Meslek</span>
                <input
                    v-model="model.occupation"
                    type="text"
                    maxlength="100"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <div>
                <span class="block text-sm font-medium text-gray-700">Öz mü?</span>
                <select
                    v-model="model.biological"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="null">—</option>
                    <option :value="true">Evet</option>
                    <option :value="false">Hayır</option>
                </select>
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700">Sağ mı?</span>
                <select
                    v-model="model.alive"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="null">—</option>
                    <option :value="true">Evet</option>
                    <option :value="false">Hayır</option>
                </select>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <div>
                <span class="block text-sm font-medium text-gray-700">Engel</span>
                <input
                    v-model="model.disability"
                    type="text"
                    maxlength="255"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700">Sürekli Hastalık</span>
                <input
                    v-model="model.illness"
                    type="text"
                    maxlength="255"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
        </div>
    </div>
</template>
