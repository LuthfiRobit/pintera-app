<?php
// app/Http/Controllers/Portal/Auth/RegisteredAkunController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class RegisteredAkunController extends BaseController
{
    public function create(): View
    {
        $lembagaId = session('spmb_pilihan.lembaga_id');
        $jalurId = session('spmb_pilihan.jalur_id');

        $lembaga = null;
        $jalurTerpilih = null;
        $jalurLain = collect();

        if ($lembagaId && $jalurId) {
            $lembaga = Lembaga::find($lembagaId);
            $jalurTerpilih = JalurPpdb::find($jalurId);

            if ($lembaga && $jalurTerpilih) {
                $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
                $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'pendaftaran')->first();

                $jalurLain = $tahunAjaranAktif
                    ? JalurPpdb::where('lembaga_id', $lembaga->id)
                        ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                        ->where('status_aktif', true)
                        ->orderBy('id')
                        ->get()
                        ->map(function (JalurPpdb $jalur) use ($jalurTerpilih, $jenisPendaftaran) {
                            $nominal = $jenisPendaftaran
                                ? NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)->where('jalur_ppdb_id', $jalur->id)->first()
                                : null;

                            return [
                                'jalur' => $jalur,
                                'selected' => $jalur->id === $jalurTerpilih->id,
                                'nominal' => $nominal,
                            ];
                        })
                    : collect();
            } else {
                $lembaga = null;
                $jalurTerpilih = null;
            }
        }

        return view('portal.auth.register', [
            'lembaga' => $lembaga,
            'jalurTerpilih' => $jalurTerpilih,
            'jalurLain' => $jalurLain,
        ]);
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:akun_pendaftar,email'],
            'no_hp_wa' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()],
            'terms' => ['required', 'accepted'],
        ])->validate();

        AkunPendaftar::create([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'no_hp_wa' => $data['no_hp_wa'],
            'password' => $data['password'],
        ]);

        $otpService->kirim($data['email']);

        session(['portal_register_email_pending' => $data['email']]);

        return redirect()->route('portal.verifikasi-otp');
    }

    public function gantiJalur(Request $request, JalurPpdb $jalur): RedirectResponse
    {
        abort_unless((int) $jalur->lembaga_id === (int) session('spmb_pilihan.lembaga_id'), 404);

        $request->session()->put('spmb_pilihan.jalur_id', $jalur->id);

        return redirect()->route('spmb.register');
    }
}
