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

function cellPadClass(room: RoomData, col: number): string {
    const third = Math.ceil(room.maxColumn / 3);
    if (col <= third) return 'pl-2';
    if (col > room.maxColumn - third) return 'pr-2';
    return '';
}

function nameSizeClass(fullName: string): string {
    if (fullName.length > 28) return 'text-[9px] print:text-[8px]';
    if (fullName.length > 18) return 'text-[10px] print:text-[9px]';
    return '';
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
            <div class="no-print flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="photos"
                    title="Fotoğrafları Göster/Gizle"
                    :class="[
                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                        photos ? 'bg-indigo-600' : 'bg-gray-300',
                    ]"
                    @click="photos = !photos"
                >
                    <span
                        :class="[
                            'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5',
                            photos ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                        ]"
                    />
                </button>
                <span class="text-sm text-gray-700">Fotoğraflar</span>
            </div>
            <div class="no-print flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="showNumber"
                    title="Numarayı Göster/Gizle"
                    :class="[
                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                        showNumber ? 'bg-indigo-600' : 'bg-gray-300',
                    ]"
                    @click="showNumber = !showNumber"
                >
                    <span
                        :class="[
                            'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5',
                            showNumber ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                        ]"
                    />
                </button>
                <span class="text-sm text-gray-700">Numara</span>
            </div>
            <div class="no-print flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2">
                <button
                    type="button"
                    role="switch"
                    :aria-checked="showBranch"
                    title="Sınıfı Göster/Gizle"
                    :class="[
                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                        showBranch ? 'bg-indigo-600' : 'bg-gray-300',
                    ]"
                    @click="showBranch = !showBranch"
                >
                    <span
                        :class="[
                            'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5',
                            showBranch ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                        ]"
                    />
                </button>
                <span class="text-sm text-gray-700">Sınıf</span>
            </div>
        </template>

        <div
            v-for="(room, index) in grid"
            :key="room.room.id"
            :class="index < grid.length - 1 ? 'print:break-after-page' : ''"
            class="mt-6 print:pb-[8mm]"
        >
            <h2 class="text-lg font-semibold print:text-base">{{ week.name }} - {{ room.room.name }} Oturma Planı</h2>
            <div class="print:break-inside-avoid">
            <table class="mt-2 w-full table-fixed border-collapse print:mt-1">
                <tbody>
                    <tr v-for="row in rowNumbers(room)" :key="row">
                        <td
                            v-for="col in columnNumbers(room)"
                            :key="col"
                            class="p-0.5 align-top"
                            :class="[
                                seatMap(room).get(`${row}-${col}`)?.student ? 'border border-gray-300' : '',
                                cellPadClass(room, col),
                            ]"
                        >
                            <div
                                v-if="seatMap(room).get(`${row}-${col}`)"
                                class="text-center text-[11px] leading-tight print:text-[10px]"
                            >
                                <template v-if="seatMap(room).get(`${row}-${col}`)!.student">
                                    <StudentAvatar
                                        v-if="photos"
                                        :photo-url="seatMap(room).get(`${row}-${col}`)!.student!.photo_url"
                                        :full-name="seatMap(room).get(`${row}-${col}`)!.student!.full_name"
                                        img-class="block h-20 w-full object-contain print:h-14"
                                        placeholder-class="max-h-20 w-full print:max-h-14"
                                        circle-class="w-12 text-base"
                                    />
                                    <div
                                        v-else
                                        class="invisible h-20 w-full print:h-14"
                                        aria-hidden="true"
                                    />
                                </template>
                                <template v-if="seatMap(room).get(`${row}-${col}`)!.student">
                                    <div v-if="photos" class="my-1 border-t border-dashed border-gray-300 print:my-0.5"></div>
                                    <div
                                        class="min-h-[2.5em] text-center font-semibold"
                                        :class="nameSizeClass(seatMap(room).get(`${row}-${col}`)!.student!.full_name)"
                                    >
                                        <span v-if="showNumber"
                                            >{{ seatMap(room).get(`${row}-${col}`)!.student!.school_number }}
                                        </span>
                                        {{ seatMap(room).get(`${row}-${col}`)!.student!.full_name }}
                                    </div>
                                    <div
                                        class="min-h-[1.25em] truncate text-center text-gray-500"
                                        :class="showBranch ? '' : 'invisible'"
                                        :aria-hidden="!showBranch"
                                    >
                                        {{ seatMap(room).get(`${row}-${col}`)!.student!.branch }}
                                    </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-2 flex flex-wrap items-start gap-2 print:mt-1 print:break-inside-avoid">
                <div class="grid min-w-0 flex-1 grid-cols-4 gap-2">
                <div v-for="group in levelTables(room)" :key="group.label">
                    <table class="w-full border-collapse border text-[10px]">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-1.5 py-px text-left font-medium text-gray-500">Sınıf</th>
                                <th class="border px-1.5 py-px text-right font-medium text-gray-500">Öğrenci</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in group.rows" :key="entry.branch">
                                <td class="border px-1.5 py-px">{{ entry.branch }}</td>
                                <td class="border px-1.5 py-px text-right">{{ entry.count }}</td>
                            </tr>
                            <tr v-if="group.rows.length === 0">
                                <td colspan="2" class="border px-1.5 py-px text-gray-400">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>
                <table class="ml-auto w-32 shrink-0 border-collapse border text-sm">
                    <tbody>
                        <tr>
                            <td class="border px-3 py-1.5 text-center font-semibold">Öğretmen</td>
                        </tr>
                        <tr>
                            <td class="border px-3 py-1.5 text-center font-semibold">Masası</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </PrintLayout>
</template>
