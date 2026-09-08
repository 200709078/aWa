<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface SeatInfo {
    id: number;
    row: number;
    column: number;
    label: string | null;
    violation: boolean;
    assignment_id: number | null;
    student: { school_number: string; full_name: string; branch: string | null; photo_url: string | null } | null;
}

interface RoomData {
    room: { id: number; name: string };
    maxRow: number;
    maxColumn: number;
    seats: SeatInfo[];
}

interface Summary {
    total_students: number;
    used_rooms: { id: number; name: string }[];
    unused_rooms: { id: number; name: string }[];
    used_seats: number;
    empty_seats: number;
    violations: number;
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
    summary: Summary;
    roomsData: RoomData[];
}>();

const rooms = ref<RoomData[]>(props.roomsData);
const summaryState = ref<Summary>(props.summary);

const editMode = ref(false);
const selected = ref<{ assignmentId: number; seatId: number } | null>(null);
const notice = ref<{ type: 'ok' | 'error'; text: string } | null>(null);
const salonModal = ref(false);
const confirmState = ref<{ message: string; retry: () => void } | null>(null);
const dragPayload = ref<{ assignmentId: number } | null>(null);
const busy = ref(false);

const showPhotos = ref(localStorage.getItem('kelebek-show-photos') !== '0');
watch(showPhotos, (value) => localStorage.setItem('kelebek-show-photos', value ? '1' : '0'));

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

function toggleEdit() {
    editMode.value = !editMode.value;
    selected.value = null;
    salonModal.value = false;
    confirmState.value = null;
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function postJson(url: string, body: object): Promise<any> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!res.ok) {
        throw new Error(data.message || 'İşlem başarısız.');
    }
    return data;
}

function applyUpdate(data: any) {
    rooms.value = data.roomsData;
    summaryState.value = data.summary;
    selected.value = null;
    salonModal.value = false;
    confirmState.value = null;
    notice.value = { type: 'ok', text: data.message || 'Kaydedildi.' };
}

function handleResponse(data: any, retry: () => void) {
    if (data.needs_confirm) {
        confirmState.value = { message: data.message, retry };
        return;
    }
    if (data.applied) {
        applyUpdate(data);
    }
}

async function doMove(seatId: number, force = false) {
    if (!selected.value || busy.value) return;
    const payload = { assignment_id: selected.value.assignmentId, seat_id: seatId, force };
    busy.value = true;
    try {
        const data = await postJson(`/distribution/plans/${props.plan.id}/move`, payload);
        handleResponse(data, () => doMove(seatId, true));
    } catch (e: any) {
        notice.value = { type: 'error', text: e.message };
    } finally {
        busy.value = false;
    }
}

async function doSwap(otherAssignmentId: number, force = false) {
    if (!selected.value || busy.value) return;
    const payload = {
        assignment_id: selected.value.assignmentId,
        other_assignment_id: otherAssignmentId,
        force,
    };
    busy.value = true;
    try {
        const data = await postJson(`/distribution/plans/${props.plan.id}/swap`, payload);
        handleResponse(data, () => doSwap(otherAssignmentId, true));
    } catch (e: any) {
        notice.value = { type: 'error', text: e.message };
    } finally {
        busy.value = false;
    }
}

function onSeatClick(seat: SeatInfo) {
    if (!editMode.value || busy.value) return;
    notice.value = null;
    if (!seat.student) {
        if (selected.value) {
            doMove(seat.id);
        }
        return;
    }
    if (!selected.value) {
        selected.value = { assignmentId: seat.assignment_id!, seatId: seat.id };
        return;
    }
    if (selected.value.seatId === seat.id) {
        selected.value = null;
        return;
    }
    doSwap(seat.assignment_id!);
}

function onDrop(seat: SeatInfo) {
    if (!editMode.value || busy.value || !dragPayload.value) return;
    notice.value = null;
    const draggedId = dragPayload.value.assignmentId;
    dragPayload.value = null;
    if (!seat.student) {
        selected.value = { assignmentId: draggedId, seatId: -1 };
        doMove(seat.id);
        return;
    }
    if (seat.assignment_id === draggedId) return;
    selected.value = { assignmentId: draggedId, seatId: -1 };
    doSwap(seat.assignment_id!);
}

