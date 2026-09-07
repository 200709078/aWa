<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Year {
    id: number;
    name: string;
    is_active: boolean;
    branches_count: number;
}

defineProps<{ years: Year[] }>();

const createForm = useForm({ name: '' });

function submitCreate() {
    createForm.post('/academic-years', { onSuccess: () => createForm.reset() });
}

const editing = ref<Year | null>(null);
const editForm = useForm({ name: '' });

function openEdit(year: Year) {
    editing.value = year;
    editForm.name = year.name;
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.put(`/academic-years/${editing.value.id}`, {
        onSuccess: () => (editing.value = null),
    });
}

function toggle(url: string) {
    router.post(url);
}
</script>

<template>
    <AppLayout title="Akademik Yıllar">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Akademik Yıllar</h1>

            <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submitCreate">
                <div class="flex-1">
                    <label for="year-name" class="block text-sm font-medium text-gray-700">Yeni akademik yıl (örn. 2026-2027)</label>
                    <input
                        id="year-name"
                        v-model="createForm.name"
                        type="text"
                        required
                        maxlength="20"
                        placeholder="2026-2027"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="createForm.processing"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    Ekle
                </button>
            </form>
        </div>

        <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Yıl</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Şube</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="year in years" :key="year.id">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ year.name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ year.branches_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span
                                v-if="year.is_active"
                                class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"
                            >
                                Aktif
                            </span>
                            <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                Pasif
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="openEdit(year)"
                                >
                                    Düzenle
                                </button>
                                <button
                                    v-if="!year.is_active"
                                    type="button"
                                    class="rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-700"
                                    @click="toggle(`/academic-years/${year.id}/activate`)"
                                >
                                    Aktif Yap
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="toggle(`/academic-years/${year.id}/deactivate`)"
                                >
                                    Pasife Al
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="years.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Henüz akademik yıl eklenmedi.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="editing" class="fixed inset-0 z-10 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Akademik yılı düzenle</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <label for="edit-year-name" class="block text-sm font-medium text-gray-700">Yıl adı</label>
                        <input
                            id="edit-year-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            maxlength="20"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">{{ editForm.errors.name }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            @click="editing = null"
                        >
                            Vazgeç
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
