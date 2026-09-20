<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';

interface SchoolOption {
    id: number;
    name: string;
}

interface AppUser {
    id: number;
    name: string;
    email: string;
    role: string;
    schools: SchoolOption[];
}

defineProps<{
    users: AppUser[];
    schools: SchoolOption[];
}>();

function roleLabel(role: string): string {
    return role === 'super_admin' ? 'Süper Admin' : 'Okul Yöneticisi';
}

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'school_admin',
    school_ids: [] as number[],
});

function submitCreate() {
    createForm.post('/users', {
        onSuccess: () => {
            creating.value = false;
            createForm.reset('name', 'email', 'password', 'school_ids');
        },
    });
}

const creating = ref(false);

const editing = ref<AppUser | null>(null);
const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'school_admin',
    school_ids: [] as number[],
});

function openEdit(user: AppUser) {
    editing.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.role;
    editForm.school_ids = user.schools.map((s) => s.id);
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.put(`/users/${editing.value.id}`, {
        onSuccess: () => (editing.value = null),
    });
}

const deleting = ref<AppUser | null>(null);

function askDelete(user: AppUser) {
    deleting.value = user;
}

function confirmDelete() {
    if (!deleting.value) return;
    router.delete(`/users/${deleting.value.id}`, {
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <AppLayout title="Kullanıcılar">
        <div class="flex w-full max-w-[80%] flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Kullanıcılar</h1>
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700"
                @click="creating = true"
            >
                Kullanıcı Ekle
            </button>
        </div>

        <div class="mt-6 w-full max-w-[80%] overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ad Soyad</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">E-posta</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rol</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Okullar</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="user in users" :key="user.id">
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ user.name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ user.email }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span
                                v-if="user.role === 'super_admin'"
                                class="rounded-full bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-800"
                            >
                                Süper Admin
                            </span>
                            <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                Okul Yöneticisi
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ user.role === 'super_admin' ? 'Tüm okullar' : (user.schools.map((s) => s.name).join(', ') || '—') }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-left">
                            <div class="flex justify-start gap-2">
                                <button
                                    type="button"
                                    title="Düzenle"
                                    aria-label="Düzenle"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                    @click="openEdit(user)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                </button>
                                <button
                                    type="button"
                                    title="Sil"
                                    aria-label="Sil"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-red-600 shadow-sm hover:bg-red-50 hover:text-red-700 focus:border-red-500 focus:ring-red-500"
                                    @click="askDelete(user)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="users.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Henüz kullanıcı eklenmedi.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="$page.props.errors.user" class="mt-4 rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
            {{ $page.props.errors.user }}
        </p>

        <div v-if="creating" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-sm overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitCreate">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Yeni Kullanıcı Ekle</h2>
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
                                class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                                @click="creating = false"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="user-name" class="block text-sm font-medium text-gray-700">Ad Soyad</label>
                        <input
                            id="user-name"
                            v-model="createForm.name"
                            type="text"
                            required
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="user-email" class="block text-sm font-medium text-gray-700">E-posta</label>
                        <input
                            id="user-email"
                            v-model="createForm.email"
                            type="email"
                            required
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="createForm.errors.email" class="mt-1 text-sm text-red-600">{{ createForm.errors.email }}</p>
                    </div>
                    <div>
                        <label for="user-password" class="block text-sm font-medium text-gray-700">Şifre</label>
                        <input
                            id="user-password"
                            v-model="createForm.password"
                            type="password"
                            required
                            minlength="8"
                            maxlength="100"
                            autocomplete="new-password"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="createForm.errors.password" class="mt-1 text-sm text-red-600">
                            {{ createForm.errors.password }}
                        </p>
                    </div>
                    <div>
                        <label for="user-role" class="block text-sm font-medium text-gray-700">Rol</label>
                        <div class="mt-1">
                            <DropdownSelect
                                id="user-role"
                                v-model="createForm.role"
                                :options="[{ value: 'school_admin', label: 'Okul Yöneticisi' }, { value: 'super_admin', label: 'Süper Admin' }]"
                                aria-label="Rol"
                            />
                        </div>
                    </div>
                    <div v-if="createForm.role === 'school_admin'">
                        <span class="block text-sm font-medium text-gray-700">Okullar</span>
                        <div class="mt-1 flex flex-wrap gap-3">
                            <label v-for="school in schools" :key="school.id" class="flex items-center gap-1 text-sm text-gray-700">
                                <input v-model="createForm.school_ids" type="checkbox" :value="school.id" class="rounded border-gray-300" />
                                {{ school.name }}
                            </label>
                        </div>
                        <p v-if="createForm.errors.school_ids" class="mt-1 text-sm text-red-600">
                            {{ createForm.errors.school_ids }}
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow">
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">Kullanıcıyı Düzenle</h2>
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
                        <label for="edit-user-name" class="block text-sm font-medium text-gray-700">Ad Soyad</label>
                        <input
                            id="edit-user-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label for="edit-user-email" class="block text-sm font-medium text-gray-700">E-posta</label>
                        <input
                            id="edit-user-email"
                            v-model="editForm.email"
                            type="email"
                            required
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.email" class="mt-1 text-sm text-red-600">{{ editForm.errors.email }}</p>
                    </div>
                    <div>
                        <label for="edit-user-password" class="block text-sm font-medium text-gray-700">
                            Şifre <span class="font-normal text-gray-500">(değişmeyecekse boş bırakın)</span>
                        </label>
                        <input
                            id="edit-user-password"
                            v-model="editForm.password"
                            type="password"
                            minlength="8"
                            maxlength="100"
                            autocomplete="new-password"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label for="edit-user-role" class="block text-sm font-medium text-gray-700">Rol</label>
                        <div class="mt-1">
                            <DropdownSelect
                                id="edit-user-role"
                                v-model="editForm.role"
                                :options="[{ value: 'school_admin', label: 'Okul Yöneticisi' }, { value: 'super_admin', label: 'Süper Admin' }]"
                                aria-label="Rol"
                            />
                        </div>
                        <p v-if="editForm.errors.role" class="mt-1 text-sm text-red-600">{{ editForm.errors.role }}</p>
                    </div>
                    <div v-if="editForm.role === 'school_admin'">
                        <span class="block text-sm font-medium text-gray-700">Okullar</span>
                        <div class="mt-1 flex flex-wrap gap-3">
                            <label v-for="school in schools" :key="school.id" class="flex items-center gap-1 text-sm text-gray-700">
                                <input v-model="editForm.school_ids" type="checkbox" :value="school.id" class="rounded border-gray-300" />
                                {{ school.name }}
                            </label>
                        </div>
                        <p v-if="editForm.errors.school_ids" class="mt-1 text-sm text-red-600">
                            {{ editForm.errors.school_ids }}
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Kullanıcıyı Sil</h2>
                <p class="mt-2 text-sm text-gray-600">{{ deleting.name }} ({{ deleting.email }}) silinsin mi? Bu işlem geri alınamaz.</p>
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
