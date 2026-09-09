<script setup lang="ts">
import { ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
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
        grade_level: number | null;
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

function levelTables(room: RoomData): { label: string; rows: { branch: string; count: number }[] }[] {
    const byLevel = new Map<number | null, Map<string, number>>();
    for (const seat of room.seats) {
        if (!seat.student) continue;
        const level = seat.student.grade_level ?? null;
        const branch = seat.student.branch || '—';
        if (!byLevel.has(level)) byLevel.set(level, new Map());
        const counts = byLevel.get(level)!;
        counts.set(branch, (counts.get(branch) || 0) + 1);
    }
    const toRows = (level: number | null) =>
        [...(byLevel.get(level) ?? new Map()).entries()]
            .map(([branch, count]) => ({ branch, count }))
            .sort((a, b) => a.branch.localeCompare(b.branch, 'tr', { numeric: true }));
    const groups = [9, 10, 11, 12].map((level) => ({ label: `${level}. Sınıf`, rows: toRows(level) }));
    if (byLevel.has(null)) {
        groups.push({ label: '—', rows: toRows(null) });
    }
    return groups;
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
            <table class="mt-2 w-full table-fixed border-collapse">
                <tbody>
                    <tr v-for="row in rowNumbers(room)" :key="row">
                        <td v-for="col in columnNumbers(room)" :key="col" class="border border-gray-300 p-1">
                            <div
                                v-if="seatMap(room).get(`${row}-${col}`)"
                                class="text-center leading-tight"
                                :class="photos ? 'text-[11px]' : 'text-[10px]'"
                            >
                                <StudentAvatar
                                    v-if="photos && seatMap(room).get(`${row}-${col}`)!.student"
                                    :photo-url="seatMap(room).get(`${row}-${col}`)!.student!.photo_url"
                                    :full-name="seatMap(room).get(`${row}-${col}`)!.student!.full_name"
                                    img-class="mb-1 block h-auto w-full object-contain"
                                    placeholder-class="mb-1 w-full"
                                    circle-class="w-14 text-lg"
                                />
                                <template v-if="seatMap(room).get(`${row}-${col}`)!.student">
                                    <div class="text-center font-semibold">
                                        <span v-if="showNumber"
                                            >{{ seatMap(room).get(`${row}-${col}`)!.student!.school_number }}
                                        </span>
                                        {{ seatMap(room).get(`${row}-${col}`)!.student!.full_name }}
                                    </div>
                                    <div v-if="showBranch" class="text-center text-gray-500">
                                        {{ seatMap(room).get(`${row}-${col}`)!.student!.branch }}
                                    </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-3 grid grid-cols-4 gap-3 print:break-inside-avoid">
                <div v-for="group in levelTables(room)" :key="group.label">
                    <table class="w-full border-collapse border text-[11px]">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-2 py-0.5 text-left font-medium text-gray-500">Şube</th>
                                <th class="border px-2 py-0.5 text-right font-medium text-gray-500">Öğrenci</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in group.rows" :key="entry.branch">
                                <td class="border px-2 py-0.5">{{ entry.branch }}</td>
                                <td class="border px-2 py-0.5 text-right">{{ entry.count }}</td>
                            </tr>
                            <tr v-if="group.rows.length === 0">
                                <td colspan="2" class="border px-2 py-0.5 text-gray-400">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </PrintLayout>
</template>
