<?php

namespace App\Http\Controllers\Admin;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\KategoriKeringanan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanBillingGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JenisTagihanController extends BaseController
{
    use AuthorizesRequests;

    private const PPDB_KATEGORI = ['pendaftaran', 'daftar_ulang'];

    private const KRITERIA_FIELDS = ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa'];

    public function index(Request $request): View
    {
        $this->authorize('jenis-tagihan.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = JenisTagihan::withCount(['nominalJalur', 'tagihanItem'])->orderBy('nama');

        if ($search = $request->input('search')) {
            $query->where('nama', 'like', '%' . $search . '%');
        }

        if ($kategori = $request->input('kategori')) {
            $query->where('kategori', $kategori);
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('is_active', $request->input('status'));
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.jenis-tagihan._daftar', [
                'jenisTagihanList' => $paginated,
                'perPage'          => $perPage,
            ]);
        }

        return view('admin.jenis-tagihan.index', [
            'jenisTagihanList' => $paginated,
            'perPage'          => $perPage,
            'totalJenis'       => JenisTagihan::count(),
            'totalAktif'       => JenisTagihan::where('is_active', true)->count(),
            'totalDipakai'     => JenisTagihan::has('tagihanItem')->count(),
            'kategoriList'     => [
                'pendaftaran'  => 'Pendaftaran',
                'daftar_ulang' => 'Daftar Ulang',
                'spp'          => 'SPP',
                'tahunan'      => 'Tahunan',
                'kegiatan'     => 'Kegiatan',
                'lainnya'      => 'Lainnya',
                'custom'       => 'Custom',
            ],
            'statusList'       => [
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

        return view('admin.jenis-tagihan.form', array_merge(
            ['jenisTagihan' => null],
            $this->referenceData($lembagaId)
        ));
    }

    public function edit(JenisTagihan $jenisTagihan): View
    {
        $this->authorize('jenis-tagihan.edit');

        $jenisTagihan->load(['sasaranGrup.kriteria', 'keringananRules.kategoriKeringanan']);

        return view('admin.jenis-tagihan.form', array_merge(
            ['jenisTagihan' => $jenisTagihan],
            $this->referenceData($jenisTagihan->lembaga_id)
        ));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
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

        $isPpdbKategori = in_array($request->input('kategori'), self::PPDB_KATEGORI, true);

        if ($isPpdbKategori && $this->hasBillingPayload($request)) {
            return $this->errorResponse($request, 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.');
        }

        $data = $request->validate($this->baseRules($lembagaId, null));
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');
        $data['is_active'] = $request->boolean('is_active');

        $billing = null;
        if (! $isPpdbKategori) {
            $billing = $request->validate($this->billingRules($lembagaId, $request));
            $duplicateError = $this->findDuplicateKeringanan($billing['keringanan'] ?? []);
            if ($duplicateError) {
                return $this->errorResponse($request, $duplicateError);
            }
        }

        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $data['lembaga_id'] = $lembagaId;
        }

        $jenisTagihan = DB::transaction(function () use ($data, $billing) {
            $jenisTagihan = JenisTagihan::create($data);
            if ($billing !== null) {
                $this->syncBillingConfig($jenisTagihan, $billing);
            }

            return $jenisTagihan;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $jenisTagihan->fresh(),
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

    public function update(Request $request, JenisTagihan $jenisTagihan): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.edit');

        $isPpdbKategori = in_array($request->input('kategori'), self::PPDB_KATEGORI, true);

        if ($isPpdbKategori && $this->hasBillingPayload($request)) {
            return $this->errorResponse($request, 'Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang.');
        }

        $data = $request->validate($this->baseRules($jenisTagihan->lembaga_id, $jenisTagihan));
        $data['bisa_dicicil'] = $request->boolean('bisa_dicicil');
        $data['is_active'] = $request->boolean('is_active');

        $billing = null;
        if (! $isPpdbKategori) {
            $billing = $request->validate($this->billingRules($jenisTagihan->lembaga_id, $request));
            $duplicateError = $this->findDuplicateKeringanan($billing['keringanan'] ?? []);
            if ($duplicateError) {
                return $this->errorResponse($request, $duplicateError);
            }
        }

        DB::transaction(function () use ($jenisTagihan, $data, $billing) {
            if ($billing !== null) {
                $this->syncBillingConfig($jenisTagihan, $billing);
            } else {
                $jenisTagihan->sasaranGrup()->delete();
                $jenisTagihan->keringananRules()->delete();
            }
            $jenisTagihan->update($data);
        });

        if ($request->wantsJson()) {
            return response()->json(['data' => $jenisTagihan->fresh()->loadCount(['nominalJalur', 'tagihanItem'])]);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisTagihan $jenisTagihan): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tagihan.delete');

        $jumlahTagihan = $jenisTagihan->tagihanItem()->count();
        if ($jumlahTagihan > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah dipakai di {$jumlahTagihan} tagihan milik calon murid."
            );
        }

        $jumlahNominal = $jenisTagihan->nominalJalur()->count();
        if ($jumlahNominal > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah ada {$jumlahNominal} nominal jalur yang dikonfigurasi. Hapus dulu di halaman Kelola Nominal."
            );
        }

        $jenisTagihan->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis tagihan berhasil dihapus.']);
        }

        return redirect()->route('admin.jenis-tagihan.index')->with('status', 'Jenis tagihan berhasil dihapus.');
    }

    public function prosesTagihan(JenisTagihan $jenisTagihan, JenisTagihanSasaranMatcher $matcher, TagihanBillingGenerator $generator): JsonResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if (in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return response()->json([
                'message' => "Jenis tagihan berkategori {$jenisTagihan->kategori} tidak bisa diproses lewat billing engine — gunakan alur pendaftaran PPDB.",
            ], 422);
        }

        $totalPool = $matcher->countTotalSiswaPool($jenisTagihan);
        $targetCount = $matcher->resolveTargetSiswa($jenisTagihan)->count();

        $log = $generator->generate($jenisTagihan, 'manual');

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

        if (! in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang. Kategori "Lainnya" belum punya mekanisme penentuan nominal.']);
        }

        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $jenisTagihan->lembaga_id)->where('status_aktif', true)->first();

