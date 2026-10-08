<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import PrintLayout from '../../Layouts/PrintLayout.vue';

interface GridStudent {
    school_number: string | null;
    full_name: string | null;
    photo_url: string | null;
}

interface GridSeat {
    id: number;
    row: number;
    column: number;
    student: GridStudent | null;
}

const props = defineProps<{
    plan: { id: number };
    year: { name: string };
    branch: { name: string };
    teacher: { full_name: string | null; photo_url: string | null } | null;
    grid: { maxRow: number; maxColumn: number; seats: GridSeat[] };
}>();

const schoolName = computed(() => {
    const pageProps = usePage().props as unknown as { current_school: { name: string } | null };
    return pageProps.current_school?.name ?? '';
});

const seatMap = computed(() => {
    const map = new Map<string, GridSeat>();
    for (const seat of props.grid.seats) map.set(`${seat.row}-${seat.column}`, seat);
    return map;
});

const rowNumbers = computed(() => Array.from({ length: props.grid.maxRow }, (_, i) => i + 1));
const boardWidth = computed(() => `${(2 / Math.max(1, props.grid.maxColumn)) * 100}%`);
const columnNumbers = computed(() => Array.from({ length: props.grid.maxColumn }, (_, i) => i + 1));

function nameSizeClass(fullName: string | null): string {
    const len = fullName?.length ?? 0;
    if (len > 28) return 'text-[9px] print:text-[8px]';
    if (len > 18) return 'text-[10px] print:text-[9px]';
    return '';
}
</script>

<template>
    <PrintLayout :title="`${branch.name} Sınıf Oturma Planı`" :back-href="`/oturme-planlari/${plan.id}`">
        <h2 class="text-lg font-semibold print:text-base">{{ year.name }} ÖĞRETİM YILI {{ schoolName }}</h2>
        <h3 class="mt-1 text-base font-semibold print:text-sm">{{ branch.name }} Sınıf Oturma Planı</h3>

        <div class="mt-4 print:break-inside-avoid">
            <table class="w-full table-fixed border-collapse">
                <tbody>
                    <tr v-for="row in rowNumbers" :key="row">
                        <td v-for="col in columnNumbers" :key="col" class="p-0.5 align-top">
                            <div
                                v-if="seatMap.get(`${row}-${col}`)?.student"
                                class="border border-gray-300 text-center text-[11px] leading-tight print:text-[10px]"
                            >
                                <StudentAvatar
                                    :photo-url="seatMap.get(`${row}-${col}`)!.student!.photo_url"
                                    :full-name="seatMap.get(`${row}-${col}`)!.student!.full_name ?? ''"
                                    img-class="block h-20 w-full object-contain print:h-14"
                                    placeholder-class="mx-auto h-20 w-auto print:h-14"
                                    circle-class="w-12 text-base"
                                />
                                <div class="my-1 border-t border-dashed border-gray-300 print:my-0.5"></div>
                                <div class="font-bold">{{ seatMap.get(`${row}-${col}`)!.student!.school_number }}</div>
                                <div :class="nameSizeClass(seatMap.get(`${row}-${col}`)!.student!.full_name)">
                                    {{ seatMap.get(`${row}-${col}`)!.student!.full_name }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-2 flex justify-center">
            <div
                class="rounded border border-gray-400 py-1 text-center text-sm font-bold tracking-widest print:text-xs"
                :style="{ width: boardWidth }"
            >
                TAHTA
            </div>
        </div>
        <div class="mt-4 flex justify-end print:break-inside-avoid">
            <div class="w-36 rounded border border-gray-400 p-1.5 text-center">
                <div class="text-xs font-bold print:text-[10px]">ÖĞRETMEN MASASI</div>
                <StudentAvatar
                    :photo-url="teacher?.photo_url ?? null"
                    :full-name="teacher?.full_name ?? ''"
                    img-class="mx-auto mt-1 block h-20 w-full object-contain print:h-14"
                    placeholder-class="mx-auto mt-1 h-20 w-auto print:h-14"
                    circle-class="w-12 text-base"
                />
                <div class="mt-1 text-sm print:text-xs">{{ teacher?.full_name ?? '' }}</div>
                <div class="text-xs text-gray-600 print:text-[10px]">Rehber Öğretmen</div>
            </div>
        </div>
    </PrintLayout>
</template>
