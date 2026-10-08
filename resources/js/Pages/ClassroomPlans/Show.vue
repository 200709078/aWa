<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface GridStudent {
    id: number;
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

interface RosterStudent {
    id: number;
    school_number: string | null;
    full_name: string | null;
}

const props = defineProps<{
    plan: { id: number };
    year: { id: number; name: string };
    branch: { id: number; name: string };
    teacher: { full_name: string | null; photo_url: string | null } | null;
    grid: { maxRow: number; maxColumn: number; seats: GridSeat[] };
    unseated: RosterStudent[];
}>();

const selectedSeatId = ref<number | null>(null);
const selectedRosterId = ref<number | null>(null);

const seatMap = computed(() => {
    const map = new Map<string, GridSeat>();
    for (const seat of props.grid.seats) map.set(`${seat.row}-${seat.column}`, seat);
    return map;
});

const rowNumbers = computed(() => Array.from({ length: props.grid.maxRow }, (_, i) => i + 1));
const columnNumbers = computed(() => Array.from({ length: props.grid.maxColumn }, (_, i) => i + 1));

function seatAt(row: number, column: number): GridSeat | undefined {
    return seatMap.value.get(`${row}-${column}`);
}

function clearSelection() {
    selectedSeatId.value = null;
    selectedRosterId.value = null;
}

function onSeatClick(seat: GridSeat) {
    if (seat.student) {
        if (selectedSeatId.value === seat.id) {
            selectedSeatId.value = null;
        } else if (selectedSeatId.value) {
            router.post(`/oturme-planlari/${props.plan.id}/takas`, { seat_id_a: selectedSeatId.value, seat_id_b: seat.id }, { preserveScroll: true });
            clearSelection();
        } else {
            selectedSeatId.value = seat.id;
            selectedRosterId.value = null;
        }
        return;
    }

    if (selectedSeatId.value) {
        router.post(
            `/oturme-planlari/${props.plan.id}/tasi`,
            { from_seat_id: selectedSeatId.value, to_seat_id: seat.id },
            { preserveScroll: true },
        );
        clearSelection();
    } else if (selectedRosterId.value) {
        router.post(
            `/oturme-planlari/${props.plan.id}/tasi`,
            { student_id: selectedRosterId.value, to_seat_id: seat.id },
            { preserveScroll: true },
        );
        clearSelection();
    }
}

function addRow() {
    router.post(`/oturme-planlari/${props.plan.id}/satir-ekle`, {}, { preserveScroll: true });
}

function addColumn() {
    router.post(`/oturme-planlari/${props.plan.id}/sutun-ekle`, {}, { preserveScroll: true });
}

function onDeleteSeat(seat: GridSeat) {
    router.delete(`/oturme-planlari/koltuk/${seat.id}`, { preserveScroll: true });
    clearSelection();
}

function onRosterClick(student: RosterStudent) {
    selectedRosterId.value = selectedRosterId.value === student.id ? null : student.id;
    selectedSeatId.value = null;
}

function syncRoster() {
    router.post(`/oturme-planlari/${props.plan.id}/esitle`, {}, { preserveScroll: true });
}

function deletePlan() {
    if (window.confirm('Oturma planı silinsin mi? Koltuk düzeni kaybolur.')) {
        router.delete(`/oturme-planlari/${props.plan.id}`);
    }
}

function openPrint() {
    const url = `/oturme-planlari/${props.plan.id}/yazdir`;
    const width = 850;
    const height = 900;
    const left = Math.max(0, Math.round((window.screen.width - width) / 2));
    const top = Math.max(0, Math.round((window.screen.height - height) / 2));
    const features = `width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,resizable=no,scrollbars=yes`;
    const win = window.open(url, 'awa-print', features);
    if (!win) window.open(url, '_blank');
}

const seatedCount = computed(() => props.grid.seats.filter((s) => s.student).length);
</script>

<template>
    <AppLayout :title="`${branch.name} Oturma Planı`">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ year.name }} — {{ branch.name }} Oturma Planı</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Yerleşen: <strong>{{ seatedCount }}</strong> / Koltuk: {{ grid.seats.length }} / Listede boşta: {{ unseated.length }}
                        <span v-if="selectedSeatId || selectedRosterId" class="ml-2 text-indigo-700">Seçim aktif — hedef koltuğa tıklayın, iptal için tekrar tıklayın.</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                        @click="addRow"
                    >
                        Satır Ekle
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                        @click="addColumn"
                    >
                        Sütun Ekle
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                        @click="openPrint"
                    >
                        Yazdır / İndir
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                        @click="syncRoster"
                    >
                        Listeyle Eşitle
                    </button>
                    <Link
                        href="/oturme-planlari"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                    >
                        Geri
                    </Link>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-6">
                <div class="overflow-x-auto p-3">
                    <table class="border-collapse">
                        <tbody>
                            <tr v-for="row in rowNumbers" :key="row">
                                <td v-for="col in columnNumbers" :key="col" class="p-1 align-top">
                                    <div v-if="seatAt(row, col)" class="group relative inline-block">
                                        <button
                                            type="button"
                                            class="flex h-[220px] w-36 flex-col items-center justify-center gap-0.5 overflow-hidden rounded-md border px-1.5 py-1.5 text-center text-xs"
                                            :class="[
                                                seatAt(row, col)!.student
                                                    ? selectedSeatId === seatAt(row, col)!.id
                                                        ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-400'
                                                        : 'border-gray-300 bg-white hover:border-indigo-400'
                                                    : 'border-dashed border-gray-300 bg-gray-50 py-4 text-gray-400 hover:border-indigo-400 hover:text-indigo-600',
                                            ]"
                                            @click="onSeatClick(seatAt(row, col)!)"
                                        >
                                            <template v-if="seatAt(row, col)!.student">
                                                <StudentAvatar
                                                    :photo-url="seatAt(row, col)!.student!.photo_url"
                                                    :full-name="seatAt(row, col)!.student!.full_name ?? ''"
                                                    img-class="block h-auto w-full rounded object-contain"
                                                    placeholder-class="w-full"
                                                    circle-class="w-24 text-2xl"
                                                    class="w-full"
                                                />
                                                <span class="mt-1 w-full truncate font-semibold text-gray-900">{{ seatAt(row, col)!.student!.school_number }} {{ seatAt(row, col)!.student!.full_name }}</span>
                                            </template>
                                            <span v-else>Boş</span>
                                        </button>
                                        <button
                                            type="button"
                                            title="Koltuğu sil"
                                            aria-label="Koltuğu sil"
                                            class="absolute -right-2 -top-2 hidden h-5 w-5 items-center justify-center rounded-full bg-red-600 text-white shadow hover:bg-red-700 group-hover:inline-flex"
                                            @click.stop="onDeleteSeat(seatAt(row, col)!)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-gray-500">Dolu karta tıklayıp başka karta tıklayarak taşıyın/takas edin. Her koltuk köşesindeki kırmızı × ile silinir (doluysa öğrencisi yerleşmemişlere düşer).</p>
                    <div class="mt-3 flex justify-end">
                        <div class="w-36 rounded-md border border-gray-300 bg-gray-50 p-1.5 text-center">
                            <div class="text-xs font-semibold text-gray-700">Öğretmen Masası</div>
                            <StudentAvatar
                                :photo-url="teacher?.photo_url ?? null"
                                :full-name="teacher?.full_name ?? ''"
                                img-class="mx-auto mt-1 block h-auto w-full rounded object-contain"
                                placeholder-class="mx-auto mt-1 w-full"
                                circle-class="w-16 text-xl"
                            />
                            <div class="mt-1 truncate text-xs text-gray-700">{{ teacher?.full_name ?? 'Atanmamış' }}</div>
                            <Link
                                v-if="!teacher"
                                href="/branches"
                                class="mt-0.5 inline-block text-[11px] font-semibold text-indigo-600 hover:underline"
                            >
                                Sınıflar ekranından belirleyin
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="w-64 shrink-0">
                    <h2 class="text-sm font-semibold text-gray-900">Yerleşmemiş Öğrenciler ({{ unseated.length }})</h2>
                    <p class="mt-1 text-xs text-gray-500">Öğrenciyi seçip boş koltuğa tıklayın.</p>
                    <ul class="mt-2 max-h-96 space-y-1 overflow-y-auto">
                        <li v-for="student in unseated" :key="student.id">
                            <button
                                type="button"
                                class="w-full truncate rounded-md border px-2 py-1.5 text-left text-sm"
                                :class="selectedRosterId === student.id ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-400' : 'border-gray-200 hover:border-indigo-300'"
                                @click="onRosterClick(student)"
                            >
                                <strong>{{ student.school_number }}</strong> {{ student.full_name }}
                            </button>
                        </li>
                        <li v-if="unseated.length === 0" class="text-sm text-gray-400">Herkes yerleşmiş.</li>
                    </ul>
                    <button
                        type="button"
                        class="mt-3 inline-flex h-9 items-center justify-center rounded-md border border-red-300 bg-white px-4 text-sm text-red-700 shadow-sm hover:bg-red-50"
                        @click="deletePlan"
                    >
                        Planı Sil
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
