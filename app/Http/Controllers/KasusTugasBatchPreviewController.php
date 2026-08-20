<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Services\TugasBatchGenerator;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Http\Requests\PreviewKasusTugasBatchRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class KasusTugasBatchPreviewController extends BaseController
{
    use AuthorizesRequests;

    public function preview(PreviewKasusTugasBatchRequest $request, Kasus $kasus, TugasBatchGenerator $generator): JsonResponse
    {
        $this->authorize('kasus.view');
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validated();

        [$tanggalPengumpulanBulanan, $akhirBulan] = $generator->parseTanggalPengumpulanBulanan($data['tanggal_pengumpulan_bulanan'] ?? null);

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
}
