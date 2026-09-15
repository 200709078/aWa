<script setup lang="ts">
import { computed, ref } from 'vue';
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

const academicYearId = ref<number | null>(props.activeYearId ?? null);
const photoFiles = ref<File[]>([]);
const zipFile = ref<File | null>(null);

const uploading = ref(false);
const doneCount = ref(0);
const totalCount = ref(0);
const statusText = ref('');
const errorMessages = ref<string[]>([]);
const result = ref<BatchPayload | null>(null);

const progressPct = computed(() => {
    if (totalCount.value === 0) return 0;
    return Math.round((doneCount.value / totalCount.value) * 100);
});

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

async function postBatch(formData: FormData): Promise<BatchPayload> {
    const response = await fetch('/students/photos', {
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

    return (await response.json()) as BatchPayload;
}

async function submit() {
    errorMessages.value = [];
    result.value = null;

    if (!academicYearId.value) {
        errorMessages.value = ['Akademik Yıl seçin.'];
        return;
    }
    if (photoFiles.value.length === 0 && !zipFile.value) {
        errorMessages.value = ['Fotoğraf dosyası veya ZIP seçin.'];
        return;
    }

    uploading.value = true;
    doneCount.value = 0;
    statusText.value = 'Hazırlanıyor…';

    try {
        let eslesen = 0;
        const unmatched: string[] = [];
        const failed: FailedFile[] = [];
        let last: BatchPayload | null = null;

        // Önce ZIP varsa tek istekte yükle.
        if (zipFile.value) {
            totalCount.value = photoFiles.value.length + 1;
            statusText.value = 'ZIP yükleniyor…';
            const fd = new FormData();
            fd.append('academic_year_id', String(academicYearId.value));
            fd.append('zip_file', zipFile.value);
            last = await postBatch(fd);
            eslesen += last.summary.eslesen;
            unmatched.push(...last.unmatched);
            failed.push(...last.failed);
            doneCount.value = 1;
        }

        const chunks = chunkFiles(photoFiles.value);
        if (photoFiles.value.length > 0 && totalCount.value === 0) {
            totalCount.value = photoFiles.value.length;
        } else if (photoFiles.value.length > 0) {
            // ZIP + fotoğraf birlikte seçildiyse toplam zaten ayarlandı.
        } else {
            totalCount.value = 1;
        }

        for (let i = 0; i < chunks.length; i++) {
            statusText.value = `Fotoğraflar yükleniyor (${i + 1}/${chunks.length})…`;
            const fd = new FormData();
            fd.append('academic_year_id', String(academicYearId.value));
            for (const file of chunks[i]) {
                fd.append('photos[]', file);
            }
            const payload = await postBatch(fd);
            eslesen += payload.summary.eslesen;
            unmatched.push(...payload.unmatched);
            failed.push(...payload.failed);
            last = payload;
            doneCount.value += chunks[i].length;
        }

        if (!last) throw new Error('Yükleme sonucu alınamadı.');

        result.value = {
            year: last.year,
            summary: {
                eslesen,
                eslesmeyen: unmatched.length,
                fotografsiz: last.summary.fotografsiz,
                hatali: failed.length,
            },
            unmatched,
            failed,
            withoutPhoto: last.withoutPhoto,
            withoutPhotoTruncated: last.withoutPhotoTruncated,
        };
        statusText.value = 'Tamamlandı.';
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
                Çoklu dosya seçimi veya ZIP yükleyebilirsiniz. Çok sayıda dosya otomatik olarak küçük
                gruplar halinde yüklenir.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="photo-year" class="block text-sm font-medium text-gray-700">Akademik Yıl</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="photo-year"
                            v-model="academicYearId"
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
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp,.bmp"
                        class="mt-1 block w-full text-sm text-gray-600"
                        @change="(e) => (photoFiles = Array.from((e.target as HTMLInputElement).files ?? []))"
                    />
                    <p v-if="photoFiles.length > 0" class="mt-1 text-sm text-gray-500">
                        {{ photoFiles.length }} dosya seçildi
                    </p>
                </div>

                <div>
                    <label for="photo-zip" class="block text-sm font-medium text-gray-700">Veya ZIP Dosyası</label>
                    <input
                        id="photo-zip"
                        type="file"
                        accept=".zip"
                        class="mt-1 block w-full text-sm text-gray-600"
                        @change="(e) => (zipFile = (e.target as HTMLInputElement).files?.[0] ?? null)"
                    />
                </div>

                <div v-if="errorMessages.length > 0" class="rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                    <p v-for="(message, i) in errorMessages" :key="i">{{ message }}</p>
                </div>

                <div v-if="uploading" class="space-y-1">
                    <div class="h-2 overflow-hidden rounded bg-gray-200">
                        <div class="h-full bg-indigo-600" :style="{ width: `${progressPct}%` }"></div>
                    </div>
                    <p class="text-sm text-gray-600">{{ statusText }} ({{ doneCount }}/{{ totalCount }})</p>
                </div>

                <button
                    type="submit"
                    :disabled="uploading"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ uploading ? 'Yükleniyor…' : 'Yükle ve eşleştir' }}
                </button>
            </form>
        </div>

        <div v-if="result" class="mt-6 max-w-2xl rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-gray-900">Fotoğraf Yükleme Sonucu</h2>
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
