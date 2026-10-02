<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';

interface Year {
    id: number;
    name: string;
    is_active: boolean;
}

interface FailedFile {
    filename: string;
    message: string;
}

interface MatchFile {
    filename: string;
    matched: boolean;
    school_number?: string;
    full_name?: string | null;
}

interface MatchPreview {
    token: string;
    files: MatchFile[];
}

interface BareStudent {
    school_number: string;
    full_name: string;
}

interface BatchPayload {
    year: { id: number; name: string } | null;
    summary: {
        eslesen: number;
        eslesmeyen: number;
        fotografsiz: number;
        hatali: number;
    };
    unmatched: string[];
    failed: FailedFile[];
    withoutPhoto: BareStudent[];
    withoutPhotoTruncated: boolean;
}

const props = defineProps<{
    years: Year[];
    activeYearId: number | null;
}>();

// Sunucu varsayılan PHP limitlerinin (post_max_size=8M, max_file_uploads=20)
// altında kalmak için partiler küçük tutulur.
const BATCH_MAX_COUNT = 15;
const BATCH_MAX_BYTES = 6 * 1024 * 1024;
// Onayda her istek bu kadar fotoğraf işler (PHP süre limitine takılmamak için).
const CONFIRM_CHUNK_COUNT = 10;

const academicYearId = ref<number | null>(props.activeYearId ?? null);
const photoFiles = ref<File[]>([]);
const photoInput = ref<HTMLInputElement | null>(null);

function openPhotoDialog() {
    photoInput.value?.click();
}

function onPhotoChange(e: Event) {
    photoFiles.value = Array.from((e.target as HTMLInputElement).files ?? []);
}

const uploading = ref(false);
const doneCount = ref(0);
const totalCount = ref(0);
const statusText = ref('');
const errorMessages = ref<string[]>([]);
const preview = ref<MatchPreview | null>(null);
const result = ref<BatchPayload | null>(null);

const progressPct = computed(() => {
    if (totalCount.value === 0) return 0;
    return Math.round((doneCount.value / totalCount.value) * 100);
});

const matchedFiles = computed(() => (preview.value?.files ?? []).filter((f) => f.matched));
const unmatchedFiles = computed(() => (preview.value?.files ?? []).filter((f) => !f.matched));

function resetPreview() {
    preview.value = null;
}

watch([photoFiles, academicYearId], resetPreview);

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function chunkFiles(files: File[]): File[][] {
    const chunks: File[][] = [];
    let current: File[] = [];
    let currentBytes = 0;

    for (const file of files) {
        const wouldExceedCount = current.length >= BATCH_MAX_COUNT;
        const wouldExceedBytes = current.length > 0 && currentBytes + file.size > BATCH_MAX_BYTES;
        if (wouldExceedCount || wouldExceedBytes) {
            chunks.push(current);
            current = [];
            currentBytes = 0;
        }
        current.push(file);
        currentBytes += file.size;
    }
    if (current.length > 0) chunks.push(current);
    return chunks;
}

function extractMessages(data: unknown, fallback: string): string[] {
    if (data && typeof data === 'object') {
        const obj = data as { message?: unknown; errors?: unknown };
        if (obj.errors && typeof obj.errors === 'object') {
            const list = Object.values(obj.errors as Record<string, unknown>).flatMap((v) =>
                Array.isArray(v) ? v.map(String) : [String(v)],
            );
            if (list.length > 0) return list.slice(0, 10);
        }
        if (typeof obj.message === 'string' && obj.message.length > 0) return [obj.message];
    }
    return [fallback];
}

async function postBatch(url: string, formData: FormData): Promise<unknown> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Batch-Upload': '1',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: formData,
    });

    if (response.status === 413) {
        let data: unknown = null;
        try {
            data = await response.json();
        } catch {
            data = null;
        }
        throw new Error(
            extractMessages(
                data,
                'Dosyalar çok büyük. Daha az dosya seçip tekrar deneyin.',
            )[0],
        );
    }

    if (response.status === 419) {
        throw new Error('Oturum süresi doldu. Sayfayı yenileyip tekrar giriş yapın.');
    }

    if (!response.ok) {
        let data: unknown = null;
        try {
            data = await response.json();
        } catch {
            data = null;
        }
        throw new Error(extractMessages(data, 'Yükleme başarısız. Tekrar deneyin.')[0]);
    }

    return (await response.json()) as unknown;
}

interface MatchResponse {
    token: string;
    files: MatchFile[];
}

async function submit() {
    if (preview.value) {
        await confirmUpload();
        return;
    }
    await matchFiles();
}

