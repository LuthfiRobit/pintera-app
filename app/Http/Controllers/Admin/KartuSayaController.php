<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\KartuSiswa\GenerateUlangKartuQrSiswaAction;
use App\Domains\Akademik\Actions\KartuSiswa\GetOrCreateKartuQrSiswaAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KartuSayaController extends Controller
{
    public function index(Request $request, GetOrCreateKartuQrSiswaAction $action): View
    {
        $siswa = $request->user()->siswa;
        abort_unless($siswa !== null, 403, 'Akun Anda tidak terhubung ke data siswa.');

        $kartu = $action->execute($siswa);

        return view('admin.siswa-akademik.kartu-saya', ['kartu' => $kartu]);
    }

    public function generateUlang(Request $request, GenerateUlangKartuQrSiswaAction $action): RedirectResponse
    {
        $siswa = $request->user()->siswa;
        abort_unless($siswa !== null, 403, 'Akun Anda tidak terhubung ke data siswa.');

        $action->execute($siswa);

        return redirect()->route('admin.kartu-saya.index')->with('status', 'Kode QR berhasil dibuat ulang. Kode lama sudah tidak berlaku.');
    }
}
