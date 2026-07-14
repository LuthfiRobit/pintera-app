<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SkPpdbController extends BaseController
{
    use AuthorizesRequests;

    /**
     * Resolve the acting user's effective lembaga scope. Yayasan-scoped
     * actors (lembaga_id is always null on their user row) fall back to
     * whichever lembaga is currently selected via the lembaga-switcher UI;
     * lembaga-scoped actors just use their own fixed lembaga_id. Pendaftaran
     * deliberately does NOT use BelongsToTenant, so this must be applied
     * manually here too.
     */
    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    public function create(Request $request): View
    {
        $this->authorize('spmb-pendaftaran.terbitkan-sk');

        $lembagaId = $this->lembagaId($request);

        if ($lembagaId === null) {
            return view('admin.spmb-pendaftaran.terbitkan-sk', [
                'gelombangList' => collect(),
                'gelombangTerpilih' => null,
                'ringkasan' => null,
                'lembagaBelumDipilih' => true,
            ]);
        }

        $gelombangList = GelombangPpdb::where('lembaga_id', $lembagaId)->get();
        $gelombangTerpilih = null;
        $ringkasan = null;

        if ($gelombangId = $request->integer('gelombang_ppdb_id')) {
            $gelombangTerpilih = GelombangPpdb::where('lembaga_id', $lembagaId)->find($gelombangId);

            if ($gelombangTerpilih) {
                $ringkasan = [
                    'total' => Pendaftaran::where('gelombang_ppdb_id', $gelombangId)->where('lembaga_id', $lembagaId)->count(),
                    // "Final & belum tercakup SK" — only these will end up in the SK about to be
                    // generated. Candidates already linked to an earlier SK for this gelombang are
                    // deliberately excluded here too, so the count shown matches what store() will
                    // actually link.
                    'final' => Pendaftaran::where('gelombang_ppdb_id', $gelombangId)
                        ->where('lembaga_id', $lembagaId)
                        ->whereIn('status', ['diterima', 'ditolak'])
                        ->whereNull('sk_ppdb_id')
                        ->count(),
                    'belum_final' => Pendaftaran::where('gelombang_ppdb_id', $gelombangId)->where('lembaga_id', $lembagaId)->where('status', 'menunggu_verifikasi')->count(),
                ];
            }
        }

        return view('admin.spmb-pendaftaran.terbitkan-sk', [
            'gelombangList' => $gelombangList,
            'gelombangTerpilih' => $gelombangTerpilih,
            'ringkasan' => $ringkasan,
            'lembagaBelumDipilih' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('spmb-pendaftaran.terbitkan-sk');

        $lembagaId = $this->lembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menerbitkan SK.'])->withInput();
        }

        $data = $request->validate([
            'gelombang_ppdb_id' => ['required', 'integer', 'exists:gelombang_ppdb,id'],
            'nomor_sk' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($lembagaId) {
                    if (SkPpdb::where('lembaga_id', $lembagaId)->where('nomor_sk', $value)->exists()) {
                        $fail('Nomor SK ini sudah pernah dipakai untuk lembaga Anda.');
                    }
                },
            ],
            'tanggal_terbit' => ['required', 'date'],
        ]);

        $gelombang = GelombangPpdb::where('lembaga_id', $lembagaId)->findOrFail($data['gelombang_ppdb_id']);

        // Only candidates that are (a) finalized, (b) in this gelombang, (c) in the acting
        // user's lembaga, AND (d) not already linked to an earlier sk_ppdb row are eligible.
        // (d) is what makes a "susulan" SK issuance safe: a pendaftaran already covered by a
        // prior SK for this same gelombang must never be re-listed or re-linked here — each
        // Pendaftaran is linked to exactly one SK, whichever one actually covered it.
        $pendaftaranFinal = Pendaftaran::where('gelombang_ppdb_id', $gelombang->id)
            ->where('lembaga_id', $lembagaId)
            ->whereIn('status', ['diterima', 'ditolak'])
            ->whereNull('sk_ppdb_id')
            ->with('calonMurid')
            ->get();

        $sk = SkPpdb::create([
            'gelombang_ppdb_id' => $gelombang->id,
            'lembaga_id' => $lembagaId,
            'nomor_sk' => $data['nomor_sk'],
            'tanggal_terbit' => $data['tanggal_terbit'],
            'diterbitkan_oleh_user_id' => $request->user()->id,
            'file_path' => '',
        ]);

        $pdf = Pdf::loadView('pdf.sk-ppdb', [
            'sk' => $sk,
            'gelombang' => $gelombang,
            // Not $request->user()->lembaga: a yayasan-scoped actor's own user row has
            // lembaga_id = null, so that relation would be null even though $gelombang
            // (and therefore this SK) is correctly scoped to the active lembaga above.
            'lembaga' => $gelombang->lembaga,
            'pendaftaranFinal' => $pendaftaranFinal,
            'diterbitkanOleh' => $request->user(),
        ]);

        $fileName = 'sk/'.$sk->id.'-sk-ppdb.pdf';
        Storage::disk('public')->put($fileName, $pdf->output());
        $sk->update(['file_path' => $fileName]);

        // Link exactly the set of Pendaftaran rows that were actually put in the PDF above —
        // not a fresh re-query by status/gelombang, which would also match rows already linked
        // to a prior SK and silently steal them onto this one.
        Pendaftaran::whereIn('id', $pendaftaranFinal->pluck('id'))
            ->update(['sk_ppdb_id' => $sk->id]);

        return redirect()->route('admin.spmb-pendaftaran.index')->with('status', 'SK berhasil diterbitkan.');
    }
}