        return view('admin.jenis-tagihan.nominal', [
            'jenisTagihan' => $jenisTagihan,
            'jalurList' => $tahunAjaranAktif
                ? JalurPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
                : collect(),
            'nominalMap' => NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->pluck('nominal', 'jalur_ppdb_id'),
            'tahunAjaranAktif' => $tahunAjaranAktif,
        ]);
    }

    public function simpanNominal(Request $request, JenisTagihan $jenisTagihan): RedirectResponse
    {
        $this->authorize('jenis-tagihan.edit');

        if (! in_array($jenisTagihan->kategori, self::PPDB_KATEGORI, true)) {
            return redirect()->route('admin.jenis-tagihan.index')
                ->withErrors(['kategori' => 'Nominal per jalur PPDB hanya berlaku untuk kategori Pendaftaran/Daftar Ulang.']);
        }

        $data = $request->validate([
            'nominal' => ['required', 'array'],
            'nominal.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $jalurIds = JalurPpdb::where('lembaga_id', $jenisTagihan->lembaga_id)->pluck('id');

        foreach ($data['nominal'] as $jalurPpdbId => $nominal) {
            if (! $jalurIds->contains((int) $jalurPpdbId) || $nominal === null || $nominal === '') {
                continue;
            }

            NominalTagihanJalur::updateOrCreate(
                ['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalurPpdbId],
                ['nominal' => $nominal]
            );
        }

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
                ->with(['tahunAjaran' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)])
                ->orderBy('nama')->get(['id', 'nama', 'tahun_ajaran_id']),
            'tingkatList' => Kelas::where('lembaga_id', $lembagaId)->whereNotNull('tingkat')->distinct()->orderBy('tingkat')->pluck('tingkat'),
            'kategoriKeringananList' => KategoriKeringanan::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']),
        ];
    }

    private function hasBillingPayload(Request $request): bool
    {
        return $request->has('sasaran') || $request->has('tarif') || $request->has('keringanan');
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
            'tanggal_mulai' => ['nullable', 'date', 'required_if:mode,otomatis'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tanggal_generate' => ['nullable', 'integer', 'between:1,31', 'required_if:mode,otomatis'],
            'hari_jatuh_tempo' => ['nullable', 'integer', 'min:0'],
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

    private function syncBillingConfig(JenisTagihan $jenisTagihan, array $billing): void
    {
        $jenisTagihan->sasaranGrup()->delete();
        $jenisTagihan->keringananRules()->delete();

        foreach ($billing['sasaran'] ?? [] as $grupData) {
            $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
            foreach ($grupData['kriteria'] as $kriteriaData) {
                $grup->kriteria()->create($kriteriaData);
            }
        }

        foreach ($billing['tarif'] ?? [] as $grupData) {
            $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => $grupData['nominal']]);
            foreach ($grupData['kriteria'] as $kriteriaData) {
                $grup->kriteria()->create($kriteriaData);
            }
        }

        foreach ($billing['keringanan'] ?? [] as $ruleData) {
            $jenisTagihan->keringananRules()->create($ruleData);
        }
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['jenis_tagihan' => $message])->withInput();
    }
}
