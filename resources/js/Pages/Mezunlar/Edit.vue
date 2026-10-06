<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import GraduateForm from '../../Components/GraduateForm.vue';
import { blankEduRow, type EduRow } from '../../types/graduateForm';
import AppLayout from '../../Layouts/AppLayout.vue';

interface Graduate {
    id: number;
    year: number;
    number: string;
    full_name: string;
    first_name: string | null;
    last_name: string | null;
    phone: string | null;
    email: string | null;
    photo_path: string | null;
    photo_version: number | null;
    education_rows: EduRow[];
    company: string | null;
    job_city: string | null;
}

const props = defineProps<{
    graduate: Graduate;
    backUrl: string;
}>();

const form = useForm({
    graduation_year: props.graduate.year,
    graduation_number: props.graduate.number,
    first_name: props.graduate.first_name ?? '',
    last_name: props.graduate.last_name ?? '',
    phone: props.graduate.phone ?? '',
    email: props.graduate.email ?? '',
    educations: (props.graduate.education_rows.length > 0
        ? props.graduate.education_rows.map((row) => ({ ...row }))
        : [blankEduRow()]) as EduRow[],
    company: props.graduate.company ?? '',
    job_city: props.graduate.job_city ?? '',
    photo: null as File | null,
});

const photoSrc = computed(() => {
    if (form.photo) return URL.createObjectURL(form.photo);
    if (props.graduate.photo_path) return `/storage/${props.graduate.photo_path}?v=${props.graduate.photo_version ?? 0}`;
    return null;
});

const displayName = computed(() => `${form.first_name} ${form.last_name}`.trim() || props.graduate.full_name);

function submit() {
    form.transform((data) => ({ ...data, _method: 'put' })).post(`/mezunlar/${props.graduate.id}`);
}
</script>

<template>
    <AppLayout :title="`Mezunu Düzenle — ${graduate.full_name}`">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-2xl font-bold text-gray-900">Mezunu Düzenle — {{ graduate.full_name }}</h1>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        form="graduate-edit-form"
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
            <form id="graduate-edit-form" class="mt-4" @submit.prevent="submit">
                <GraduateForm :form="form" :photo-src="photoSrc" :display-name="displayName" id-prefix="edit-graduate" />
            </form>
        </div>
    </AppLayout>
</template>
