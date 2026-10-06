<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import StudentForm from '../../Components/StudentForm.vue';
import { blankGuardian, type GuardianForm } from '../../types/studentForm';
import AppLayout from '../../Layouts/AppLayout.vue';

interface StudentGuardian {
    id: number;
    relation: string | null;
    first_name: string | null;
    last_name: string | null;
    phone: string | null;
    is_primary: boolean;
}

interface Student {
    id: number;
    school_number: string;
    first_name: string | null;
    last_name: string | null;
    full_name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    blood_type: string | null;
    religion: string | null;
    height_cm: number | null;
    weight_kg: number | null;
    photo_path: string | null;
    photo_version: number | null;
    is_active: boolean;
    branch: { id: number | null; name: string };
    guardians: StudentGuardian[];
}

const props = defineProps<{
    student: Student;
    allBranches: { id: number; name: string }[];
    backUrl: string;
}>();

function toGuardianForm(g: StudentGuardian): GuardianForm {
    return {
        id: g.id,
        relation: g.relation ?? 'veli',
        first_name: g.first_name ?? '',
        last_name: g.last_name ?? '',
        phone: g.phone ?? '',
        is_primary: g.is_primary,
    };
}

const form = useForm({
    branch_id: props.student.branch.id,
    school_number: props.student.school_number === '—' ? '' : props.student.school_number,
    first_name: props.student.first_name ?? '',
    last_name: props.student.last_name ?? '',
    phone: props.student.phone ?? '',
    email: props.student.email ?? '',
    address: props.student.address ?? '',
    gender: props.student.gender ?? null,
    birth_place: props.student.birth_place ?? '',
    birth_date: props.student.birth_date ?? '',
    blood_type: props.student.blood_type ?? null,
    religion: props.student.religion ?? '',
    height_cm: props.student.height_cm ?? null,
    weight_kg: props.student.weight_kg ?? null,
    photo: null as File | null,
    is_active: props.student.is_active,
    guardians: props.student.guardians.length > 0 ? props.student.guardians.map(toGuardianForm) : [blankGuardian()],
});

const photoSrc = computed(() => {
    if (form.photo) return URL.createObjectURL(form.photo);
    if (props.student.photo_path) return `/storage/${props.student.photo_path}?v=${props.student.photo_version ?? 0}`;
    return null;
});

const displayName = computed(() => `${form.first_name} ${form.last_name}`.trim() || props.student.full_name);

function submit() {
    form.transform((data) => ({ ...data, _method: 'put' })).post(`/students/${props.student.id}`);
}
</script>

<template>
    <AppLayout :title="`Öğrenciyi Düzenle — ${student.full_name}`">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-2xl font-bold text-gray-900">Öğrenciyi Düzenle — {{ student.full_name }}</h1>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        form="student-edit-form"
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
            <form id="student-edit-form" class="mt-4" @submit.prevent="submit">
                <StudentForm
                    :form="form"
                    :branches="allBranches"
                    :photo-src="photoSrc"
                    :display-name="displayName"
                    id-prefix="edit-student"
                />
            </form>
        </div>
    </AppLayout>
</template>
