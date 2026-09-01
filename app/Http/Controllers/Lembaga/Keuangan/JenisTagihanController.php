<?php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Actions\JenisTagihan\CreateJenisTagihanAction;
use App\Domains\Keuangan\Actions\JenisTagihan\DeleteJenisTagihanAction;
use App\Domains\Keuangan\Actions\JenisTagihan\ProsesJenisTagihanBillingAction;
use App\Domains\Keuangan\Actions\JenisTagihan\ReorderTarifGrupAction;
use App\Domains\Keuangan\Actions\JenisTagihan\SimpanNominalJenisTagihanAction;
use App\Domains\Keuangan\Actions\JenisTagihan\UpdateJenisTagihanAction;
use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;
use App\Domains\Keuangan\Enums\KategoriTagihan;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;
use App\Domains\Keuangan\Models\JenisTagihanSasaranKriteria;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Http\Controllers\Controller;
use App\Jobs\RecalculateTagihanNominalJob;
use App\Models\JalurPpdb;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JenisTagihanController extends Controller
{
    use AuthorizesRequests;

    private const KRITERIA_FIELDS = ['tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'];

    public function index(Request $request): View
    {
        $this->authorize('jenis-tagihan.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = JenisTagihan::withCount(['nominalJalur', 'tagihanItem'])->orderBy('nama');

        if ($search = $request->input('search')) {
            $query->where('nama', 'like', '%'.$search.'%');
        }

        if ($kategori = $request->input('kategori')) {
            $query->where('kategori', $kategori);
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('is_active', $request->input('status'));
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.keuangan.jenis-tagihan._daftar', [
                'jenisTagihanList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        return view('portals.lembaga.keuangan.jenis-tagihan.index', [
            'jenisTagihanList' => $paginated,
            'perPage' => $perPage,
            'totalJenis' => JenisTagihan::count(),
            'totalAktif' => JenisTagihan::where('is_active', true)->count(),
            'totalDipakai' => JenisTagihan::has('tagihanItem')->count(),
            'kategoriList' => [
                'pendaftaran' => 'Pendaftaran',
                'daftar_ulang' => 'Daftar Ulang',
                'spp' => 'SPP',
                'tahunan' => 'Tahunan',
                'kegiatan' => 'Kegiatan',
                'lainnya' => 'Lainnya',
                'custom' => 'Custom',
            ],
            'statusList' => [
                '1' => 'Aktif',
                '0' => 'Tidak Aktif',
            ],
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $this->resolveLembagaIdOrFail($request);
        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.']);
        }

        return view('portals.lembaga.keuangan.jenis-tagihan.form', array_merge(
            ['jenisTagihan' => null],
            $this->referenceData($lembagaId)
        ));
    }

    public function edit(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.edit');

        $jenisTagihan->load(['sasaranGrup.kriteria', 'keringananRules.kategoriKeringanan']);

        return view('portals.lembaga.keuangan.jenis-tagihan.form', array_merge(
            ['jenisTagihan' => $jenisTagihan],
            $this->referenceData($jenisTagihan->lembaga_id)
        ));
    }

    public function store(Request $request, CreateJenisTagihanAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $this->resolveLembagaIdOrFail($request);
        if ($lembagaId === null) {
            $message = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tagihan.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'errors' => ['lembaga_id' => [$message]]], 422);
            }

            return back()->withErrors(['lembaga_id' => $message])->withInput();
        }

        $isPpdbKategori = KategoriTagihan::tryFrom($request->input('kategori'))?->isPpdb() ?? false;

        if ($isPpdbKategori && $this->hasBillingPayload($request)) {
            return $this->errorResponse($request, 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.');
        }

        if ($request->input('mode') === 'otomatis' && $request->input('tipe') === 'sekali') {
            return $this->errorResponse($request, "Tipe 'Sekali' tidak bisa dipasangkan dengan Mode Otomatis karena kontradiktif (generate berulang vs sekali saja).");
        }

        $data = $request->validate($this->baseRules($lembagaId, null));
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');
        $data['is_active'] = $request->boolean('is_active');

        $billing = null;
        if (! $isPpdbKategori) {
            $billing = $request->validate($this->billingRules($lembagaId, $request));
            $this->validateKelasKriteriaLembaga($billing, $lembagaId);
            $duplicateError = $this->findDuplicateKeringanan($billing['keringanan'] ?? []);
            if ($duplicateError) {
                return $this->errorResponse($request, $duplicateError);
            }
        }

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $data['lembaga_id'] = $lembagaId;
        }

        $dto = JenisTagihanData::fromArray($data, $billing);
        $jenisTagihan = $action->execute($lembagaId, $dto);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $jenisTagihan,
                'redirect' => $isPpdbKategori ? route('admin.jenis-tagihan.nominal', $jenisTagihan) : null,
            ], 201);
        }

        if ($isPpdbKategori) {
            return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)
                ->with('status', 'Jenis tagihan berhasil ditambahkan. Atur nominal per jalur di bawah.');
        }

        return redirect()->route('admin.jenis-tagihan.index')
            ->with('status', 'Jenis tagihan berhasil ditambahkan.');
    }

    public function update(Request $request, JenisTagihan $jenisTagihan, UpdateJenisTagihanAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $isPpdbKategori = KategoriTagihan::tryFrom($request->input('kategori'))?->isPpdb() ?? false;

        if ($isPpdbKategori && $this->hasBillingPayload($request)) {
            return $this->errorResponse($request, 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.');
        }

        if ($request->input('mode') === 'otomatis' && $request->input('tipe') === 'sekali') {
            return $this->errorResponse($request, "Tipe 'Sekali' tidak bisa dipasangkan dengan Mode Otomatis karena kontradiktif (generate berulang vs sekali saja).");
        }

        $data = $request->validate($this->baseRules($jenisTagihan->lembaga_id, $jenisTagihan));
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');
        $data['is_active'] = $request->boolean('is_active');

        $billing = null;
        if (! $isPpdbKategori) {
            $billing = $request->validate($this->billingRules($jenisTagihan->lembaga_id, $request));
            $this->validateKelasKriteriaLembaga($billing, $jenisTagihan->lembaga_id);
            $duplicateError = $this->findDuplicateKeringanan($billing['keringanan'] ?? []);
            if ($duplicateError) {
                return $this->errorResponse($request, $duplicateError);
            }
        }

        $dto = JenisTagihanData::fromArray($data, $billing);
        $jenisTagihan = $action->execute($jenisTagihan, $dto);

        if ($jenisTagihan->syncBillingConfigResult?->tarifBerubah || $jenisTagihan->syncBillingConfigResult?->keringananBerubah) {
            Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)
                ->whereNotIn('status', ['lunas', 'dibatalkan'])
                ->pluck('id')
                ->each(fn (int $tagihanId) => RecalculateTagihanNominalJob::dispatch($tagihanId));
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $jenisTagihan->loadCount(['nominalJalur', 'tagihanItem'])]);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisTagihan $jenisTagihan, DeleteJenisTagihanAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.delete');

        try {
            $action->execute($jenisTagihan);
        } catch (ValidationException $e) {
            $message = $e->errors()['jenis_tagihan'][0] ?? $e->getMessage();

            return $this->errorResponse($request, $message);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis tagihan berhasil dihapus.']);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil dihapus.');
    }

    public function previewSasaran(Request $request, JenisTagihanSasaranMatcher $matcher): JsonResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $this->resolveLembagaIdOrFail($request);
        if ($lembagaId === null) {
            return response()->json(['count' => 0]);
        }

        $data = $request->validate(['sasaran' => ['nullable', 'array']]);

        $draftJenisTagihan = new JenisTagihan(['lembaga_id' => $lembagaId]);
        $draftGrups = collect($data['sasaran'] ?? [])->map(function ($grupData) {
            $grup = new JenisTagihanSasaranGrup(['tipe' => 'sasaran']);
            $grup->setRelation('kriteria', collect($grupData['kriteria'] ?? [])->map(fn ($k) => new JenisTagihanSasaranKriteria($k)));

            return $grup;
        });
        $draftJenisTagihan->setRelation('sasaranGrup', $draftGrups);

        $count = Siswa::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $lembagaId)
            ->get()
            ->filter(fn (Siswa $siswa) => $matcher->siswaMatchesJenisTagihan($siswa, $draftJenisTagihan))
            ->count();

        return response()->json(['count' => $count]);
    }

    public function previewTarifKeringanan(Request $request, JenisTagihanSasaranMatcher $matcher): JsonResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $this->resolveLembagaIdOrFail($request);
        if ($lembagaId === null) {
            return response()->json(['tarif_counts' => [], 'keringanan_counts' => []]);
        }

        $data = $request->validate([
            'tarif' => ['nullable', 'array'],
            'keringanan' => ['nullable', 'array'],
        ]);

        $siswaList = Siswa::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $lembagaId)
            ->get();

        $tarifCounts = [];
        foreach ($data['tarif'] ?? [] as $tarifData) {
            $grup = new JenisTagihanSasaranGrup(['tipe' => 'tarif']);
            $grup->setRelation('kriteria', collect($tarifData['kriteria'] ?? [])->map(fn ($k) => new JenisTagihanSasaranKriteria($k)));

            $tarifCounts[] = $siswaList->filter(fn (Siswa $siswa) => $matcher->siswaMatchesGrup($siswa, $grup))->count();
        }

        $keringananCounts = [];
        $kategoriIds = collect($data['keringanan'] ?? [])->pluck('kategori_keringanan_id')->filter()->unique();
        if ($kategoriIds->isNotEmpty()) {
            $counts = SiswaKeringanan::whereIn('kategori_keringanan_id', $kategoriIds)
                ->whereHas('siswa', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lembagaId))
                ->selectRaw('kategori_keringanan_id, count(distinct siswa_id) as total')
                ->groupBy('kategori_keringanan_id')
                ->pluck('total', 'kategori_keringanan_id');

            foreach ($kategoriIds as $katId) {
                $keringananCounts[(string) $katId] = (int) ($counts[$katId] ?? 0);
            }
        }

        return response()->json([
            'tarif_counts' => $tarifCounts,
            'keringanan_counts' => $keringananCounts,
        ]);
    }

    public function previewSiswaKeringanan(Request $request, JenisTagihanSasaranMatcher $matcher): JsonResponse
    {
        $this->authorize('jenis-tagihan.create');

        $lembagaId = $this->resolveLembagaIdOrFail($request);
        if ($lembagaId === null) {
            return response()->json(['siswa' => []]);
        }

        $data = $request->validate([
            'sasaran' => ['nullable', 'array'],
            'search' => ['nullable', 'string', 'max:255'],
            'kelas_id' => ['nullable', 'integer'],
        ]);

        $draftJenisTagihan = new JenisTagihan(['lembaga_id' => $lembagaId]);
        $draftGrups = collect($data['sasaran'] ?? [])->map(function ($grupData) {
            $grup = new JenisTagihanSasaranGrup(['tipe' => 'sasaran']);
            $grup->setRelation('kriteria', collect($grupData['kriteria'] ?? [])->map(fn ($k) => new JenisTagihanSasaranKriteria($k)));

            return $grup;
        });
        $draftJenisTagihan->setRelation('sasaranGrup', $draftGrups);

        $query = Siswa::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $lembagaId)
            ->with('kelas');

        if (! empty($data['search'])) {
            $query->search($data['search']);
        }

        if (! empty($data['kelas_id'])) {
            $query->where('kelas_id', $data['kelas_id']);
        }

        $siswaList = $query->get()
            ->filter(fn (Siswa $siswa) => $matcher->siswaMatchesJenisTagihan($siswa, $draftJenisTagihan))
            ->values();

        $today = now()->toDateString();
        $assignmentsBySiswa = SiswaKeringanan::whereIn('siswa_id', $siswaList->pluck('id'))
            ->where('berlaku_dari', '<=', $today)
            ->where(fn ($q) => $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $today))
            ->get(['id', 'siswa_id', 'kategori_keringanan_id'])
            ->groupBy('siswa_id');

        $result = $siswaList->map(fn (Siswa $siswa) => [
            'id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'kelas' => $siswa->kelas->nama ?? '-',
            'assignments' => ($assignmentsBySiswa->get($siswa->id) ?? collect())
                ->mapWithKeys(fn ($row) => [(string) $row->kategori_keringanan_id => $row->id]),
        ])->values();

        return response()->json(['siswa' => $result]);
    }

    public function prosesTagihan(
        JenisTagihan $jenisTagihan,
        JenisTagihanSasaranMatcher $matcher,
        ProsesJenisTagihanBillingAction $action
    ): JsonResponse {
        $this->authorize('jenis-tagihan.edit');

        if ($jenisTagihan->kategori->isPpdb()) {
            return response()->json([
                'message' => "Jenis tagihan berkategori {$jenisTagihan->kategori->label()} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB.",
            ], 422);
        }

        $totalPool = $matcher->countTotalSiswaPool($jenisTagihan);
        $targetCount = $matcher->resolveTargetSiswa($jenisTagihan)->count();

        $log = $action->execute($jenisTagihan);

        $gagal = count($log->error_log ?? []);
        $tidakMemenuhiKriteria = $totalPool - $targetCount;
        $sudahTertagih = $targetCount - $log->bills_generated - $gagal;

        return response()->json([
            'message' => "{$log->bills_generated} tagihan dibuat, {$sudahTertagih} sudah tertagih, {$tidakMemenuhiKriteria} tidak memenuhi kriteria, {$gagal} gagal.",
            'bills_generated' => $log->bills_generated,
            'sudah_tertagih' => $sudahTertagih,
            'tidak_memenuhi_kriteria' => $tidakMemenuhiKriteria,
            'gagal' => $gagal,
            'status_text' => match ($log->status) {
                'success' => 'Berhasil',
                'partial' => 'Selesai Parsial',
                'failed' => 'Gagal Total',
                default => 'Selesai',
            },
        ]);
    }

    public function nominal(JenisTagihan $jenisTagihan): View|RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if (! $jenisTagihan->kategori->isPpdb()) {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang. Kategori "Lainnya" belum punya mekanisme penentuan nominal.']);
        }

        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $jenisTagihan->lembaga_id)->where('status_aktif', true)->first();

        return view('portals.lembaga.keuangan.jenis-tagihan.nominal', [
            'jenisTagihan' => $jenisTagihan,
            'jalurList' => $tahunAjaranAktif
                ? JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
                : collect(),
            'nominalMap' => NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->pluck('nominal', 'jalur_ppdb_id'),
            'tahunAjaranAktif' => $tahunAjaranAktif,
        ]);
    }

    public function simpanNominal(
        Request $request,
        JenisTagihan $jenisTagihan,
        SimpanNominalJenisTagihanAction $action
    ): RedirectResponse {
        $this->authorize('jenis-tagihan.edit');

        if (! $jenisTagihan->kategori->isPpdb()) {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang.']);
        }

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $action->execute($jenisTagihan, $data['nominal']);

        return redirect()->route('admin.jenis-tagihan.nominal', $jenisTagihan)->with('status', 'Nominal berhasil disimpan.');
    }

    private function resolveLembagaIdOrFail(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }

    private function referenceData(int $lembagaId): array
    {
        return [
            'lembagaList' => Lembaga::orderBy('nama')->get(['id', 'nama']),
            'tahunAjaranList' => TahunAjaran::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']),
            'kelasList' => Kelas::where('lembaga_id', $lembagaId)
                ->with(['tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                ->orderBy('nama')->get(['id', 'nama', 'tahun_ajaran_id']),
            'tingkatList' => Kelas::where('lembaga_id', $lembagaId)->whereNotNull('tingkat')->distinct()->orderBy('tingkat')->pluck('tingkat'),
            'kategoriKeringananList' => KategoriKeringanan::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']),
        ];
    }

    private function hasBillingPayload(Request $request): bool
    {
        return $request->has('sasaran') || $request->has('tarif') || $request->has('keringanan')
            || $request->has('tipe') || $request->has('hari_generate') || $request->has('bulan_generate') || $request->has('offset_hari_jatuh_tempo');
    }

    private function baseRules(int $lembagaId, ?JenisTagihan $editing): array
    {
        return [
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tagihan', 'nama')
                ->where(fn ($query) => $query->where('lembaga_id', $lembagaId))
                ->ignore($editing?->id)],
            'kategori' => ['required', Rule::in(['pendaftaran', 'daftar_ulang', 'lainnya', 'spp', 'tahunan', 'kegiatan', 'custom'])],
            'bisa_dicicil' => ['nullable', 'boolean'],
            'maks_cicilan' => ['nullable', 'integer', 'min:2', 'required_if:bisa_dicicil,1'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'mode' => ['nullable', Rule::in(['manual', 'otomatis'])],
            'tipe' => [Rule::requiredIf(fn () => ! (KategoriTagihan::tryFrom(request('kategori'))?->isPpdb() ?? false)), 'nullable', Rule::in(['harian', 'mingguan', 'bulanan', 'tahunan', 'sekali'])],
            'tanggal_mulai' => ['nullable', 'date', 'required_if:mode,otomatis'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'hari_generate' => ['nullable', 'integer', 'between:1,7', Rule::requiredIf(fn () => request('mode') === 'otomatis' && request('tipe') === 'mingguan')],
            'bulan_generate' => ['nullable', 'integer', 'between:1,12', Rule::requiredIf(fn () => request('mode') === 'otomatis' && request('tipe') === 'tahunan')],
            'tanggal_generate' => ['nullable', 'integer', 'between:1,31', Rule::requiredIf(fn () => request('mode') === 'otomatis' && in_array(request('tipe'), ['bulanan', 'tahunan'], true))],
            'hari_jatuh_tempo' => ['nullable', 'integer', 'between:1,31', Rule::requiredIf(fn () => request('mode') === 'otomatis' && in_array(request('tipe'), ['bulanan', 'tahunan'], true))],
            'offset_hari_jatuh_tempo' => ['nullable', 'integer', 'min:0', Rule::requiredIf(fn () => request('mode') === 'otomatis' && in_array(request('tipe'), ['harian', 'mingguan'], true))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function billingRules(int $lembagaId, Request $request): array
    {
        return [
            'sasaran' => ['nullable', 'array'],
            'sasaran.*.kriteria' => ['required', 'array', 'min:1'],
            'sasaran.*.kriteria.*.field' => ['required', Rule::in(self::KRITERIA_FIELDS)],
            'sasaran.*.kriteria.*.operator' => ['required', Rule::in(['in', 'not_in'])],
            'sasaran.*.kriteria.*.value' => ['required', 'array', 'min:1'],
            'sasaran.*.kriteria.*.value.*' => ['string', 'max:255'],
            'tarif' => ['nullable', 'array'],
            'tarif.*.nominal' => ['required', 'numeric', 'min:0'],
            'tarif.*.kriteria' => ['required', 'array', 'min:1'],
            'tarif.*.kriteria.*.field' => ['required', Rule::in(self::KRITERIA_FIELDS)],
            'tarif.*.kriteria.*.operator' => ['required', Rule::in(['in', 'not_in'])],
            'tarif.*.kriteria.*.value' => ['required', 'array', 'min:1'],
            'tarif.*.kriteria.*.value.*' => ['string', 'max:255'],
            'keringanan' => ['nullable', 'array'],
            'keringanan.*.kategori_keringanan_id' => ['required', 'integer', Rule::exists('kategori_keringanan', 'id')->where('lembaga_id', $lembagaId)],
            'keringanan.*.tipe_potongan' => ['required', Rule::in(['fixed', 'persen'])],
            'keringanan.*.nilai' => ['required', 'numeric', 'min:0', function ($attribute, $value, $fail) use ($request) {
                preg_match('/keringanan\.(\d+)\.nilai/', $attribute, $matches);
                $index = $matches[1] ?? null;
                $tipe = $request->input("keringanan.{$index}.tipe_potongan");
                if ($tipe === 'persen' && $value > 100) {
                    $fail('Potongan persentase tidak boleh lebih dari 100.');
                }
            }],
            'keringanan.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function findDuplicateKeringanan(array $keringanan): ?string
    {
        $ids = array_column($keringanan, 'kategori_keringanan_id');
        if (count($ids) !== count(array_unique($ids))) {
            return 'Satu kategori keringanan tidak boleh dipakai lebih dari sekali untuk jenis tagihan yang sama.';
        }

        return null;
    }

    private function validateKelasKriteriaLembaga(array $billing, int $lembagaId): void
    {
        $semuaKriteria = collect($billing['sasaran'] ?? [])->flatMap(fn ($grup) => $grup['kriteria'] ?? [])
            ->merge(collect($billing['tarif'] ?? [])->flatMap(fn ($grup) => $grup['kriteria'] ?? []));

        foreach ($semuaKriteria as $kriteria) {
            if (($kriteria['field'] ?? null) !== 'kelas') {
                continue;
            }

            $idsValid = Kelas::where('lembaga_id', $lembagaId)->whereIn('id', $kriteria['value'] ?? [])->pluck('id')->all();
            $idsDiminta = array_map('intval', $kriteria['value'] ?? []);

            if (array_diff($idsDiminta, $idsValid) !== []) {
                throw ValidationException::withMessages(['sasaran' => 'Salah satu kelas yang dipilih tidak ditemukan di lembaga ini.']);
            }
        }
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['jenis_tagihan' => $message])->withInput();
    }

    public function reorderTarif(Request $request, JenisTagihan $jenisTagihan, ReorderTarifGrupAction $action): JsonResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $data = $request->validate(['urutan_grup_id' => ['required', 'array']]);

        $action->execute($jenisTagihan, $data['urutan_grup_id']);

        return response()->json(['message' => 'Urutan prioritas Tarif berhasil disimpan.']);
    }
}
