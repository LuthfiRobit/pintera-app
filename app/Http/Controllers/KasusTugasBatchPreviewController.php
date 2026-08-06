<?php
// app/Http/Controllers/KasusTugasBatchPreviewController.php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKasusTugasBatchRequest;
use App\Models\Kasus;
use App\Models\Scopes\TenantScope;
use App\Services\TugasBatchGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class KasusTugasBatchPreviewController extends BaseController
{
    use AuthorizesRequests;

    public function preview(StoreKasusTugasBatchRequest $request, Kasus $kasus, TugasBatchGenerator $generator): JsonResponse
    {
        $this->authorize('kasus.view');
        $this->assertKonselorPemegangKasus($kasus);

        $data = $request->validated();

        $tanggalPengumpulanBulanan = null;
        $akhirBulan = false;
        if (($data['tanggal_pengumpulan_bulanan'] ?? null) === 'akhir_bulan') {
            $akhirBulan = true;
        } elseif (! empty($data['tanggal_pengumpulan_bulanan'])) {
            $tanggalPengumpulanBulanan = (int) $data['tanggal_pengumpulan_bulanan'];
        }

        $tanggalMulai = Carbon::parse($data['tanggal_mulai']);
        $tanggalSelesai = Carbon::parse($data['tanggal_selesai']);

        $frekuensiAkhir = $generator->tentukanFrekuensiAkhir($data['frekuensi'], $tanggalMulai, $tanggalSelesai);
        $barisTanggal = $generator->generate($data['frekuensi'], $tanggalMulai, $tanggalSelesai, $tanggalPengumpulanBulanan, $akhirBulan);

        return response()->json([
            'frekuensi_akhir' => $frekuensiAkhir,
            'jumlah_baris' => $barisTanggal->count(),
            'baris' => $barisTanggal->map(fn ($baris) => [
                'mulai_pada' => $baris['mulai_pada']->toDateString(),
                'batas_selesai_pada' => $baris['batas_selesai_pada']->toDateString(),
            ])->values(),
        ]);
    }

    private function assertKonselorPemegangKasus(Kasus $kasus): void
    {
        $user = auth()->user();
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);

        abort_unless($isKonselor, 403);
    }
}
