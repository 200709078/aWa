<script setup lang="ts">
import { ref } from 'vue';
import StudentAvatar from './StudentAvatar.vue';
import { blankEduRow, type GraduateFormData } from '../types/graduateForm';

const props = defineProps<{
    form: GraduateFormData;
    photoSrc: string | null;
    displayName: string;
    idPrefix: string;
}>();

const photoInput = ref<HTMLInputElement | null>(null);

function onPhotoChange(e: Event) {
    props.form.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function addEducationRow() {
    props.form.educations.push(blankEduRow());
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex gap-3">
            <button
                type="button"
                title="Fotoğraf seç"
                aria-label="Fotoğraf seç"
                class="relative block h-[212px] w-[182px] shrink-0 overflow-hidden rounded-md border border-gray-300 bg-gray-50 hover:ring-2 hover:ring-indigo-500"
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
            <div class="min-w-0 flex-1 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label :for="`${idPrefix}-number`" class="block text-sm font-medium text-gray-700">Mezuniyet No</label>
                        <input
                            :id="`${idPrefix}-number`"
                            v-model="form.graduation_number"
                            type="text"
                            required
                            maxlength="20"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.graduation_number" class="mt-1 text-sm text-red-600">
                            {{ form.errors.graduation_number }}
                        </p>
                    </div>
                    <div>
                        <label :for="`${idPrefix}-year`" class="block text-sm font-medium text-gray-700">Mezuniyet Yılı</label>
                        <input
                            :id="`${idPrefix}-year`"
                            v-model.number="form.graduation_year"
                            type="number"
                            required
                            min="1900"
                            max="2100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.graduation_year" class="mt-1 text-sm text-red-600">
                            {{ form.errors.graduation_year }}
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
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                        class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.last_name }}
                    </p>
                </div>
            </div>
        </div>
        <p v-if="form.errors.photo" class="mt-1 text-sm text-red-600">
            {{ form.errors.photo }}
        </p>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label :for="`${idPrefix}-phone`" class="block text-sm font-medium text-gray-700">Telefon</label>
                <input
                    :id="`${idPrefix}-phone`"
                    v-model="form.phone"
                    type="text"
                    maxlength="30"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
            <div class="flex items-center justify-between">
                <span class="block text-sm font-medium text-gray-700">Eğitim</span>
                <button
                    type="button"
                    class="inline-flex h-7 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-3 text-xs text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                    @click="addEducationRow()"
                >
                    + Satır Ekle
                </button>
            </div>
            <div
                v-for="(row, i) in form.educations"
                :key="row.id ?? `yeni-${i}`"
                class="mt-2 space-y-3 rounded-md border border-gray-200 p-3"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label :for="`${idPrefix}-ecity-${i}`" class="block text-sm font-medium text-gray-700">Şehir</label>
                        <input
                            :id="`${idPrefix}-ecity-${i}`"
                            v-model="row.city"
                            type="text"
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label :for="`${idPrefix}-einst-${i}`" class="block text-sm font-medium text-gray-700">Üniversite / Okul</label>
                        <input
                            :id="`${idPrefix}-einst-${i}`"
                            v-model="row.institution_name"
                            type="text"
                            required
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label :for="`${idPrefix}-efac-${i}`" class="block text-sm font-medium text-gray-700">Fakülte</label>
                        <input
                            :id="`${idPrefix}-efac-${i}`"
                            v-model="row.faculty"
                            type="text"
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label :for="`${idPrefix}-edep-${i}`" class="block text-sm font-medium text-gray-700">Bölüm</label>
                        <input
                            :id="`${idPrefix}-edep-${i}`"
                            v-model="row.department"
                            type="text"
                            maxlength="100"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
                <div class="flex justify-end">
                    <button
                        type="button"
                        class="text-xs text-red-600 hover:underline"
                        @click="form.educations.splice(i, 1)"
                    >
                        Satırı sil
                    </button>
                </div>
            </div>
            <p v-if="form.errors.educations" class="mt-1 text-sm text-red-600">
                {{ form.errors.educations }}
            </p>
        </div>
        <div>
            <label :for="`${idPrefix}-city`" class="block text-sm font-medium text-gray-700">Şehir</label>
            <input
                :id="`${idPrefix}-city`"
                v-model="form.job_city"
                type="text"
                maxlength="100"
                class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
        </div>
        <div>
            <label :for="`${idPrefix}-company`" class="block text-sm font-medium text-gray-700">İşyeri</label>
            <textarea
                :id="`${idPrefix}-company`"
                v-model="form.company"
                rows="2"
                maxlength="100"
                class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            ></textarea>
        </div>
    </div>
</template>
