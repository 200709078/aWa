<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import TeacherForm from '../../Components/TeacherForm.vue';
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
}

const props = defineProps<{
    teacher: Teacher;
    backUrl: string;
}>();

const form = useForm({
    first_name: props.teacher.first_name ?? '',
    last_name: props.teacher.last_name ?? '',
    phone: props.teacher.phone ?? '',
    email: props.teacher.email ?? '',
    address: props.teacher.address ?? '',
    duty: props.teacher.duty ?? '',
    branch: props.teacher.branch ?? '',
    started_at: props.teacher.started_at ?? '',
    photo: null as File | null,
});

const photoSrc = computed(() => {
    if (form.photo) return URL.createObjectURL(form.photo);
    if (props.teacher.photo_path) return `/storage/${props.teacher.photo_path}?v=${props.teacher.photo_version ?? 0}`;
    return null;
});

const displayName = computed(() => `${form.first_name} ${form.last_name}`.trim() || props.teacher.full_name);

function submit() {
    form.transform((data) => ({ ...data, _method: 'put' })).post(`/personel/${props.teacher.id}`);
}
</script>

<template>
    <AppLayout :title="`Personeli Düzenle — ${teacher.full_name}`">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-2xl font-bold text-gray-900">Personeli Düzenle — {{ teacher.full_name }}</h1>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        form="teacher-edit-form"
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
            <form id="teacher-edit-form" class="mt-4" @submit.prevent="submit">
                <TeacherForm :form="form" :photo-src="photoSrc" :display-name="displayName" id-prefix="edit-teacher" />
            </form>
        </div>
    </AppLayout>
</template>
