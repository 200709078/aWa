<script setup lang="ts">
import { ref } from 'vue';
import StudentAvatar from './StudentAvatar.vue';
import type { TeacherFormData } from '../types/teacherForm';

const props = defineProps<{
    form: TeacherFormData;
    photoSrc: string | null;
    displayName: string;
    idPrefix: string;
}>();

const photoInput = ref<HTMLInputElement | null>(null);

function onPhotoChange(e: Event) {
    props.form.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
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
                <div>
                    <label :for="`${idPrefix}-started`" class="block text-sm font-medium text-gray-700">Göreve Başlama Tarihi</label>
                    <input
                        :id="`${idPrefix}-started`"
                        v-model="form.started_at"
                        type="date"
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label :for="`${idPrefix}-duty`" class="block text-sm font-medium text-gray-700">Görev</label>
                <input
                    :id="`${idPrefix}-duty`"
                    v-model="form.duty"
                    type="text"
                    maxlength="100"
                    placeholder="Öğretmen"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label :for="`${idPrefix}-branch`" class="block text-sm font-medium text-gray-700">Branş</label>
                <input
                    :id="`${idPrefix}-branch`"
                    v-model="form.branch"
                    type="text"
                    maxlength="100"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
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
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
        </div>
    </div>
</template>