async function matchFiles() {
    errorMessages.value = [];
    result.value = null;
    preview.value = null;

    if (!academicYearId.value) {
        errorMessages.value = ['Akademik Yıl seçin.'];
        return;
    }
    if (photoFiles.value.length === 0) {
        errorMessages.value = ['Fotoğraf dosyası seçin.'];
        return;
    }

    uploading.value = true;
    doneCount.value = 0;
    totalCount.value = photoFiles.value.length;
    statusText.value = 'Hazırlanıyor…';

    try {
        const token = crypto.randomUUID();
        const files: MatchFile[] = [];
        const chunks = chunkFiles(photoFiles.value);

        for (let i = 0; i < chunks.length; i++) {
            statusText.value = 'Fotoğraflar eşleştiriliyor';
            const fd = new FormData();
            fd.append('academic_year_id', String(academicYearId.value));
            fd.append('token', token);
            for (const file of chunks[i]) {
                fd.append('photos[]', file);
            }
            const data = (await postBatch('/students/photos/match', fd)) as MatchResponse;
            files.push(...data.files);
            doneCount.value += chunks[i].length;
        }

        preview.value = { token, files };
        statusText.value = 'Eşleşme tamamlandı.';
    } catch (e) {
        errorMessages.value = [e instanceof Error ? e.message : 'Eşleştirme başarısız.'];
    } finally {
        uploading.value = false;
    }
}

