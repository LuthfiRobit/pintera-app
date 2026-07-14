<?php

namespace App\Http\Controllers\Admin;

use App\Models\DokumenPendaftaran;
use App\Models\HasilSeleksi;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PendaftaranAdminController extends BaseController
{
    use AuthorizesRequests;

    /**
     * Resolve the acting user's effective lembaga scope. Yayasan-scoped
     * actors (lembaga_id is always null on their user row) fall back to
     * whichever lembaga is currently selected via the lembaga-switcher UI;
     * lembaga-scoped actors just use their own fixed lembaga_id. Pendaftaran,
     * DokumenPendaftaran, and HasilSeleksi deliberately do NOT use
     * BelongsToTenant (M2's public unauthenticated routes write them), so
     * every action below must apply this scope manually.
     */
    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    public function index(Request $request): View
    {
        $this->authorize('spmb-pendaftaran.view');

        return view('admin.spmb-pendaftaran.index', [
            'lembagaBelumDipilih' => $this->lembagaId($request) === null,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.view');

        $lembagaId = $this->lembagaId($request);

        if ($lembagaId === null) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 0,
                    'last_page' => 0,
                    'per_page' => 0,
                    'total' => 0,
                ],
            ]);
        }

        $query = Pendaftaran::where('lembaga_id', $lembagaId)
            ->with(['calonMurid', 'jalurPpdb', 'gelombangPpdb'])
            ->withCount([
                'dokumen as dokumen_total',
                'dokumen as dokumen_terverifikasi_count' => fn ($q) => $q->where('status_verifikasi', 'diterima'),
            ]);

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_pendaftaran', 'like', '%'.$search.'%')
                    ->orWhereHas('calonMurid', fn ($cm) => $cm->where('nama_lengkap', 'like', '%'.$search.'%'));
            });
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($gelombangId = $request->integer('gelombang_ppdb_id')) {
            $query->where('gelombang_ppdb_id', $gelombangId);
        }

        if ($jalurId = $request->integer('jalur_ppdb_id')) {
            $query->where('jalur_ppdb_id', $jalurId);
        }

        $sortable = ['submitted_at', 'status'];
        $sort = in_array($request->string('sort')->value(), $sortable, true) ? $request->string('sort')->value() : 'submitted_at';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Pendaftaran $pendaftaran) => [
                'id' => $pendaftaran->id,
                'kode_pendaftaran' => $pendaftaran->kode_pendaftaran,
                'nama_calon_murid' => $pendaftaran->calonMurid->nama_lengkap,
                'jalur' => $pendaftaran->jalurPpdb->nama,
                'gelombang' => $pendaftaran->gelombangPpdb->nama,
                'status' => $pendaftaran->status,
                'dokumen_total' => $pendaftaran->dokumen_total,
                'dokumen_terverifikasi' => $pendaftaran->dokumen_terverifikasi_count,
                'submitted_at' => $pendaftaran->submitted_at->format('d M Y H:i'),
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->authorize('spmb-pendaftaran.view');
        abort_unless($pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $pendaftaran->load([
            'calonMurid.alamat', 'calonMurid.keluarga', 'calonMurid.dataPeriodik', 'calonMurid.dataKhusus',
            'jalurPpdb', 'gelombangPpdb',
            'dokumen.dokumenSyaratPpdb',
            'jawabanFormulir.formulirField',
            'hasilSeleksi.seleksiPpdb.jenisTesMaster',
        ]);

        $seleksiTersedia = \App\Models\SeleksiPpdb::where('jalur_ppdb_id', $pendaftaran->jalur_ppdb_id)
            ->where('gelombang_ppdb_id', $pendaftaran->gelombang_ppdb_id)
            ->with('jenisTesMaster')
            ->get();

        return view('admin.spmb-pendaftaran.show', [
            'pendaftaran' => $pendaftaran,
            'seleksiTersedia' => $seleksiTersedia,
        ]);
    }

    public function verifikasiDokumen(Request $request, Pendaftaran $pendaftaran, DokumenPendaftaran $dokumen): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.verifikasi-dokumen');
        abort_unless($pendaftaran->lembaga_id === $this->lembagaId($request), 404);
        abort_unless($dokumen->pendaftaran_id === $pendaftaran->id, 404);

        $data = $request->validate([
            'status_verifikasi' => ['required', 'in:diterima,ditolak'],
            'catatan_verifikasi' => ['required_if:status_verifikasi,ditolak', 'nullable', 'string', 'max:1000'],
        ]);

        $dokumen->update([
            'status_verifikasi' => $data['status_verifikasi'],
            'catatan_verifikasi' => $data['catatan_verifikasi'] ?? null,
            'diverifikasi_oleh_user_id' => $request->user()->id,
            'diverifikasi_pada' => now(),
        ]);

        return response()->json(['message' => 'Dokumen berhasil diverifikasi.']);
    }

    public function simpanNilai(Request $request, Pendaftaran $pendaftaran): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.nilai-seleksi');
        abort_unless($pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'seleksi_ppdb_id' => ['required', 'integer', 'exists:seleksi_ppdb,id'],
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        HasilSeleksi::updateOrCreate(
            ['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $data['seleksi_ppdb_id']],
            [
                'nilai' => $data['nilai'] ?? null,
                'catatan' => $data['catatan'] ?? null,
                'dinilai_oleh_user_id' => $request->user()->id,
                'dinilai_pada' => now(),
            ]
        );

        return response()->json(['message' => 'Nilai berhasil disimpan.']);
    }

    public function tetapkanKeputusan(Request $request, Pendaftaran $pendaftaran): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.tetapkan-keputusan');
        abort_unless($pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'status' => ['required', 'in:diterima,ditolak'],
            'catatan_keputusan' => ['nullable', 'string', 'max:1000'],
        ]);

        $pendaftaran->update([
            'status' => $data['status'],
            'catatan_keputusan' => $data['catatan_keputusan'] ?? null,
            'ditetapkan_oleh_user_id' => $request->user()->id,
            'ditetapkan_pada' => now(),
        ]);

        return response()->json(['message' => 'Keputusan berhasil ditetapkan.']);
    }

    public function nilaiMassal(Request $request): View
    {
        $this->authorize('spmb-pendaftaran.nilai-seleksi');

        $lembagaId = $this->lembagaId($request);

        if ($lembagaId === null) {
            return view('admin.spmb-pendaftaran.nilai-massal', [
                'daftarSeleksi' => collect(),
                'seleksiTerpilih' => null,
                'pesertaList' => collect(),
                'lembagaBelumDipilih' => true,
            ]);
        }

        $daftarSeleksi = SeleksiPpdb::where('lembaga_id', $lembagaId)
            ->with(['jenisTesMaster', 'jalurPpdb', 'gelombangPpdb'])
            ->get();

        $seleksiTerpilih = null;
        $pesertaList = collect();

        if ($seleksiId = $request->integer('seleksi_ppdb_id')) {
            $seleksiTerpilih = SeleksiPpdb::where('lembaga_id', $lembagaId)->find($seleksiId);

            if ($seleksiTerpilih) {
                $pesertaList = Pendaftaran::where('lembaga_id', $lembagaId)
                    ->where('jalur_ppdb_id', $seleksiTerpilih->jalur_ppdb_id)
                    ->where('gelombang_ppdb_id', $seleksiTerpilih->gelombang_ppdb_id)
                    ->with(['calonMurid', 'hasilSeleksi' => fn ($q) => $q->where('seleksi_ppdb_id', $seleksiId)])
                    ->get();
            }
        }

        return view('admin.spmb-pendaftaran.nilai-massal', [
            'daftarSeleksi' => $daftarSeleksi,
            'seleksiTerpilih' => $seleksiTerpilih,
            'pesertaList' => $pesertaList,
            'lembagaBelumDipilih' => false,
        ]);
    }

    public function simpanNilaiMassal(Request $request): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.nilai-seleksi');

        $lembagaId = $this->lembagaId($request);

        if ($lembagaId === null) {
            return response()->json([
                'message' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menyimpan nilai.',
            ], 422);
        }

        $data = $request->validate([
            'seleksi_ppdb_id' => ['required', 'integer', 'exists:seleksi_ppdb,id'],
            'nilai' => ['required', 'array'],
            'nilai.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $seleksi = SeleksiPpdb::where('lembaga_id', $lembagaId)->findOrFail($data['seleksi_ppdb_id']);

        $pendaftaranIds = Pendaftaran::where('lembaga_id', $lembagaId)
            ->where('jalur_ppdb_id', $seleksi->jalur_ppdb_id)
            ->where('gelombang_ppdb_id', $seleksi->gelombang_ppdb_id)
            ->pluck('id');

        foreach ($data['nilai'] as $pendaftaranId => $nilai) {
            if (! $pendaftaranIds->contains((int) $pendaftaranId)) {
                continue;
            }

            HasilSeleksi::updateOrCreate(
                ['pendaftaran_id' => $pendaftaranId, 'seleksi_ppdb_id' => $seleksi->id],
                ['nilai' => $nilai, 'dinilai_oleh_user_id' => $request->user()->id, 'dinilai_pada' => now()]
            );
        }

        return response()->json(['message' => 'Nilai berhasil disimpan.']);
    }
}
