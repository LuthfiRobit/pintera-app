<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Sdm\Actions\RecordManualAttendanceAction;
use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Exceptions\AttendanceOnHolidayException;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Karyawan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class AttendanceController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.view');

        $tanggal = $request->query('tanggal', now()->toDateString());

        $recordList = AttendanceRecord::with('pegawai')
            ->whereDate('tanggal', $tanggal)
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('admin.kehadiran-sdm.index', [
            'recordList' => $recordList,
            'tanggal' => $tanggal,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('kehadiran-sdm.catat');

        $lembagaId = $this->resolveLembagaId($request);

        $guruList = $lembagaId ? Guru::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']) : collect();
        $karyawanList = $lembagaId ? Karyawan::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']) : collect();
        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->where('is_active', true)->orderBy('nama')->get() : collect();

        return view('admin.kehadiran-sdm.create', [
            'guruList' => $guruList,
            'karyawanList' => $karyawanList,
            'titikAbsen' => $titikAbsen,
            'statusOptions' => AttendanceStatus::cases(),
        ]);
    }

    public function store(Request $request, RecordManualAttendanceAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.catat');

        $data = $request->validate([
            'pegawai_tipe' => ['required', 'in:guru,karyawan'],
            'pegawai_id' => ['required', 'integer'],
            'arah' => ['required', 'in:masuk,pulang'],
            'status' => ['required', 'in:hadir,izin,sakit,alpa'],
            'waktu' => ['required', 'date'],
            'attendance_point_id' => ['nullable', 'integer', 'exists:attendance_points,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'override_hari_libur' => ['nullable', 'boolean'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.'])->withInput();
        }

        $pegawaiModel = $data['pegawai_tipe'] === 'guru' ? Guru::class : Karyawan::class;
        $pegawai = $pegawaiModel::find($data['pegawai_id']);

        abort_if($pegawai === null || (int) $pegawai->lembaga_id !== $lembagaId, 404, 'Pegawai tidak ditemukan di lembaga aktif Anda.');

        try {
            $action->execute($pegawai, new RecordManualAttendanceData(
                lembagaId: $lembagaId,
                arah: $data['arah'],
                status: AttendanceStatus::from($data['status']),
                waktu: CarbonImmutable::parse($data['waktu']),
                dicatatOlehUserId: $request->user()->id,
                attendancePointId: $data['attendance_point_id'] ?? null,
                catatan: $data['catatan'] ?? null,
                overrideHariLibur: (bool) ($data['override_hari_libur'] ?? false),
            ));
        } catch (AttendanceOnHolidayException $exception) {
            return back()->withErrors(['tanggal' => $exception->getMessage().' Centang "Tetap catat meski hari libur" kalau ini disengaja.'])->withInput();
        }

        return redirect()->route('admin.kehadiran-sdm.index')->with('status', 'Kehadiran berhasil dicatat.');
    }

    private function resolveLembagaId(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }
}