async function confirmUpload() {
    if (!preview.value || !academicYearId.value) return;

    errorMessages.value = [];
    result.value = null;
    uploading.value = true;

    try {
        const token = preview.value.token;
        const names = preview.value.files.map((f) => f.filename);
        const total = names.length;
        doneCount.value = 0;
        totalCount.value = total;

        let acc: BatchPayload | null = null;
        for (let i = 0; i < names.length; i += CONFIRM_CHUNK_COUNT) {
            const chunk = names.slice(i, i + CONFIRM_CHUNK_COUNT);
            statusText.value = 'Fotoğraflar kaydediliyor';
            const fd = new FormData();
            fd.append('academic_year_id', String(academicYearId.value));
            fd.append('token', token);
            for (const name of chunk) fd.append('filenames[]', name);
            const data = (await postBatch('/students/photos/confirm', fd)) as BatchPayload;
            acc = acc === null ? data : {
                ...data,
                summary: {
                    eslesen: acc.summary.eslesen + data.summary.eslesen,
                    eslesmeyen: acc.summary.eslesmeyen + data.summary.eslesmeyen,
                    fotografsiz: data.summary.fotografsiz,
                    hatali: acc.summary.hatali + data.summary.hatali,
                },
                unmatched: [...acc.unmatched, ...data.unmatched],
                failed: [...acc.failed, ...data.failed],
            };
            doneCount.value = Math.min(i + CONFIRM_CHUNK_COUNT, total);
        }

        result.value = acc;
        preview.value = null;
        photoFiles.value = [];
        statusText.value = 'Tamamlandı.';
        window.dispatchEvent(
            new CustomEvent('kelebek-toast', {
                detail: { type: 'success', messages: [`Yükleme tamamlandı — ${acc?.summary.eslesen ?? 0} fotoğraf kaydedildi.`] },
            }),
        );
        requestAnimationFrame(() => {
            document.getElementById('photo-result')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    } catch (e) {
        errorMessages.value = [e instanceof Error ? e.message : 'Yükleme başarısız.'];
    } finally {
        uploading.value = false;
    }
}
</script>

<template>
    <AppLayout title="Toplu Fotoğraf Yükle">
        <div class="max-w-xl rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Toplu Fotoğraf Yükle</h1>
            <p class="mt-2 text-sm text-gray-600">
                Dosya adı okul numarası olmalı (örn. 145.jpg). Büyük fotoğraflar otomatik küçültülür.
                Birden fazla dosya seçebilirsiniz. Önce Eşleştir ile önizleyin, sonra Yükle ile kaydedin.
                Çok sayıda dosya otomatik olarak küçük gruplar halinde yüklenir.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="photo-year" class="block text-sm font-medium text-gray-700">Akademik Yıl</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="photo-year"
                            v-model="academicYearId"
                            :disabled="uploading"
                            :options="[{ value: null, label: 'Seçin' }, ...years.map((year) => ({ value: year.id, label: `${year.name}${year.is_active ? ' (aktif)' : ''}` }))]"
                            aria-label="Akademik Yıl"
                        />
                    </div>
                </div>

                <div>
                    <label for="photo-files" class="block text-sm font-medium text-gray-700">
                        Fotoğraflar (jpg, jpeg, png, webp, bmp)
                    </label>
                    <input
                        id="photo-files"
                        ref="photoInput"
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp,.bmp"
                        class="hidden"
                        @change="onPhotoChange"
                    />
                    <button
                        type="button"
                        :disabled="uploading"
                        class="mt-1 flex h-9 w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-gray-50 px-3 text-sm text-gray-700 shadow-sm hover:bg-indigo-50 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="openPhotoDialog"
                    >
                        <span class="truncate">{{
                            photoFiles.length > 0 ? `${photoFiles.length} dosya seçildi` : 'Fotoğraf seçin (jpg, jpeg, png, webp, bmp)'
                        }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    </button>
                </div>

                <div v-if="errorMessages.length > 0" class="rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                    <p v-for="(message, i) in errorMessages" :key="i">{{ message }}</p>
                </div>

                <div v-if="uploading" class="space-y-1">
                    <div class="h-2 overflow-hidden rounded bg-gray-200">
                        <div class="h-full bg-indigo-600" :style="{ width: `${progressPct}%` }"></div>
                    </div>
                    <p class="text-sm text-gray-600">{{ statusText }}: {{ doneCount }}/{{ totalCount }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="uploading || (photoFiles.length === 0 && !preview)"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500 disabled:opacity-100"
                >
                    {{ uploading ? 'Yükleniyor…' : preview ? 'Yükle' : 'Eşleştir' }}
                </button>
            </form>
        </div>

        <div v-if="preview" class="mt-6 max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-gray-900">Eşleşme Önizleme</h2>
            <p class="mt-1 text-sm text-gray-600">Henüz kaydedilmedi. Yükle ile kaydedin.</p>

            <dl class="mt-4 divide-y divide-gray-200">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Eşleşen Fotoğraf</dt>
                    <dd class="font-semibold text-green-700">{{ matchedFiles.length }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Eşleşmeyen Dosya</dt>
                    <dd class="font-semibold text-yellow-700">{{ unmatchedFiles.length }}</dd>
                </div>
            </dl>

            <div v-if="matchedFiles.length > 0" class="mt-4">
                <h3 class="font-semibold text-gray-900">Eşleşenler</h3>
                <ul class="mt-2 space-y-1 text-sm text-gray-600">
                    <li v-for="file in matchedFiles" :key="file.filename">
                        {{ file.filename }} — {{ file.school_number }} / {{ file.full_name }}
                    </li>
                </ul>
            </div>

            <div v-if="unmatchedFiles.length > 0" class="mt-4">
                <h3 class="font-semibold text-gray-900">Eşleşmeyenler</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li v-for="file in unmatchedFiles" :key="file.filename">{{ file.filename }}</li>
                </ul>
            </div>
        </div>

        <div v-if="result" id="photo-result" class="mt-6 max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">
                Yükleme tamamlandı — {{ result.summary.eslesen }} fotoğraf kaydedildi.
            </div>
            <h2 class="mt-4 text-xl font-bold text-gray-900">Fotoğraf Yükleme Sonucu</h2>
            <p v-if="result.year" class="mt-1 text-sm text-gray-600">Akademik Yıl: {{ result.year.name }}</p>

            <dl class="mt-4 divide-y divide-gray-200">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Eşleşen Fotoğraf</dt>
                    <dd class="font-semibold text-green-700">{{ result.summary.eslesen }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Eşleşmeyen Dosya</dt>
                    <dd class="font-semibold text-yellow-700">{{ result.summary.eslesmeyen }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Fotoğrafı Olmayan Öğrenci</dt>
                    <dd class="font-semibold">{{ result.summary.fotografsiz }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-600">Hatalı Dosya</dt>
                    <dd class="font-semibold text-red-700">{{ result.summary.hatali }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="result && result.unmatched.length > 0" class="mt-6 max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">Eşleşmeyen Dosyalar ({{ result.unmatched.length }})</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                <li v-for="name in result.unmatched" :key="name">{{ name }}</li>
            </ul>
        </div>

        <div v-if="result && result.failed.length > 0" class="mt-6 max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">Hatalı Dosyalar ({{ result.failed.length }})</h2>
            <ul class="mt-2 space-y-1 text-sm">
                <li v-for="file in result.failed" :key="file.filename" class="text-gray-600">
                    {{ file.filename }} — <span class="text-red-600">{{ file.message }}</span>
                </li>
            </ul>
        </div>

        <div v-if="result && result.withoutPhoto.length > 0" class="mt-6 max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-gray-900">Fotoğrafı Olmayan Öğrenciler ({{ result.summary.fotografsiz }})</h2>
            <ul class="mt-2 space-y-1 text-sm text-gray-600">
                <li v-for="student in result.withoutPhoto" :key="student.school_number">
                    {{ student.school_number }} — {{ student.full_name }}
                </li>
            </ul>
            <p v-if="result.withoutPhotoTruncated" class="mt-2 text-sm text-gray-400">Liste ilk 100 kayıtla sınırlı.</p>
        </div>
    </AppLayout>
</template>
