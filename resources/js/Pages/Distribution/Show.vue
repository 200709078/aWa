<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface SeatInfo {
    id: number;
    row: number;
    column: number;
    label: string | null;
    violation: boolean;
    student: { school_number: string; full_name: string; branch: string | null } | null;
}

interface RoomData {
    room: { id: number; name: string };
    maxRow: number;
    maxColumn: number;
    seats: SeatInfo[];
}

const props = defineProps<{
    plan: {
        id: number;
        name: string | null;
        status: string;
        total_students: number;
        used_room_count: number;
        created_at: string | null;
        creator: string | null;
    };
    week: { id: number; name: string };
    summary: {
        total_students: number;
        used_rooms: { id: number; name: string }[];
        unused_rooms: { id: number; name: string }[];
        used_seats: number;
        empty_seats: number;
        violations: number;
    };
    roomsData: RoomData[];
}>();

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

function seatLabel(seat: SeatInfo): string {
    return seat.label || `${seat.row}-${seat.column}`;
}

const title = computed(() => props.plan.name || `Plan #${props.plan.id}`);

function finalize() {
    router.post(`/distribution/plans/${props.plan.id}/finalize`);
}

function reopen() {
    router.post(`/distribution/plans/${props.plan.id}/reopen`);
}
</script>

<template>
    <AppLayout :title="title">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ title }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ week.name }}
                        <span v-if="plan.creator"> · {{ plan.creator }}</span>
                        <span v-if="plan.created_at"> · {{ plan.created_at }}</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <span
                        v-if="plan.status === 'final'"
                        class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800"
                    >
                        Final
                    </span>
                    <span v-else class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                        Taslak
                    </span>
                    <button
                        v-if="plan.status !== 'final'"
                        type="button"
                        class="rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-700"
                        @click="finalize"
                    >
                        Final Yap
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                        @click="reopen"
                    >
                        Taslağa Al
                    </button>
                    <Link
                        :href="`/distribution?exam_week_id=${week.id}`"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                    >
                        Dağıtıma dön
                    </Link>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-6">
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.total_students }}</div>
                    <div class="text-xs text-gray-500">Öğrenci</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.used_rooms.length }}</div>
                    <div class="text-xs text-gray-500">Kullanılan salon</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.unused_rooms.length }}</div>
                    <div class="text-xs text-gray-500">Boş bırakılan</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.used_seats }}</div>
                    <div class="text-xs text-gray-500">Dolu koltuk</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summary.empty_seats }}</div>
                    <div class="text-xs text-gray-500">Boş koltuk</div>
                </div>
                <div
                    class="rounded-md px-3 py-2 text-center"
                    :class="summary.violations > 0 ? 'bg-red-50' : 'bg-green-50'"
                >
                    <div
                        class="text-xl font-bold"
                        :class="summary.violations > 0 ? 'text-red-700' : 'text-green-700'"
                    >
                        {{ summary.violations }}
                    </div>
                    <div class="text-xs text-gray-500">Yatay ihlal</div>
                </div>
            </div>

            <p v-if="summary.violations > 0" class="mt-3 rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                {{ summary.violations }} koltukta aynı şube yan yana geldi (kırmızı). Elle düzeltme adımında
                düzenleyebilirsiniz.
            </p>
            <p v-if="summary.unused_rooms.length > 0" class="mt-2 text-sm text-gray-500">
                Kullanılmayan: {{ summary.unused_rooms.map((r) => r.name).join(', ') }}
            </p>
        </div>

        <div v-for="room in roomsData" :key="room.room.id" class="mt-6 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">{{ room.room.name }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="border-collapse">
                    <tbody>
                        <tr v-for="row in rowNumbers(room)" :key="row">
                            <td class="pr-2 text-right text-xs font-semibold text-gray-400">{{ row }}</td>
                            <td v-for="col in columnNumbers(room)" :key="col" class="p-1">
                                <div
                                    v-if="seatMap(room).get(`${row}-${col}`)"
                                    class="min-h-12 w-28 rounded-md px-2 py-1 text-xs"
                                    :class="
                                        seatMap(room).get(`${row}-${col}`)!.violation
                                            ? 'bg-red-100 text-red-900'
                                            : seatMap(room).get(`${row}-${col}`)!.student
                                              ? 'bg-indigo-50 text-gray-900'
                                              : 'bg-gray-50 text-gray-400'
                                    "
                                >
                                    <div class="font-semibold">
                                        {{ seatLabel(seatMap(room).get(`${row}-${col}`)!) }}
                                    </div>
                                    <template v-if="seatMap(room).get(`${row}-${col}`)!.student">
                                        <div class="truncate">
                                            {{ seatMap(room).get(`${row}-${col}`)!.student!.school_number }}
                                            {{ seatMap(room).get(`${row}-${col}`)!.student!.full_name }}
                                        </div>
                                        <div class="text-gray-500">
                                            {{ seatMap(room).get(`${row}-${col}`)!.student!.branch }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div>Boş</div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
