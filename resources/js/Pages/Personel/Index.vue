<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StudentAvatar from '../../Components/StudentAvatar.vue';
import NavIcon from '../../Components/NavIcon.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Teacher {
    id: number;
    first_name: string | null;
    last_name: string | null;
    full_name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    photo_path: string | null;
    photo_version: number | null;
    duty: string | null;
    branch: string | null;
    started_at: string | null;
    started_at_label: string | null;
    is_active: boolean;
}

interface Paginator {
    data: Teacher[];
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    search: string;
    teachers: Paginator;
    totalTeachers: number;
}>();

const filterSearch = ref(props.search);

function teacherTitle(teacher: Teacher): string | null {
    if (!teacher.branch) return null;
    if (teacher.duty === 'Öğretmen') return `${teacher.branch} Öğretmeni`;
    return teacher.branch;
}

function applySearch() {
    router.get('/personel', { q: filterSearch.value || undefined }, { preserveState: true });
}

function clearSearch() {
    filterSearch.value = '';
    router.get('/personel', {}, { preserveState: true });
}

const createForm = useForm({
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    address: '',
    duty: '',
    branch: '',
    started_at: '',
    photo: null as File | null,
});

function submitCreate() {
    createForm.post('/personel', {
        onSuccess: () => {
            creating.value = false;
            createForm.reset('first_name', 'last_name', 'phone', 'email', 'address', 'duty', 'branch', 'started_at', 'photo');
            if (photoInput.value) photoInput.value.value = '';
        },
    });
}

const creating = ref(false);
const photoInput = ref<HTMLInputElement | null>(null);
const editPhotoInput = ref<HTMLInputElement | null>(null);

