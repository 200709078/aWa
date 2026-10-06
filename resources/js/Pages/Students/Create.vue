<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import StudentForm from '../../Components/StudentForm.vue';
import { blankGuardian } from '../../types/studentForm';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps<{
    allBranches: { id: number; name: string }[];
    initialBranchId: number | null;
    backUrl: string;
}>();

const form = useForm({
    branch_id: props.initialBranchId,
    school_number: '',
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    address: '',
    gender: null as string | null,
    birth_place: '',
    birth_date: '',
    blood_type: null as string | null,
    religion: '',
    height_cm: null as number | null,
    weight_kg: null as number | null,
    photo: null as File | null,
    is_active: true,
    guardians: [blankGuardian('anne', true), blankGuardian('baba')],
});

const photoSrc = computed(() => {
    if (form.photo) return URL.createObjectURL(form.photo);
    return null;
});

const displayName = computed(() => `${form.first_name} ${form.last_name}`.trim());

function submit() {
    form.post('/students');
}
</script>

<template>
    <AppLayout title="Yeni Öğrenci Ekle">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-2xl font-bold text-gray-900">Yeni Öğrenci Ekle</h1>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        form="student-create-form"
                        title="Kaydet"
                        aria-label="Kaydet"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </button>
                    <Link
                        :href="backUrl"
                        title="Geri dön"
                        aria-label="Geri dön"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </Link>
                </div>
            </div>
            <form id="student-create-form" class="mt-4" @submit.prevent="submit">
                <StudentForm
                    :form="form"
                    :branches="allBranches"
                    :photo-src="photoSrc"
                    :display-name="displayName"
                    id-prefix="student"
                />
            </form>
        </div>
    </AppLayout>
</template>
