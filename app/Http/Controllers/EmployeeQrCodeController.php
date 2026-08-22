<?php

namespace App\Http\Controllers;

use App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction;
use App\Models\Guru;
use App\Models\Karyawan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class EmployeeQrCodeController extends BaseController
{
    use AuthorizesRequests;

    public function show(Request $request): View
    {
        $this->authorize('kehadiran-sdm.lihat-qr-sendiri');

        $pegawai = $this->resolvePegawai($request);
        abort_if($pegawai === null, 404, 'Data kepegawaian Anda tidak ditemukan.');

        return view('sdm.qr-saya', ['pegawai' => $pegawai, 'qrCode' => $pegawai->employeeQrCode]);
    }

    public function generate(Request $request, GenerateEmployeeQrTokenAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.lihat-qr-sendiri');

        $pegawai = $this->resolvePegawai($request);
        abort_if($pegawai === null, 404, 'Data kepegawaian Anda tidak ditemukan.');

        $action->execute($pegawai);

        return back()->with('status', 'QR kehadiran Anda berhasil diperbarui.');
    }

    private function resolvePegawai(Request $request): Guru|Karyawan|null
    {
        $userId = $request->user()->id;

        return Guru::where('user_id', $userId)->first() ?? Karyawan::where('user_id', $userId)->first();
    }
}
