<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

interface School {
    id: number;
    name: string;
    kurum_kodu: string;
    mail: string | null;
    telefon: string | null;
    mudur: string | null;
    muduryrd: string | null;
    is_active: boolean;
    users_count: number;
    academic_years_count: number;
    rooms_count: number;
}

defineProps<{ schools: School[] }>();

const createForm = useForm({
    name: '',
    kurum_kodu: '',
    mail: '',
    telefon: '',
    mudur: '',
    muduryrd: '',
    is_active: true,
});

function submitCreate() {
    createForm.post('/schools', {
        onSuccess: () => createForm.reset('name', 'kurum_kodu', 'mail', 'telefon', 'mudur', 'muduryrd'),
    });
}

const editing = ref<School | null>(null);
const editForm = useForm({
    name: '',
    kurum_kodu: '',
    mail: '',
    telefon: '',
    mudur: '',
    muduryrd: '',
    is_active: true,
});

function openEdit(school: School) {
    editing.value = school;
    editForm.name = school.name;
    editForm.kurum_kodu = school.kurum_kodu;
    editForm.mail = school.mail ?? '';
    editForm.telefon = school.telefon ?? '';
    editForm.mudur = school.mudur ?? '';
    editForm.muduryrd = school.muduryrd ?? '';
    editForm.is_active = school.is_active;
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.put(`/schools/${editing.value.id}`, {
        onSuccess: () => (editing.value = null),
    });
}

const deleting = ref<School | null>(null);

function isUsed(school: School): boolean {
    return school.users_count > 0 || school.academic_years_count > 0 || school.rooms_count > 0;
}

function askDelete(school: School) {
    deleting.value = school;
}

function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/schools/${deleting.value.id}`, {
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <AppLayout title="Okullar">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Okullar</h1>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Okul</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Kurum Kodu</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İletişim</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Müdür</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Müdür Yrd.</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Durum</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="school in schools" :key="school.id">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ school.name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ school.kurum_kodu }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            <div>{{ school.mail ?? '—' }}</div>
                            <div>{{ school.telefon ?? '—' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            {{ school.mudur ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            {{ school.muduryrd ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span
                                v-if="school.is_active"
                                class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"
                            >
                                Aktif
                            </span>
                            <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                Pasif
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-left">
                            <div class="flex justify-start gap-2">
                                <button
                                    type="button"
                                    title="Düzenle"
                                    aria-label="Düzenle"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                    @click="openEdit(school)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                </button>
                                <button
                                    type="button"
                                    :title="isUsed(school) ? 'Kullanılan okul silinemez' : 'Sil'"
                                    aria-label="Sil"
                                    :disabled="isUsed(school)"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-red-600 shadow-sm hover:bg-red-50 hover:text-red-700 focus:border-red-500 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="askDelete(school)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="schools.length === 0">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">Henüz okul eklenmedi.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="$page.props.errors.school" class="mt-4 rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
            {{ $page.props.errors.school }}
        </p>

        <div class="mt-6 w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Yeni Okul Ekle</h2>

            <form class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4" @submit.prevent="submitCreate">
                <div class="col-span-2">
                    <label for="school-name" class="block text-sm font-medium text-gray-700">Okul Adı</label>
                    <input
                        id="school-name"
                        v-model="createForm.name"
                        type="text"
                        required
                        maxlength="100"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <label for="school-code" class="block text-sm font-medium text-gray-700">Kurum Kodu</label>
                    <input
                        id="school-code"
                        v-model="createForm.kurum_kodu"
                        type="text"
                        required
                        maxlength="20"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.kurum_kodu" class="mt-1 text-sm text-red-600">
                        {{ createForm.errors.kurum_kodu }}
                    </p>
                </div>
                <div>
                    <label for="school-phone" class="block text-sm font-medium text-gray-700">Telefon</label>
                    <input
                        id="school-phone"
                        v-model="createForm.telefon"
                        type="text"
                        maxlength="30"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div class="col-span-2">
                    <label for="school-mail" class="block text-sm font-medium text-gray-700">E-posta</label>
                    <input
                        id="school-mail"
                        v-model="createForm.mail"
                        type="email"
                        maxlength="100"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="createForm.errors.mail" class="mt-1 text-sm text-red-600">{{ createForm.errors.mail }}</p>
                </div>
                <div>
                    <label for="school-manager" class="block text-sm font-medium text-gray-700">Müdür</label>
                    <input
                        id="school-manager"
                        v-model="createForm.mudur"
                        type="text"
                        maxlength="100"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="school-vice" class="block text-sm font-medium text-gray-700">Müdür Yardımcısı</label>
                    <input
                        id="school-vice"
                        v-model="createForm.muduryrd"
                        type="text"
                        maxlength="100"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex items-end">
                    <button
                        type="submit"
                        title="Ekle"
                        aria-label="Ekle"
                        :disabled="createForm.processing"
                        class="inline-flex h-9 w-full items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </button>
                </div>
            </form>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Okulu Düzenle</h2>
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
                                class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                @click="editing = null"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="edit-school-name" class="block text-sm font-medium text-gray-700">Okul Adı</label>
                        <input
                            id="edit-school-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="edit-school-code" class="block text-sm font-medium text-gray-700">Kurum Kodu</label>
                            <input
                                id="edit-school-code"
                                v-model="editForm.kurum_kodu"
                                type="text"
                                required
                                maxlength="20"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p v-if="editForm.errors.kurum_kodu" class="mt-1 text-sm text-red-600">
                                {{ editForm.errors.kurum_kodu }}
                            </p>
                        </div>
                        <div>
                            <label for="edit-school-phone" class="block text-sm font-medium text-gray-700">Telefon</label>
                            <input
                                id="edit-school-phone"
                                v-model="editForm.telefon"
                                type="text"
                                maxlength="30"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                    <div>
                        <label for="edit-school-mail" class="block text-sm font-medium text-gray-700">E-posta</label>
                        <input
                            id="edit-school-mail"
                            v-model="editForm.mail"
                            type="email"
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="edit-school-manager" class="block text-sm font-medium text-gray-700">Müdür</label>
                            <input
                                id="edit-school-manager"
                                v-model="editForm.mudur"
                                type="text"
                                maxlength="100"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label for="edit-school-vice" class="block text-sm font-medium text-gray-700">Müdür Yardımcısı</label>
                            <input
                                id="edit-school-vice"
                                v-model="editForm.muduryrd"
                                type="text"
                                maxlength="100"
                                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300" />
                        Aktif
                    </label>
                </form>
            </div>
        </div>

        <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Okulu Sil</h2>
                <p class="mt-2 text-sm text-gray-600">{{ deleting.name }} silinsin mi? Bu işlem geri alınamaz.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                        @click="deleting = null"
                    >
                        Vazgeç
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-red-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-red-700"
                        @click="confirmDelete"
                    >
                        Sil
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