const otherRooms = computed(() => {
    if (!selected.value) return [];
    return rooms.value
        .map((room) => ({
            room,
            emptySeats: room.seats.filter((s) => !s.student),
        }))
        .filter((r) => r.emptySeats.length > 0);
});

function isSelected(seat: SeatInfo): boolean {
    return selected.value !== null && selected.value.seatId === seat.id;
}
</script>

<template>
    <AppLayout :title="title">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ title }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ week.name }}
                        <span v-if="plan.creator"> · {{ plan.creator }}</span>
                        <span v-if="plan.created_at"> · {{ plan.created_at }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="editMode"
                        type="button"
                        :disabled="!selected"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                        @click="salonModal = true"
                    >
                        Salon Değiştir
                    </button>
                    <button
                        type="button"
                        :title="editMode ? 'Düzenlemeyi Kapat' : 'Elle Düzenle'"
                        :aria-label="editMode ? 'Düzenlemeyi Kapat' : 'Elle Düzenle'"
                        :class="
                            editMode
                                ? 'inline-flex items-center rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-700'
                                : 'inline-flex items-center rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100'
                        "
                        @click="toggleEdit"
                    >
                        {{ editMode ? 'Düzenlemeyi Kapat' : 'Elle Düzenle' }}
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="plan.status === 'final'"
                            :title="plan.status === 'final' ? 'Taslağa Al' : 'Final Yap'"
                            :class="[
                                'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                                plan.status === 'final' ? 'bg-indigo-600' : 'bg-gray-300',
                            ]"
                            @click="plan.status === 'final' ? reopen() : finalize()"
                        >
                            <span
                                :class="[
                                    'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5',
                                    plan.status === 'final' ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                                ]"
                            />
                        </button>
                        <span
                            v-if="plan.status === 'final'"
                            class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800"
                        >
                            Final
                        </span>
                        <span v-else class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                            Taslak
                        </span>
                    </div>
                    <label
                        class="flex items-center gap-1 rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700"
                    >
                        <input v-model="showPhotos" type="checkbox" class="rounded border-gray-300" />
                        Fotoğraflar
                    </label>
                    <Link
                        :href="`/distribution?exam_week_id=${week.id}`"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                    >
                        Geri
                    </Link>
                </div>
            </div>

            <p v-if="editMode" class="mt-3 rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-800">
                Düzenleme açık: önce bir öğrenci seçin, sonra boş koltuğa tıklayarak taşıyın veya başka öğrenciye
                tıklayarak takas edin. Sürükle-bırak da kullanabilirsiniz.
            </p>

            <div v-if="notice" class="mt-3 rounded-md px-4 py-2 text-sm"
                :class="notice.type === 'ok' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700'">
                {{ notice.text }}
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-6">
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summaryState.total_students }}</div>
                    <div class="text-xs text-gray-500">Öğrenci</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summaryState.used_rooms.length }}</div>
                    <div class="text-xs text-gray-500">Kullanılan Salon</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summaryState.unused_rooms.length }}</div>
                    <div class="text-xs text-gray-500">Boş Bırakılan</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summaryState.used_seats }}</div>
                    <div class="text-xs text-gray-500">Dolu Koltuk</div>
                </div>
                <div class="rounded-md bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold">{{ summaryState.empty_seats }}</div>
                    <div class="text-xs text-gray-500">Boş Koltuk</div>
                </div>
                <div
                    class="rounded-md px-3 py-2 text-center"
                    :class="summaryState.violations > 0 ? 'bg-red-50' : 'bg-green-50'"
                >
                    <div
                        class="text-xl font-bold"
                        :class="summaryState.violations > 0 ? 'text-red-700' : 'text-green-700'"
                    >
                        {{ summaryState.violations }}
                    </div>
                    <div class="text-xs text-gray-500">Yatay İhlal</div>
                </div>
            </div>

            <p v-if="summaryState.violations > 0" class="mt-3 rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                {{ summaryState.violations }} koltukta aynı şube yan yana geldi (kırmızı).
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-2 border-t pt-3">
                <span class="py-1 text-sm font-medium text-gray-700">Çıktılar:</span>
                <a
                    :href="`/distribution/plans/${plan.id}/print/seating?photo=1`"
                    target="_blank"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Salon Planları (Fotoğraflı)
                </a>
                <a
                    :href="`/distribution/plans/${plan.id}/print/seating?photo=0`"
                    target="_blank"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Salon Planları (Fotoğrafsız)
                </a>
                <a
                    :href="`/distribution/plans/${plan.id}/print/branches`"
                    target="_blank"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Şube Listeleri
                </a>
                <a
                    :href="`/distribution/plans/${plan.id}/print/rooms`"
                    target="_blank"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Salon Listeleri
                </a>
                <a
                    :href="`/distribution/plans/${plan.id}/print/summary`"
                    target="_blank"
                    class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                    Dağılım Özeti
                </a>
            </div>
        </div>

        <div v-for="room in rooms" :key="room.room.id" class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">{{ room.room.name }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="border-collapse">
                    <tbody>
                        <tr v-for="row in rowNumbers(room)" :key="row">
                            <td v-for="col in columnNumbers(room)" :key="col" class="p-1">
                                <div
                                    v-if="seatMap(room).get(`${row}-${col}`)"
                                    class="min-h-12 rounded-md px-2 py-1 text-center text-xs"
                                    :class="[
                                        seatMap(room).get(`${row}-${col}`)!.violation
                                            ? 'bg-red-100 text-red-900'
                                            : seatMap(room).get(`${row}-${col}`)!.student
                                              ? 'bg-indigo-50 text-gray-900'
                                              : 'bg-gray-50 text-gray-400',
                                        editMode ? 'cursor-pointer' : '',
                                        isSelected(seatMap(room).get(`${row}-${col}`)!)
                                            ? 'ring-2 ring-indigo-600'
                                            : '',
                                        showPhotos ? 'w-36' : 'w-28',
                                    ]"
                                    :draggable="editMode && !!seatMap(room).get(`${row}-${col}`)!.student"
                                    @click="onSeatClick(seatMap(room).get(`${row}-${col}`)!)"
                                    @dragstart="
                                        dragPayload = seatMap(room).get(`${row}-${col}`)!.student
                                            ? { assignmentId: seatMap(room).get(`${row}-${col}`)!.assignment_id! }
                                            : null
                                    "
                                    @dragover.prevent
                                    @drop="onDrop(seatMap(room).get(`${row}-${col}`)!)"
                                >
                                    <div class="font-semibold">
                                        {{ seatLabel(seatMap(room).get(`${row}-${col}`)!) }}
                                    </div>
                                    <div
                                        v-if="showPhotos && seatMap(room).get(`${row}-${col}`)!.student"
                                        class="mb-1"
                                    >
                                        <StudentAvatar
                                            :photo-url="seatMap(room).get(`${row}-${col}`)!.student!.photo_url"
                                            :full-name="seatMap(room).get(`${row}-${col}`)!.student!.full_name"
                                            img-class="block h-auto w-full rounded object-contain"
                                            placeholder-class="w-full"
                                            circle-class="w-24 text-2xl"
                                        />
                                    </div>
                                    <template v-if="seatMap(room).get(`${row}-${col}`)!.student">
                                        <div class="truncate text-center">
                                            {{ seatMap(room).get(`${row}-${col}`)!.student!.school_number }}
                                            {{ seatMap(room).get(`${row}-${col}`)!.student!.full_name }}
                                        </div>
                                        <div class="text-center text-gray-500">
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

        <div v-if="salonModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[80vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Salon Değiştir</h2>
                <p class="mt-1 text-sm text-gray-500">Hedef salonda boş bir koltuk seçin.</p>
                <div v-for="entry in otherRooms" :key="entry.room.room.id" class="mt-4">
                    <h3 class="text-sm font-semibold text-gray-700">{{ entry.room.room.name }}</h3>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <button
                            v-for="seat in entry.emptySeats"
                            :key="seat.id"
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-indigo-50"
                            @click="doMove(seat.id)"
                        >
                            {{ seatLabel(seat) }}
                        </button>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button
                        type="button"
                        title="Vazgeç"
                        aria-label="Vazgeç"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        @click="salonModal = false"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="confirmState" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Kural İhlali Uyarısı</h2>
                <p class="mt-2 text-sm text-gray-600">{{ confirmState.message }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        title="Vazgeç"
                        aria-label="Vazgeç"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        @click="confirmState = null"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                        @click="confirmState!.retry()"
                    >
                        Yine de uygula
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
