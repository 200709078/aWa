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
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Salonlar</h1>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Salon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Kapasite (aktif)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
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
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="room.is_active"
                                    :title="room.is_active ? 'Pasif' : 'Aktif'"
                                    @click="
                                        toggle(
                                            room.is_active
                                                ? `/rooms/${room.id}/deactivate`
                                                : `/rooms/${room.id}/activate`,
                                        )
                                    "
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                                        room.is_active ? 'bg-indigo-600' : 'bg-gray-300',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5',
                                            room.is_active ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                                        ]"
                                    />
                                </button>
                                <span
                                    v-if="room.is_active"
                                    class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"
                                >
                                    Aktif
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600"
                                >
                                    Pasif
                                </span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-left">
                            <div class="flex justify-start gap-2">
                                <Link
                                    :href="`/rooms/${room.id}`"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                >
                                    Koltuk Düzeni
                                </Link>
                                <button
                                    type="button"
                                    title="Düzenle"
                                    aria-label="Düzenle"
                                    class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                                    @click="openEdit(room)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
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

        <div class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Yeni Salon Ekle</h2>

            <form class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5" @submit.prevent="submitCreate">
                <div class="col-span-2">
                    <label for="room-name" class="block text-sm font-medium text-gray-700">Salon Adı</label>
                    <input
                        id="room-name"
                        v-model="createForm.name"
                        type="text"
                        required
                        maxlength="50"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex items-end">
                <button
                    type="submit"
                    title="Ekle"
                    aria-label="Ekle"
                    :disabled="createForm.processing"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                </button>
                </div>
            </form>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-sm overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Salonu Düzenle</h2>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                title="Kaydet"
                                aria-label="Kaydet"
                                :disabled="editForm.processing"
                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            </button>
                            <button
                                type="button"
                                title="Vazgeç"
                                aria-label="Vazgeç"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                @click="editing = null"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="edit-room-name" class="block text-sm font-medium text-gray-700">Salon Adı</label>
                        <input
                            id="edit-room-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            maxlength="50"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
