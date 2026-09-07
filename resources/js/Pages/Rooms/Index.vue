<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Room {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    seats_count: number;
    active_seats_count: number;
}

defineProps<{ rooms: Room[] }>();

const createForm = useForm({
    name: '',
    description: '',
    sort_order: 0 as number | null,
    is_active: true,
});

function submitCreate() {
    createForm.post('/rooms', {
        onSuccess: () => createForm.reset('name', 'description'),
    });
}

const editing = ref<Room | null>(null);
const editForm = useForm({
    name: '',
    description: '',
    sort_order: 0 as number | null,
    is_active: true,
});

function openEdit(room: Room) {
    editing.value = room;
    editForm.name = room.name;
    editForm.description = room.description ?? '';
    editForm.sort_order = room.sort_order;
    editForm.is_active = room.is_active;
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.put(`/rooms/${editing.value.id}`, {
        onSuccess: () => (editing.value = null),
    });
}

function toggle(url: string) {
    router.post(url);
}
</script>

<template>
    <AppLayout title="Salonlar">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Salonlar</h1>

            <form class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5" @submit.prevent="submitCreate">
                <div class="col-span-2">
                    <label for="room-name" class="block text-sm font-medium text-gray-700">Salon adı</label>
                    <input
                        id="room-name"
                        v-model="createForm.name"
                        type="text"
                        required
                        maxlength="50"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                </div>
                <div class="col-span-2">
                    <label for="room-desc" class="block text-sm font-medium text-gray-700">Açıklama</label>
                    <input
                        id="room-desc"
                        v-model="createForm.description"
                        type="text"
                        maxlength="500"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex items-end gap-2">
                    <label class="flex items-center gap-2 pb-2 text-sm text-gray-700">
                        <input v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300" />
                        Aktif
                    </label>
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Ekle
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Salon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Kapasite (aktif)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="room in rooms" :key="room.id">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ room.name }}</div>
                            <div v-if="room.description" class="text-sm text-gray-500">{{ room.description }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                            {{ room.active_seats_count }} / {{ room.seats_count }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span
                                v-if="room.is_active"
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
                                <Link
                                    :href="`/rooms/${room.id}`"
                                    class="rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-700"
                                >
                                    Koltuk Düzeni
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="openEdit(room)"
                                >
                                    Düzenle
                                </button>
                                <button
                                    v-if="!room.is_active"
                                    type="button"
                                    class="rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-700"
                                    @click="toggle(`/rooms/${room.id}/activate`)"
                                >
                                    Aktif Yap
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="toggle(`/rooms/${room.id}/deactivate`)"
                                >
                                    Pasife Al
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="rooms.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Henüz salon eklenmedi.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="editing" class="fixed inset-0 z-10 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Salonu düzenle</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <label for="edit-room-name" class="block text-sm font-medium text-gray-700">Salon adı</label>
                        <input
                            id="edit-room-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            maxlength="50"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="edit-room-desc" class="block text-sm font-medium text-gray-700">Açıklama</label>
                        <input
                            id="edit-room-desc"
                            v-model="editForm.description"
                            type="text"
                            maxlength="500"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label for="edit-room-sort" class="block text-sm font-medium text-gray-700">Sıralama</label>
                        <input
                            id="edit-room-sort"
                            v-model.number="editForm.sort_order"
                            type="number"
                            min="0"
                            max="1000"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300" />
                        Aktif
                    </label>
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
