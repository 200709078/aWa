<script setup lang="ts">
import { ref } from 'vue';
import StudentAvatar from './StudentAvatar.vue';
import DropdownSelect from './DropdownSelect.vue';
import { blankGuardian, relationOptions, type StudentFormData } from '../types/studentForm';

const props = defineProps<{
    form: StudentFormData;
    branches: { id: number; name: string }[];
    photoSrc: string | null;
    displayName: string;
    idPrefix: string;
}>();

const photoInput = ref<HTMLInputElement | null>(null);

function onPhotoChange(e: Event) {
    props.form.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function addGuardian() {
    if (props.form.guardians.length < 4) props.form.guardians.push(blankGuardian());
}
</script>

<template>
    <div class="space-y-4">
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
                        :photo-url="photoSrc"
                        :full-name="displayName"
                        img-class="h-full w-full object-cover"
                        placeholder-class="h-full w-full"
                        circle-class="w-10 text-sm"
                    />
                </button>
                <input
                    :id="`${idPrefix}-photo`"
                    ref="photoInput"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp"
                    class="hidden"
                    @change="onPhotoChange"
                />
            </div>
            <div class="min-w-0 flex-1 space-y-4 self-start">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label :for="`${idPrefix}-number`" class="block text-sm font-medium text-gray-700">Okul No</label>
                        <input
                            :id="`${idPrefix}-number`"
                            v-model="form.school_number"
                            type="text"
                            required
                            maxlength="20"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.school_number" class="mt-1 text-sm text-red-600">
                            {{ form.errors.school_number }}
                        </p>
                    </div>
                    <div>
                        <label :for="`${idPrefix}-branch`" class="block text-sm font-medium text-gray-700">Sınıf</label>
                        <div class="mt-1">
                            <DropdownSelect
                                :id="`${idPrefix}-branch`"
                                v-model="form.branch_id"
                                :options="branches.map((branch) => ({ value: branch.id, label: branch.name }))"
                                aria-label="Sınıf"
                            />
                        </div>
                        <p v-if="form.errors.branch_id" class="mt-1 text-sm text-red-600">
                            {{ form.errors.branch_id }}
                        </p>
                    </div>
                </div>
                <div>
                    <label :for="`${idPrefix}-first`" class="block text-sm font-medium text-gray-700">Ad</label>
                    <input
                        :id="`${idPrefix}-first`"
                        v-model="form.first_name"
                        type="text"
                        required
                        maxlength="50"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.first_name }}
                    </p>
                </div>
                <div>
                    <label :for="`${idPrefix}-last`" class="block text-sm font-medium text-gray-700">Soyad</label>
                    <input
                        :id="`${idPrefix}-last`"
                        v-model="form.last_name"
                        type="text"
                        required
                        maxlength="50"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.last_name }}
                    </p>
                </div>
            </div>
        </div>
        <p v-if="form.errors.photo" class="mt-1 text-sm text-red-600">{{ form.errors.photo }}</p>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label :for="`${idPrefix}-phone`" class="block text-sm font-medium text-gray-700">Telefon</label>
                <input
                    :id="`${idPrefix}-phone`"
                    v-model="form.phone"
                    type="text"
                    maxlength="30"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
            </div>
            <div>
                <label :for="`${idPrefix}-email`" class="block text-sm font-medium text-gray-700">E-posta</label>
                <input
                    :id="`${idPrefix}-email`"
                    v-model="form.email"
                    type="email"
                    maxlength="100"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>
        </div>
        <div>
            <label :for="`${idPrefix}-address`" class="block text-sm font-medium text-gray-700">Adres</label>
            <textarea
                :id="`${idPrefix}-address`"
                v-model="form.address"
                rows="2"
                maxlength="500"
                class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            ></textarea>
            <p v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</p>
        </div>
        <div>
            <div class="flex items-center justify-between">
                <span class="block text-sm font-medium text-gray-700">Veliler</span>
                <button
                    v-if="form.guardians.length < 4"
                    type="button"
                    class="inline-flex h-7 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-3 text-xs text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                    @click="addGuardian()"
                >
                    + Veli Ekle
                </button>
            </div>
            <div
                v-for="(g, i) in form.guardians"
                :key="g.id ?? `yeni-${i}`"
                class="mt-2 space-y-3 rounded-md border border-gray-200 p-3"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="block text-sm font-medium text-gray-700">Yakınlık</span>
                        <div class="mt-1">
                            <DropdownSelect
                                :id="`${idPrefix}-guardian-rel-${i}`"
                                v-model="g.relation"
                                :options="relationOptions"
                                aria-label="Yakınlık"
                            />
                        </div>
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm text-gray-700">
                        <input v-model="g.is_primary" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                        Birincil Veli
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="block text-sm font-medium text-gray-700">Ad</span>
                        <input
                            v-model="g.first_name"
                            type="text"
                            maxlength="50"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-700">Soyad</span>
                        <input
                            v-model="g.last_name"
                            type="text"
                            maxlength="50"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-700">Telefon</span>
                    <input
                        v-model="g.phone"
                        type="text"
                        maxlength="30"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <div class="flex justify-end">
                    <button
                        type="button"
                        class="text-xs text-red-600 hover:underline"
                        @click="form.guardians.splice(i, 1)"
                    >
                        Veliyi kaldır
                    </button>
                </div>
            </div>
            <p v-if="form.errors.guardians" class="mt-1 text-sm text-red-600">{{ form.errors.guardians }}</p>
        </div>
    </div>
</template>
