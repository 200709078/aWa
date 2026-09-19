<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\School;
use App\Services\RehberExportService;
use App\Support\SchoolScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RehberAktarmaController extends Controller
{
    public function __construct(private RehberExportService $export) {}

    public function index(): Response
    {
        $years = AcademicYear::where('school_id', SchoolScope::id())->orderByDesc('name')->get(['id', 'name', 'is_active']);
        $activeId = $years->firstWhere('is_active', true)?->id ?? $years->first()?->id;

        return Inertia::render('Rehber/Index', [
            'years' => $years,
            'activeYearId' => $activeId,
        ]);
    }

    public function branches(AcademicYear $academicYear): JsonResponse
    {
        SchoolScope::ensure($academicYear);

        return response()->json(
            Branch::where('academic_year_id', $academicYear->id)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function summary(Request $request): JsonResponse
    {
        [$yearId, $branchIds, $type] = $this->validatedFilters($request);

        return response()->json($this->export->summary($yearId, $branchIds, $type));
    }

    public function download(Request $request): BinaryFileResponse
    {
        [$yearId, $branchIds, $type] = $this->validatedFilters($request);
        $withPhoto = $request->boolean('photo', true);

        $year = AcademicYear::findOrFail($yearId);
        $school = $year->school_id ? School::find($year->school_id) : null;

        $result = $this->export->build($yearId, $branchIds, $type, $school?->name ?? '', $year->name, $withPhoto);

        $path = tempnam(sys_get_temp_dir(), 'vcf').'.vcf';
        file_put_contents($path, $result['content']);

        return response()->download($path, $result['filename'], [
            'Content-Type' => 'text/vcard; charset=utf-8',
        ])->deleteFileAfterSend();
    }

    public function excel(Request $request): BinaryFileResponse
    {
        [$yearId, $branchIds, $type] = $this->validatedFilters($request);

        $year = AcademicYear::findOrFail($yearId);
        $result = $this->export->buildExcel($yearId, $branchIds, $type, $year->name);

        return response()->download($result['filepath'], $result['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * @return array{0: int, 1: array<int>, 2: string}
     */
    private function validatedFilters(Request $request): array
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('school_id', SchoolScope::id())],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'distinct'],
            'type' => ['required', 'string', Rule::in(['all', 'student', 'guardian'])],
        ], [
            'academic_year_id.required' => 'Akademik yıl seçin.',
            'branch_ids.required' => 'En az bir şube seçin.',
            'branch_ids.min' => 'En az bir şube seçin.',
            'type.in' => 'Geçersiz kayıt tipi.',
        ]);

        $yearId = (int) $data['academic_year_id'];
        $branchIds = Branch::where('academic_year_id', $yearId)->whereIn('id', $data['branch_ids'])->pluck('id')->all();

        abort_if($branchIds === [], 422, 'Seçilen şubeler bu akademik yıla ait değil.');

        return [$yearId, $branchIds, $data['type']];
    }
}
