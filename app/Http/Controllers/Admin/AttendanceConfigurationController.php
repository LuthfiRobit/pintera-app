<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Sdm\Actions\SetAttendanceMethodConfigurationAction;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Models\AttendanceMethodConfiguration;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class AttendanceConfigurationController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kehadiran-sdm.view');

        $lembagaId = $this->resolveLembagaId($request);
        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        $konfigurasi = AttendanceMethodConfiguration::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->get();

        $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->orderBy('nama')->get() : collect();

        return view('admin.kehadiran-sdm.konfigurasi', [
            'methods' => AttendanceMethod::cases(),
            'konfigurasi' => $konfigurasi,
            'titikAbsen' => $titikAbsen,
            'lembagaId' => $lembagaId,
        ]);
    }

    public function updateMetode(Request $request, SetAttendanceMethodConfigurationAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'method' => ['required', 'in:admin,qr'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum mengubah konfigurasi.']);
        }

        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        if ($yayasanId === null) {
            return back()->withErrors(['lembaga_id' => 'Yayasan tidak ditemukan untuk lembaga aktif Anda.']);
        }

        $action->execute($yayasanId, $lembagaId, AttendanceMethod::from($data['method']), (bool) $data['is_enabled']);

        return back()->with('status', 'Konfigurasi metode absensi berhasil diperbarui.');
    }

    public function storeTitik(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate(['nama' => ['required', 'string', 'max:255']]);
        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah titik absen.']);
        }

        AttendancePoint::create(['lembaga_id' => $lembagaId, 'nama' => $data['nama']]);

        return back()->with('status', 'Titik absen berhasil ditambahkan.');
    }

    public function updateTitik(Request $request, AttendancePoint $titik): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $titik->update($data);

        return back()->with('status', 'Titik absen berhasil diperbarui.');
    }

    public function destroyTitik(AttendancePoint $titik): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $titik->delete();

        return back()->with('status', 'Titik absen berhasil dihapus.');
    }

    private function resolveLembagaId(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }

    private function resolveYayasanId(Request $request, ?int $lembagaId): ?int
    {
        return $request->user()->yayasan_id ?? ($lembagaId ? Lembaga::find($lembagaId)?->yayasan_id : null);
    }
}
