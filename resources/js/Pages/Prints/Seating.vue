<script setup lang="ts">
import { ref } from 'vue';
import PrintLayout from '../../Layouts/PrintLayout.vue';

interface SeatInfo {
    id: number;
    row: number;
    column: number;
    label: string | null;
    student: {
        school_number: string;
        full_name: string;
        branch: string | null;
        photo_url: string | null;
    } | null;
}

interface RoomData {
    room: { id: number; name: string };
    maxRow: number;
    maxColumn: number;
    seats: SeatInfo[];
}

const props = defineProps<{
    plan: { id: number; name: string | null };
    week: { name: string };
    showPhotos: boolean;
    grid: RoomData[];
}>();

const photos = ref(props.showPhotos);
const showNumber = ref(true);
const showBranch = ref(true);

function seatMap(room: RoomData): Map<string, SeatInfo> {
    const map = new Map<string, SeatInfo>();
    for (const seat of room.seats) {
        map.set(`${seat.row}-${seat.column}`, seat);
    }
    return map;
}

function rowNumbers(room: RoomData): number[] {
    return Array.from({ length: room.maxRow }, (_, i) => i + 1);
}

function columnNumbers(room: RoomData): number[] {
    return Array.from({ length: room.maxColumn }, (_, i) => i + 1);
}
</script>

<template>
    <PrintLayout :title="`Salon Oturma Planları`" :back-href="`/distribution/plans/${plan.id}`">
        <template #actions>
            <label class="no-print flex items-center gap-1 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                <input v-model="photos" type="checkbox" class="rounded border-gray-300" />
                Fotoğraflar
            </label>
            <label class="no-print flex items-center gap-1 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                <input v-model="showNumber" type="checkbox" class="rounded border-gray-300" />
                Numara
            </label>
            <label class="no-print flex items-center gap-1 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                <input v-model="showBranch" type="checkbox" class="rounded border-gray-300" />
                Şube
            </label>
        </template>

        <h1 class="text-xl font-bold">Salon Oturma Planları — {{ week.name }}{{ plan.name ? ` · ${plan.name}` : '' }}</h1>

        <div
            v-for="(room, index) in grid"
            :key="room.room.id"
            :class="index < grid.length - 1 ? 'print:break-after-page' : ''"
            class="mt-6"
        >
            <h2 class="text-lg font-semibold">{{ room.room.name }}</h2>
            <table class="mt-2 border-collapse">
                <tbody>
                    <tr v-for="row in rowNumbers(room)" :key="row">
                        <td class="pr-2 text-right text-xs font-semibold text-gray-400">{{ row }}</td>
                        <td v-for="col in columnNumbers(room)" :key="col" class="border border-gray-300 p-1">
                            <div
                                v-if="seatMap(room).get(`${row}-${col}`)"
                                class="leading-tight"
                                :class="photos ? 'w-24 text-[11px]' : 'w-20 text-[10px]'"
                            >
                                <img
                                    v-if="photos && seatMap(room).get(`${row}-${col}`)!.student?.photo_url"
                                    :src="seatMap(room).get(`${row}-${col}`)!.student!.photo_url!"
                                    alt=""
                                    class="mb-1 h-10 w-full object-cover"
                                />
                                <div
                                    v-else-if="photos && seatMap(room).get(`${row}-${col}`)!.student"
                                    class="mb-1 text-gray-400"
                                >
                                    Foto yok
                                </div>
                                <template v-if="seatMap(room).get(`${row}-${col}`)!.student">
                                    <div class="font-semibold">
                                        <span v-if="showNumber"
                                            >{{ seatMap(room).get(`${row}-${col}`)!.student!.school_number }}
                                        </span>
                                        {{ seatMap(room).get(`${row}-${col}`)!.student!.full_name }}
                                    </div>
                                    <div v-if="showBranch" class="text-gray-500">
                                        {{ seatMap(room).get(`${row}-${col}`)!.student!.branch }}
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="text-gray-400">Boş ({{ row }}-{{ col }})</div>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PrintLayout>
</template>
