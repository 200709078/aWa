<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface SeatInfo {
    id: number;
    row: number;
    column: number;
    label: string | null;
    violation: boolean;
    assignment_id: number | null;
    student: { school_number: string; full_name: string; branch: string | null; grade_level: number | null; photo_url: string | null } | null;
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
const showNumber = ref(localStorage.getItem('kelebek-show-number') !== '0');
watch(showNumber, (value) => localStorage.setItem('kelebek-show-number', value ? '1' : '0'));
const showBranch = ref(localStorage.getItem('kelebek-show-branch') !== '0');
watch(showBranch, (value) => localStorage.setItem('kelebek-show-branch', value ? '1' : '0'));

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

const schoolName = computed(() => {
    const pageProps = usePage().props as unknown as { current_school: { name: string } | null };
    return pageProps.current_school?.name ?? '';
});

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

function openSalonModal(seat: SeatInfo) {
    if (!editMode.value || busy.value || !seat.student) return;
    notice.value = null;
    selected.value = { assignmentId: seat.assignment_id!, seatId: seat.id };
    salonModal.value = true;
}

function cancelSelection() {
    if (salonModal.value) {
        salonModal.value = false;
        return;
    }
    selected.value = null;
    dragPayload.value = null;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && editMode.value) {
        cancelSelection();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

function openPrint(url: string) {
    const width = 850;
    const height = 900;
    const left = Math.max(0, Math.round((window.screen.width - width) / 2));
    const top = Math.max(0, Math.round((window.screen.height - height) / 2));
    const features = `width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,resizable=no,scrollbars=yes`;
    const win = window.open(url, 'awa-print', features);
    if (!win) window.open(url, '_blank');
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
    const groups = [...byLevel.keys()]
        .filter((level): level is number => level !== null)
        .sort((a, b) => a - b)
        .map((level) => ({ label: `${level}. Sınıf`, rows: toRows(level) }));
    if (byLevel.has(null)) {
        groups.push({ label: '—', rows: toRows(null) });
    }
    return groups;
}
</script>

<template>
    <AppLayout :title="title">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ title }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ schoolName }} · {{ week.name }}
                        <span v-if="plan.creator"> · {{ plan.creator }}</span>
                        <span v-if="plan.created_at"> · {{ plan.created_at }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        :title="editMode ? 'Düzenlemeyi Kapat' : 'Elle Düzenle'"
                        :aria-label="editMode ? 'Düzenlemeyi Kapat' : 'Elle Düzenle'"
                        :class="
                            editMode
                                ? 'inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700'
                                : 'inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500'
                        "
                        @click="toggleEdit"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-5 w-5"
                        >
                            <path d="M18 11V6a2 2 0 0 0-4 0v5" />
                            <path d="M14 10V4a2 2 0 0 0-4 0v6" />
                            <path d="M10 10.5V6a2 2 0 0 0-4 0v8" />
                            <path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15" />
                            <path v-if="editMode" d="M5 5l14 14" />
                            <path v-if="editMode" d="M19 5L5 19" />
                        </svg>
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
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="showPhotos"
                            title="Fotoğrafları Göster/Gizle"
                            :class="[
                                'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                                showPhotos ? 'bg-indigo-600' : 'bg-gray-300',
                            ]"
                            @click="showPhotos = !showPhotos"
                        >
                            <span
                                :class="[
                                    'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5',
                                    showPhotos ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                                ]"
                            />
                        </button>
                        <span class="text-sm text-gray-700">Fotoğraflar</span>
                    </div>
                    <div class="flex items-center gap-2">
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
                    <div class="flex items-center gap-2">
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
                    <Link
                        :href="`/distribution?exam_week_id=${week.id}`"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        Geri
                    </Link>
                </div>
            </div>

            <p v-if="editMode" class="mt-3 rounded-md bg-indigo-50 px-4 py-2 text-sm text-indigo-800">
                Düzenleme açık: önce bir öğrenci seçin, sonra boş koltuğa tıklayarak taşıyın veya başka öğrenciye
                tıklayarak takas edin. Sürükle-bırak da kullanabilirsiniz. Seçimi iptal etmek için Esc.
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
                {{ summaryState.violations }} koltukta aynı seviye yan yana geldi (kırmızı).
            </p>

        </div>

        <div class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-2xl font-bold text-gray-900">Yazdır</h2>
            <p class="mt-1 text-sm text-gray-500">
                Her plan için yazdırılabilir çıktılar ayrı bir pencerede açılır. Tarayıcıdan Yazdır → PDF olarak kaydet
                kullanılabilir.
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <a
                    :href="`/distribution/plans/${plan.id}/print/seating?photo=1`"
                    @click.prevent="openPrint(`/distribution/plans/${plan.id}/print/seating?photo=1`)"
                    title="İncele"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mr-1.5 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    Salon Planları
                </a>
                <a
                    :href="`/distribution/plans/${plan.id}/print/branches`"
                    @click.prevent="openPrint(`/distribution/plans/${plan.id}/print/branches`)"
                    title="İncele"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mr-1.5 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    Sınıf Listeleri
                </a>
                <a
                    :href="`/distribution/plans/${plan.id}/print/rooms`"
                    @click.prevent="openPrint(`/distribution/plans/${plan.id}/print/rooms`)"
                    title="İncele"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mr-1.5 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    Salon Listeleri
                </a>
                <a
                    :href="`/distribution/plans/${plan.id}/print/summary`"
                    @click.prevent="openPrint(`/distribution/plans/${plan.id}/print/summary`)"
                    title="İncele"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="mr-1.5 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    Dağılım Özeti
                </a>
            </div>
        </div>

        <div v-for="room in rooms" :key="room.room.id" class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">{{ room.room.name }}</h2>
            <div class="mt-3 overflow-x-auto">
            <div class="w-fit max-w-full">
                <table class="border-collapse">
                    <tbody>
                        <tr v-for="row in rowNumbers(room)" :key="row">
                            <td v-for="col in columnNumbers(room)" :key="col" class="p-1">
                                <div
                                    v-if="seatMap(room).get(`${row}-${col}`) && (seatMap(room).get(`${row}-${col}`)!.student || editMode)"
                                    class="group relative min-h-12 rounded-md px-2 py-1 text-center text-xs"
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
                                        'w-36',
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
                                    <button
                                        v-if="editMode && seatMap(room).get(`${row}-${col}`)!.student"
                                        type="button"
                                        title="Salon Değiştir"
                                        aria-label="Salon Değiştir"
                                        class="absolute right-1 top-1 rounded-md border border-gray-300 bg-white p-1 text-gray-600 opacity-0 shadow-sm transition-opacity hover:bg-indigo-100 hover:text-indigo-800 focus-visible:opacity-100 group-hover:opacity-100"
                                        @click.stop="openSalonModal(seatMap(room).get(`${row}-${col}`)!)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="block h-3.5 w-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h11c3 0 5 2 5 5" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.5 2.5 4 6l4.5 3.5" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 18H9c-3 0-5-2-5-5" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.5 14.5 20 18l-4.5 3.5" />
                                        </svg>
                                    </button>
                                    <div class="font-semibold">
                                        {{ seatLabel(seatMap(room).get(`${row}-${col}`)!) }}
                                    </div>
                                    <div
                                        v-if="seatMap(room).get(`${row}-${col}`)!.student"
                                        class="mb-1"
                                    >
                                        <StudentAvatar
                                            v-if="showPhotos"
                                            :photo-url="seatMap(room).get(`${row}-${col}`)!.student!.photo_url"
                                            :full-name="seatMap(room).get(`${row}-${col}`)!.student!.full_name"
                                            img-class="block h-auto w-full rounded object-contain"
                                            placeholder-class="w-full"
                                            circle-class="w-24 text-2xl"
                                        />
                                        <div
                                            v-else
                                            class="invisible aspect-[3/4] w-full rounded"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <template v-if="seatMap(room).get(`${row}-${col}`)!.student">
                                        <div class="truncate text-center">
                                            <template v-if="showNumber">{{ seatMap(room).get(`${row}-${col}`)!.student!.school_number }}&nbsp;</template>{{ seatMap(room).get(`${row}-${col}`)!.student!.full_name }}
                                        </div>
                                        <div v-if="showBranch" class="text-center text-gray-500">
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
            <div class="mt-4 flex flex-wrap items-start gap-4">
            <div class="flex min-w-0 flex-1 flex-wrap gap-4">
                <div v-for="group in levelTables(room)" :key="group.label">
                    <table class="w-40 divide-y divide-gray-200 border text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sınıf</th>
                                <th class="px-2 py-1 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Öğrenci</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="entry in group.rows" :key="entry.branch">
                                <td class="px-2 py-1 text-gray-900">{{ entry.branch }}</td>
                                <td class="px-2 py-1 text-right text-gray-600">{{ entry.count }}</td>
                            </tr>
                            <tr v-if="group.rows.length === 0">
                                <td colspan="2" class="px-2 py-1 text-gray-400">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
                <table class="ml-auto w-32 shrink-0 border-collapse border border-gray-300 text-sm">
                    <tbody>
                        <tr>
                            <td class="border border-gray-300 px-3 py-1.5 text-center font-semibold text-gray-900">
                                Öğretmen
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-gray-300 px-3 py-1.5 text-center font-semibold text-gray-900">
                                Masası
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>
            </div>
        </div>

        <div v-if="salonModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[80vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow">
                <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h11c3 0 5 2 5 5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.5 2.5 4 6l4.5 3.5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 18H9c-3 0-5-2-5-5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.5 14.5 20 18l-4.5 3.5" />
                    </svg>
                    Salon Değiştir
                </h2>
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
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
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
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
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
