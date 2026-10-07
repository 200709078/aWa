<script setup lang="ts">
import { ref } from 'vue';
import StudentAvatar from './StudentAvatar.vue';
import DropdownSelect from './DropdownSelect.vue';
import { bloodTypeOptions, digerRelationOptions, genderOptions, type StudentFormData } from '../types/studentForm';

const props = defineProps<{
    form: StudentFormData;
    branches: { id: number; name: string }[];
    photoSrc: string | null;
    displayName: string;
    idPrefix: string;
}>();

// Üç bloktan yalnız biri birincil olabilir.
const found = props.form.guardians.findIndex((g) => g.is_primary);
const primaryIndex = ref(found >= 0 ? found : 0);

function applyPrimary() {
    props.form.guardians.forEach((g, idx) => {
        g.is_primary = idx === primaryIndex.value;
    });
}

applyPrimary();

const photoInput = ref<HTMLInputElement | null>(null);

function onPhotoChange(e: Event) {
    props.form.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
}

function guardianTitle(relation: string): string {
    if (relation === 'anne') return 'Anne Bilgileri';
    if (relation === 'baba') return 'Baba Bilgileri';
    return 'Diğer Veli Bilgileri';
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
                <div class="grid grid-cols-2 gap-3">
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
            </div>
        </div>
        <p v-if="form.errors.photo" class="mt-1 text-sm text-red-600">{{ form.errors.photo }}</p>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <span class="block text-sm font-medium text-gray-700">Cinsiyet</span>
                <div class="mt-1">
                    <DropdownSelect
                        :id="`${idPrefix}-gender`"
                        v-model="form.gender"
                        :options="genderOptions"
                        aria-label="Cinsiyet"
                    />
                </div>
                <p v-if="form.errors.gender" class="mt-1 text-sm text-red-600">{{ form.errors.gender }}</p>
            </div>
            <div>
                <label :for="`${idPrefix}-birth-place`" class="block text-sm font-medium text-gray-700">Doğum Yeri</label>
                <input
                    :id="`${idPrefix}-birth-place`"
                    v-model="form.birth_place"
                    type="text"
                    maxlength="100"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label :for="`${idPrefix}-birth-date`" class="block text-sm font-medium text-gray-700">Doğum Tarihi</label>
                <input
                    :id="`${idPrefix}-birth-date`"
                    v-model="form.birth_date"
                    type="date"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
        </div>
        <div class="grid grid-cols-4 gap-3">
            <div>
                <span class="block text-sm font-medium text-gray-700">Kan Grubu</span>
                <div class="mt-1">
                    <DropdownSelect
                        :id="`${idPrefix}-blood`"
                        v-model="form.blood_type"
                        :options="bloodTypeOptions"
                        aria-label="Kan Grubu"
                    />
                </div>
            </div>
            <div>
                <label :for="`${idPrefix}-religion`" class="block text-sm font-medium text-gray-700">Din</label>
                <input
                    :id="`${idPrefix}-religion`"
                    v-model="form.religion"
                    type="text"
                    maxlength="50"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label :for="`${idPrefix}-height`" class="block text-sm font-medium text-gray-700">Boy (cm)</label>
                <input
                    :id="`${idPrefix}-height`"
                    v-model.number="form.height_cm"
                    type="number"
                    min="50"
                    max="250"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label :for="`${idPrefix}-weight`" class="block text-sm font-medium text-gray-700">Kilo (kg)</label>
                <input
                    :id="`${idPrefix}-weight`"
                    v-model.number="form.weight_kg"
                    type="number"
                    min="15"
                    max="300"
                    class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
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
            </div>
            <div
                v-for="(g, i) in form.guardians"
                :key="g.id ?? `yeni-${i}`"
                class="mt-2 space-y-3 rounded-md border border-gray-200 p-3"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="block shrink-0 text-sm font-medium text-gray-700">{{ guardianTitle(g.relation) }}</span>
                    <div v-if="g.relation !== 'anne' && g.relation !== 'baba'" class="w-32">
                        <DropdownSelect
                            :id="`${idPrefix}-guardian-rel-${i}`"
                            v-model="g.relation"
                            :options="digerRelationOptions"
                            aria-label="Yakınlık"
                        />
                    </div>
                    <label class="flex shrink-0 items-center gap-2 text-sm text-gray-700">
                        <input
                            v-model="primaryIndex"
                            :value="i"
                            :name="`primary-${idPrefix}`"
                            type="radio"
                            class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            @change="applyPrimary()"
                        />
                        Birincil
                    </label>
                </div>
                <div class="grid grid-cols-3 gap-3">
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
                    <div>
                        <span class="block text-sm font-medium text-gray-700">Telefon</span>
                        <input
                            v-model="g.phone"
                            type="text"
                            maxlength="30"
                            class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
            </div>
            <p v-if="form.errors.guardians" class="mt-1 text-sm text-red-600">{{ form.errors.guardians }}</p>
        </div>
    </div>
</template>
