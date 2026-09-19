<?php

namespace App\Http\Controllers;

use App\Models\Graduate;
use App\Services\RehberExportService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GraduateController extends Controller
{
    public function __construct(private RehberExportService $export) {}

    public function index(Request $request): Response
    {
        $years = Graduate::distinct()->orderByDesc('graduation_year')->pluck('graduation_year')->values();
        $search = trim((string) $request->input('q', ''));

        // Arama varsa yıllara bakılmaksızın düz liste; yoksa her sayfa bir yıl.
        if ($search !== '') {
            $paginator = Graduate::with(['person:id,full_name,phone,email,photo_path', 'person.educations', 'person.employments'])
                ->whereHas('person', fn ($query) => $query->where('full_name', 'like', "%{$search}%"))
                ->orderByDesc('graduation_year')
                ->orderBy('graduation_number')
                ->paginate(30)
                ->withQueryString();

            $paginator->setCollection($paginator->getCollection()->map(fn (Graduate $graduate) => $this->flatten($graduate)));

            return Inertia::render('Mezunlar/Index', [
                'years' => $years,
                'year' => null,
                'page' => 1,
                'search' => $search,
                'graduates' => $paginator,
                'totalGraduates' => Graduate::count(),
            ]);
        }

        $page = min(max(1, $request->integer('page', 1)), max(1, $years->count()));
        $year = $years->get($page - 1);

        $rows = $year === null
            ? collect()
            : Graduate::with(['person:id,full_name,phone,email,photo_path', 'person.educations', 'person.employments'])
                ->where('graduation_year', $year)
                ->orderBy('graduation_number')
                ->get();

        $data = $rows->map(fn (Graduate $graduate) => $this->flatten($graduate))->values()->all();
        $total = count($data);

        return Inertia::render('Mezunlar/Index', [
            'years' => $years,
            'year' => $year,
            'page' => $page,
            'search' => $search,
            'graduates' => [
                'data' => $data,
                'from' => $total > 0 ? 1 : null,
                'to' => $total,
                'total' => $total,
                'prev_page_url' => $page > 1 ? "/mezunlar?page=".($page - 1) : null,
                'next_page_url' => $page < $years->count() ? "/mezunlar?page=".($page + 1) : null,
            ],
            'totalGraduates' => Graduate::count(),
        ]);
    }

    /**
     * @return array{id: int, year: int, number: string, full_name: string, phone: ?string, email: ?string, photo_path: ?string, education: ?string, company: ?string}
     */
    private function flatten(Graduate $graduate): array
    {
        $education = $graduate->person?->educations->first();

        return [
            'id' => $graduate->id,
            'year' => $graduate->graduation_year,
            'number' => $graduate->graduation_number,
            'full_name' => $graduate->person?->full_name ?? '—',
            'phone' => $graduate->person?->phone,
            'email' => $graduate->person?->email,
            'photo_path' => $graduate->person?->photo_path,
            'education' => $education
                ? trim(($education->institution_name ?? '').' / '.($education->department ?? ''), ' /')
                : null,
            'company' => $graduate->person?->employments->first()?->company_name,
        ];
    }

    public function vcf(Request $request): BinaryFileResponse
    {
        $years = Graduate::distinct()->pluck('graduation_year');
        $year = $request->input('year');
        $year = $year === null || $year === '' ? null : (int) $year;
        if ($year && ! $years->contains($year)) {
            $year = null;
        }
        $search = trim((string) $request->input('q', ''));
        $withPhoto = $request->boolean('photo', true);

        $result = $this->export->buildGraduateVcf($year, $search === '' ? null : $search, $withPhoto);

        $path = tempnam(sys_get_temp_dir(), 'mezun').'.vcf';
        file_put_contents($path, $result['content']);

        return response()->download($path, $result['filename'], [
            'Content-Type' => 'text/vcard; charset=utf-8',
        ])->deleteFileAfterSend();
    }
}
