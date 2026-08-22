<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Sdm\Actions\ScanQrAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\ScanQrAttendanceData;
use App\Domains\Sdm\Exceptions\InvalidQrTokenException;
use App\Domains\Sdm\Exceptions\QrTokenLembagaMismatchException;
use App\Domains\Sdm\Models\AttendancePoint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class AttendanceQrScanController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.catat');

        $lembagaId = $this->resolveLembagaId($request);
        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->where('is_active', true)->orderBy('nama')->get() : collect();

        return view('admin.kehadiran-sdm.scan', ['titikAbsen' => $titikAbsen]);
    }

    public function store(Request $request, ScanQrAttendanceAction $action): JsonResponse
    {
        $this->authorize('kehadiran-sdm.catat');

        $data = $request->validate([
            'token' => ['required', 'string'],
            'arah' => ['required', 'in:masuk,pulang'],
            'attendance_point_id' => ['nullable', 'integer', 'exists:attendance_points,id'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return response()->json(['message' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.'], 422);
        }

        try {
            $event = $action->execute(new ScanQrAttendanceData(
                token: $data['token'],
                arah: $data['arah'],
                lembagaId: $lembagaId,
                dicatatOlehUserId: $request->user()->id,
                attendancePointId: $data['attendance_point_id'] ?? null,
            ));
        } catch (InvalidQrTokenException|QrTokenLembagaMismatchException|\App\Domains\Sdm\Exceptions\AttendanceOnHolidayException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Kehadiran berhasil dicatat: '.$event->pegawai->nama,
        ]);
    }

    private function resolveLembagaId(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }
}
