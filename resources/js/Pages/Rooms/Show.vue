<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Room {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
}

interface Seat {
    id: number;
    row: number;
    column: number;
    label: string | null;
    is_active: boolean;
}

const props = defineProps<{
    room: Room;
    seats: Seat[];
    maxRow: number;
    maxColumn: number;
    activeCount: number;
}>();

const bulkForm = useForm({ rows: null as number | null, columns: null as number | null });

function submitBulk() {
    bulkForm.post(`/rooms/${props.room.id}/seats/bulk`, {
        onSuccess: () => bulkForm.reset(),
    });
}

const seatMap = computed(() => {
    const map = new Map<string, Seat>();
    for (const seat of props.seats) {
        map.set(`${seat.row}-${seat.column}`, seat);
    }
    return map;
});

function seatAt(row: number, column: number): Seat | undefined {
    return seatMap.value.get(`${row}-${column}`);
}

function seatLabel(seat: Seat): string {
    return seat.label || `${seat.row}-${seat.column}`;
}

function toggleSeat(seat: Seat) {
    router.post(`/seats/${seat.id}/toggle`);
}

function addSeat(row: number, column: number) {
    router.post(`/rooms/${props.room.id}/seats`, { row, column });
}

const rowNumbers = computed(() => Array.from({ length: props.maxRow }, (_, i) => i + 1));
const columnNumbers = computed(() => Array.from({ length: props.maxColumn }, (_, i) => i + 1));
</script>

<template>
    <AppLayout :title="room.name">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ room.name }}</h1>
                    <p v-if="room.description" class="mt-1 text-sm text-gray-500">{{ room.description }}</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Aktif kapasite: <strong>{{ activeCount }}</strong> / Toplam koltuk: {{ seats.length }}
                    </p>
                </div>
                <Link
                    href="/rooms"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Geri
                </Link>
            </div>

            <form class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4" @submit.prevent="submitBulk">
                <div>
                    <label for="bulk-rows" class="block text-sm font-medium text-gray-700">Satır Sayısı</label>
                    <input
                        id="bulk-rows"
                        v-model.number="bulkForm.rows"
                        type="number"
                        required
                        min="1"
                        max="50"
                        placeholder="5"
                        class="mt-1 block h-9 w-28 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="bulkForm.errors.rows" class="mt-1 text-sm text-red-600">{{ bulkForm.errors.rows }}</p>
                </div>
                <div>
                    <label for="bulk-cols" class="block text-sm font-medium text-gray-700">Sütun Sayısı</label>
                    <input
                        id="bulk-cols"
                        v-model.number="bulkForm.columns"
                        type="number"
                        required
                        min="1"
                        max="50"
                        placeholder="6"
                        class="mt-1 block h-9 w-28 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="bulkForm.errors.columns" class="mt-1 text-sm text-red-600">{{ bulkForm.errors.columns }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="bulkForm.processing"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                >
                    Koltukları Ekle
                </button>
                <p class="w-full text-xs text-gray-500">
                    Var olan koltuklar korunur. Koltuğa tıklayarak aktif/pasif yapabilir, boş hücreye tıklayarak tek
                    koltuk ekleyebilirsiniz.
                </p>
            </form>
        </div>

        <div v-if="seats.length > 0" class="mt-6 overflow-x-auto rounded-lg bg-white p-6 shadow-sm">
            <table class="border-collapse">
                <tbody>
                    <tr v-for="row in rowNumbers" :key="row">
                        <td v-for="col in columnNumbers" :key="col" class="p-1">
                            <button
                                v-if="seatAt(row, col)"
                                type="button"
                                :title="seatAt(row, col)!.is_active ? 'Pasif' : 'Aktif'"
                                class="h-12 w-14 rounded-md text-xs font-semibold"
                                :class="
                                    seatAt(row, col)!.is_active
                                        ? 'bg-indigo-100 text-indigo-900 hover:bg-indigo-200'
                                        : 'bg-gray-200 text-gray-400 line-through hover:bg-gray-300'
                                "
                                @click="toggleSeat(seatAt(row, col)!)"
                            >
                                {{ seatLabel(seatAt(row, col)!) }}
                            </button>
                            <button
                                v-else
                                type="button"
                                title="Koltuk eklemek için tıkla"
                                class="h-12 w-14 rounded-md border border-dashed border-gray-300 text-gray-300 hover:border-indigo-400 hover:text-indigo-400"
                                @click="addSeat(row, col)"
                            >
                                +
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="mt-6 rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
            Henüz koltuk yok. Yukarıdan satır × sütun girerek ekleyin.
        </div>
    </AppLayout>
</template>