function onPhotoChange(e: Event) {
    createForm.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function onEditPhotoChange(e: Event) {
    editForm.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
}

const editing = ref<Teacher | null>(null);

const editForm = useForm({
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    address: '',
    duty: '',
    branch: '',
    started_at: '',
    photo: null as File | null,
});

function openEdit(teacher: Teacher) {
    editing.value = teacher;
    editForm.first_name = teacher.first_name ?? '';
    editForm.last_name = teacher.last_name ?? '';
    editForm.phone = teacher.phone ?? '';
    editForm.email = teacher.email ?? '';
    editForm.address = teacher.address ?? '';
    editForm.duty = teacher.duty ?? '';
    editForm.branch = teacher.branch ?? '';
    editForm.started_at = teacher.started_at ?? '';
    editForm.photo = null;
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.transform((data) => ({ ...data, _method: 'put' })).post(`/personel/${editing.value.id}`, {
        onSuccess: () => (editing.value = null),
    });
}

function toggle(url: string) {
    router.post(url);
}

const deleting = ref<Teacher | null>(null);

function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/personel/${deleting.value.id}`, {
        onFinish: () => (deleting.value = null),
    });
}

const createPhotoSrc = computed(() => {
    if (createForm.photo) return URL.createObjectURL(createForm.photo);
    return null;
});

const editPhotoSrc = computed(() => {
    if (editForm.photo) return URL.createObjectURL(editForm.photo);
    if (editing.value?.photo_path) return `/storage/${editing.value.photo_path}?v=${editing.value.photo_version ?? 0}`;
    return null;
});

const createDisplayName = computed(() => `${createForm.first_name} ${createForm.last_name}`.trim());
const editDisplayName = computed(() => `${editForm.first_name} ${editForm.last_name}`.trim());
</script>

<template>
    <AppLayout title="Personel">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="shrink-0 text-2xl font-bold text-gray-900">Personel ({{ totalTeachers }} Kayıt)</h1>
                <div v-if="totalTeachers > 0" class="flex min-w-52 flex-1 items-center justify-center gap-2">
                    <input
                        v-model="filterSearch"
                        type="text"
                        placeholder="Ad veya telefon ara"
                        aria-label="Personel ara"
                        class="block h-9 w-64 rounded-md border-gray-300 bg-gray-50 px-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @keydown.enter.prevent="applySearch()"
                    />
                    <button
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                        @click="applySearch()"
                    >
                        Ara
                    </button>
                    <button
                        v-if="search"
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md px-2 text-sm text-gray-500 hover:text-indigo-700 hover:underline"
                        @click="clearSearch()"
                    >
                        Temizle
                    </button>
                </div>
                <div class="ml-auto flex shrink-0 gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700"
                        @click="creating = true"
                    >
                        Personel Ekle
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fotoğraf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Personel</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Telefon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="teacher in teachers.data" :key="teacher.id">
                        <td class="whitespace-nowrap px-4 py-3">
                            <StudentAvatar
                                :photo-url="teacher.photo_path ? `/storage/${teacher.photo_path}?v=${teacher.photo_version ?? 0}` : null"
                                :full-name="teacher.full_name"
                                img-class="block h-16 w-12 rounded object-cover"
                                placeholder-class="w-12"
                                circle-class="w-10 text-sm"
                            />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="font-medium text-gray-900">{{ teacher.full_name }}</div>
                            <div v-if="teacherTitle(teacher)" class="text-sm text-gray-600">{{ teacherTitle(teacher) }}</div>
                            <div v-if="teacher.duty" class="text-sm text-gray-500">{{ teacher.duty }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ teacher.phone ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="teacher.is_active"
                                    :title="teacher.is_active ? 'Pasif' : 'Aktif'"
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors',
                                        teacher.is_active ? 'bg-indigo-600' : 'bg-gray-300',
                                    ]"
                                    @click="
                                        toggle(
                                            teacher.is_active
                                                ? `/personel/${teacher.id}/deactivate`
                                                : `/personel/${teacher.id}/activate`,
                                        )
                                    "
                                >
                                    <span
                                        :class="[
                                            'mt-0.5 inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform',
                                            teacher.is_active ? 'translate-x-5 ml-0.5' : 'translate-x-0.5',
                                        ]"
                                    />
                                </button>
                                <span
                                    v-if="teacher.is_active"
                                    class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"
                                >
                                    Aktif
                                </span>
                                <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                    Pasif
                                </span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-left">
                            <div class="flex items-center justify-start gap-2">
                                <button
                                    type="button"
                                    title="Düzenle"
                                    aria-label="Düzenle"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                    @click="openEdit(teacher)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                </button>
                                <button
                                    type="button"
                                    title="Arşivle"
                                    aria-label="Arşivle"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-red-200 bg-red-50 px-4 text-sm text-red-600 shadow-sm hover:bg-red-100 hover:text-red-700 focus:border-red-500 focus:ring-red-500"
                                    @click="deleting = teacher"
                                >
                                    <NavIcon name="archive" cls="h-5 w-5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="teachers.data.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Kayıt bulunamadı.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="teachers.total > 0" class="mt-4 flex w-full max-w-[80%] items-center text-sm text-gray-600">
            <div class="flex w-24 justify-start">
                <Link
                    v-if="teachers.prev_page_url"
                    :href="teachers.prev_page_url"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                >
                    Önceki
                </Link>
            </div>
            <span class="flex-1 text-center">{{ teachers.from }}-{{ teachers.to }} / Toplam {{ teachers.total }}</span>
            <div class="flex w-24 justify-end">
                <Link
                    v-if="teachers.next_page_url"
                    :href="teachers.next_page_url"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                >
                    Sonraki
                </Link>
            </div>
        </div>

        <div v-if="creating" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitCreate">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Yeni Personel Ekle</h2>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                title="Kaydet"
                                aria-label="Kaydet"
                                :disabled="createForm.processing"
                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            </button>
                            <button
                                type="button"
                                title="Vazgeç"
                                aria-label="Vazgeç"
                                class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                                @click="creating = false"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="w-[182px] shrink-0">
                            <button
                                type="button"
                                title="Fotoğraf seç"
                                aria-label="Fotoğraf seç"
                                class="relative block h-[212px] w-[182px] overflow-hidden rounded-md border border-gray-300 bg-gray-50 hover:ring-2 hover:ring-indigo-500"
                                @click="photoInput?.click()"
                            >
                                <StudentAvatar
                                    :photo-url="createPhotoSrc"
                                    :full-name="createDisplayName"
                                    img-class="h-full w-full object-cover"
                                    placeholder-class="h-full w-full"
                                    circle-class="w-10 text-sm"
                                />
                            </button>
                            <input
                                ref="photoInput"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                class="hidden"
                                @change="onPhotoChange"
                            />
                        </div>
                        <div class="min-w-0 flex-1 space-y-4 self-start">
                            <div>
                                <label for="teacher-first" class="block text-sm font-medium text-gray-700">Ad</label>
                                <input
                                    id="teacher-first"
                                    v-model="createForm.first_name"
                                    type="text"
                                    required
                                    maxlength="50"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p v-if="createForm.errors.first_name" class="mt-1 text-sm text-red-600">
                                    {{ createForm.errors.first_name }}
                                </p>
                            </div>
                            <div>
                                <label for="teacher-last" class="block text-sm font-medium text-gray-700">Soyad</label>
                                <input
                                    id="teacher-last"
                                    v-model="createForm.last_name"
                                    type="text"
                                    required
                                    maxlength="50"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p v-if="createForm.errors.last_name" class="mt-1 text-sm text-red-600">
                                    {{ createForm.errors.last_name }}
                                </p>
                            </div>
                            <div>
                                <label for="teacher-started" class="block text-sm font-medium text-gray-700">Göreve Başlama Tarihi</label>
                                <input
                                    id="teacher-started"
                                    v-model="createForm.started_at"
                                    type="date"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="teacher-duty" class="block text-sm font-medium text-gray-700">Görev</label>
                            <input
                                id="teacher-duty"
                                v-model="createForm.duty"
                                type="text"
                                maxlength="100"
                                placeholder="Öğretmen"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="teacher-branch" class="block text-sm font-medium text-gray-700">Branş</label>
                            <input
                                id="teacher-branch"
                                v-model="createForm.branch"
                                type="text"
                                maxlength="100"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                    <p v-if="createForm.errors.photo" class="mt-1 text-sm text-red-600">{{ createForm.errors.photo }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="teacher-phone" class="block text-sm font-medium text-gray-700">Telefon</label>
                            <input
                                id="teacher-phone"
                                v-model="createForm.phone"
                                type="text"
                                maxlength="30"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="teacher-email" class="block text-sm font-medium text-gray-700">E-posta</label>
                            <input
                                id="teacher-email"
                                v-model="createForm.email"
                                type="email"
                                maxlength="100"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                    <div>
                        <label for="teacher-address" class="block text-sm font-medium text-gray-700">Adres</label>
                        <textarea
                            id="teacher-address"
                            v-model="createForm.address"
                            rows="2"
                            maxlength="500"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Personeli Düzenle</h2>
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
                                class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                                @click="editing = null"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="w-[182px] shrink-0">
                            <button
                                type="button"
                                title="Fotoğraf seç"
                                aria-label="Fotoğraf seç"
                                class="relative block h-[212px] w-[182px] overflow-hidden rounded-md border border-gray-300 bg-gray-50 hover:ring-2 hover:ring-indigo-500"
                                @click="editPhotoInput?.click()"
                            >
                                <StudentAvatar
                                    :photo-url="editPhotoSrc"
                                    :full-name="editDisplayName"
                                    img-class="h-full w-full object-cover"
                                    placeholder-class="h-full w-full"
                                    circle-class="w-10 text-sm"
                                />
                            </button>
                            <input
                                ref="editPhotoInput"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                class="hidden"
                                @change="onEditPhotoChange"
                            />
                        </div>
                        <div class="min-w-0 flex-1 space-y-4 self-start">
                            <div>
                                <label for="edit-teacher-first" class="block text-sm font-medium text-gray-700">Ad</label>
                                <input
                                    id="edit-teacher-first"
                                    v-model="editForm.first_name"
                                    type="text"
                                    required
                                    maxlength="50"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label for="edit-teacher-last" class="block text-sm font-medium text-gray-700">Soyad</label>
                                <input
                                    id="edit-teacher-last"
                                    v-model="editForm.last_name"
                                    type="text"
                                    required
                                    maxlength="50"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label for="edit-teacher-started" class="block text-sm font-medium text-gray-700">Göreve Başlama Tarihi</label>
                                <input
                                    id="edit-teacher-started"
                                    v-model="editForm.started_at"
                                    type="date"
                                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="edit-teacher-duty" class="block text-sm font-medium text-gray-700">Görev</label>
                            <input
                                id="edit-teacher-duty"
                                v-model="editForm.duty"
                                type="text"
                                maxlength="100"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="edit-teacher-branch" class="block text-sm font-medium text-gray-700">Branş</label>
                            <input
                                id="edit-teacher-branch"
                                v-model="editForm.branch"
                                type="text"
                                maxlength="100"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                    <p v-if="editForm.errors.photo" class="mt-1 text-sm text-red-600">{{ editForm.errors.photo }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="edit-teacher-phone" class="block text-sm font-medium text-gray-700">Telefon</label>
                            <input
                                id="edit-teacher-phone"
                                v-model="editForm.phone"
                                type="text"
                                maxlength="30"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="edit-teacher-email" class="block text-sm font-medium text-gray-700">E-posta</label>
                            <input
                                id="edit-teacher-email"
                                v-model="editForm.email"
                                type="email"
                                maxlength="100"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                    <div>
                        <label for="edit-teacher-address" class="block text-sm font-medium text-gray-700">Adres</label>
                        <textarea
                            id="edit-teacher-address"
                            v-model="editForm.address"
                            rows="2"
                            maxlength="500"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Personeli Arşive Gönder</h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ deleting.full_name }} arşive gönderilsin mi? Kayıt listeden gizlenir, Arşiv sayfasından geri alınabilir.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                        @click="deleting = null"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                        @click="confirmDelete"
                    >
                        Arşive Gönder
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
