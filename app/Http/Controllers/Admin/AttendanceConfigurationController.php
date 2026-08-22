<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Sdm\Actions\CopyKalenderAkademikNasionalAction;
use App\Domains\Sdm\Actions\SetAttendanceMethodConfigurationAction;
use App\Domains\Sdm\Actions\SetHariLiburMingguanSdmAction;
use App\Domains\Sdm\DataTransferObjects\HariKerjaSdmData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\AttendanceMethodConfiguration;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
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

        $lembaga = $lembagaId ? Lembaga::find($lembagaId) : null;

        $kalenderEntriList = $yayasanId ? KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderBy('tanggal')
            ->get() : collect();

        return view('admin.kehadiran-sdm.konfigurasi', [
            'methods' => AttendanceMethod::cases(),
            'konfigurasi' => $konfigurasi,
            'titikAbsen' => $titikAbsen,
            'lembagaId' => $lembagaId,
            'lembaga' => $lembaga,
            'kalenderEntriList' => $kalenderEntriList,
            'tipeKalenderOptions' => TipeKalenderKerjaSdm::cases(),
            'bolehKelolaNasional' => $request->user()->widestScopeLevel() === 'yayasan',
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

    public function updateHariKerja(Request $request, SetHariLiburMingguanSdmAction $action): JsonResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'hari_kerja' => ['present', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return response()->json(['message' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.'], 422);
        }

        $lembaga = Lembaga::findOrFail($lembagaId);
        $lembaga = $action->execute($lembaga, new HariKerjaSdmData(hariKerja: $data['hari_kerja']));

        return response()->json(['data' => ['hari_libur_mingguan_sdm' => $lembaga->hari_libur_mingguan_sdm]]);
    }

    public function storeKalenderEntri(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'is_nasional' => ['nullable', 'boolean'],
        ]);

        $isNasional = (bool) ($data['is_nasional'] ?? false);

        if ($isNasional && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh membuat entri kalender nasional.');
        }

        $lembagaId = $isNasional ? null : $this->resolveLembagaId($request);

        if (! $isNasional && $lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah entri kalender.']);
        }

        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        KalenderKerjaSdm::create([
            'yayasan_id' => $yayasanId,
            'lembaga_id' => $lembagaId,
            'tanggal' => $data['tanggal'],
            'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
            'nama' => $data['nama'],
            'tipe' => $data['tipe'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        return back()->with('status', 'Entri kalender kerja berhasil ditambahkan.');
    }

    public function updateKalenderEntri(Request $request, KalenderKerjaSdm $entri): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($entri->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh mengubah entri kalender nasional.');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $entri->update($data);

        return back()->with('status', 'Entri kalender kerja berhasil diperbarui.');
    }

    public function destroyKalenderEntri(Request $request, KalenderKerjaSdm $entri): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($entri->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh menghapus entri kalender nasional.');
        }

        $entri->delete();

        return back()->with('status', 'Entri kalender kerja berhasil dihapus.');
    }

    public function kalenderSalinTersedia(Request $request): JsonResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');
        abort_unless($request->user()->widestScopeLevel() === 'yayasan', 403);

        $yayasanId = $request->user()->yayasan_id;

        $sudahDisalinKey = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $yayasanId)
            ->get(['tanggal', 'nama'])
            ->map(fn ($entri) => $entri->tanggal->toDateString().'|'.$entri->nama);

        $tersedia = KalenderAkademik::nasional()
            ->orderBy('tanggal')
            ->get(['id', 'nama', 'tanggal', 'tipe'])
            ->reject(fn ($entri) => $sudahDisalinKey->contains($entri->tanggal->toDateString().'|'.$entri->nama))
            ->values();

        return response()->json(['items' => $tersedia]);
    }

    public function kalenderSalin(Request $request, CopyKalenderAkademikNasionalAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');
        abort_unless($request->user()->widestScopeLevel() === 'yayasan', 403);

        $data = $request->validate([
            'kalender_akademik_ids' => ['required', 'array', 'min:1'],
            'kalender_akademik_ids.*' => ['integer'],
        ]);

        $disalin = $action->execute($request->user()->yayasan_id, $data['kalender_akademik_ids']);

        return back()->with('status', "{$disalin->count()} entri kalender berhasil disalin dari kalender akademik.");
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
