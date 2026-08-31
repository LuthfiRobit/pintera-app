<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Sdm\Actions\AssignShiftAction;
use App\Domains\Sdm\Actions\CopyKalenderAkademikNasionalAction;
use App\Domains\Sdm\Actions\SetAttendanceMethodConfigurationAction;
use App\Domains\Sdm\Actions\SetHariLiburMingguanSdmAction;
use App\Domains\Sdm\DataTransferObjects\HariKerjaSdmData;
use App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Exceptions\ShiftAssignmentOverlapException;
use App\Domains\Sdm\Models\AttendanceMethodConfiguration;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Domains\Sdm\Models\JenisShift;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Models\Guru;
use App\Models\Karyawan;
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

    private const JENIS_PTK_OPTIONS = [
        'guru_kelas' => 'Guru Kelas',
        'guru_mapel' => 'Guru Mapel',
        'kepala_sekolah' => 'Kepala Sekolah',
        'tenaga_administrasi' => 'Tenaga Administrasi',
        'guru_bk' => 'Guru BK',
    ];

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

        $policyList = $yayasanId ? AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->with('jenisKaryawan')
            ->get() : collect();

        $jenisShiftList = $yayasanId ? JenisShift::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderBy('nama')
            ->get() : collect();

        $kuotaCutiList = $yayasanId ? KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderByRaw('lembaga_id IS NULL')
            ->get() : collect();

        $penugasanShiftList = $lembagaId ? PenugasanShift::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $lembagaId)
            ->with(['pegawai', 'jenisShift'])
            ->orderByDesc('tanggal_mulai')
            ->get() : collect();

        $guruList = $lembagaId
            ? Guru::where('lembaga_id', $lembagaId)->with('person')->orderByNama()->get(['guru.id', 'guru.nama', 'guru.nip', 'guru.nuptk', 'guru.person_id'])
                ->map(fn ($g) => ['id' => (string) $g->id, 'nama' => $g->nama, 'subtext' => $g->nip ? 'NIP: '.$g->nip : ($g->nuptk ? 'NUPTK: '.$g->nuptk : '')])->values()
            : collect();
        $karyawanList = $lembagaId
            ? Karyawan::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama', 'email'])
                ->map(fn ($k) => ['id' => (string) $k->id, 'nama' => $k->nama, 'subtext' => $k->email ?? ''])->values()
            : collect();

        return view('admin.kehadiran-sdm.konfigurasi', [
            'methods' => AttendanceMethod::cases(),
            'konfigurasi' => $konfigurasi,
            'titikAbsen' => $titikAbsen,
            'lembagaId' => $lembagaId,
            'lembaga' => $lembaga,
            'kalenderEntriList' => $kalenderEntriList,
            'tipeKalenderOptions' => TipeKalenderKerjaSdm::cases(),
            'bolehKelolaNasional' => $request->user()->widestScopeLevel() === 'yayasan',
            'policyList' => $policyList,
            'jenisPtkOptions' => self::JENIS_PTK_OPTIONS,
            'jenisKaryawanList' => JenisKaryawanMaster::orderBy('nama')->get(),
            'jenisShiftList' => $jenisShiftList,
            'penugasanShiftList' => $penugasanShiftList,
            'guruList' => $guruList,
            'karyawanList' => $karyawanList,
            'kuotaCutiList' => $kuotaCutiList,
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

    public function storePolicy(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'kategori_tipe' => ['required', 'in:guru,karyawan'],
            'jenis_ptk' => ['required_if:kategori_tipe,guru', 'nullable', 'in:guru_kelas,guru_mapel,kepala_sekolah,tenaga_administrasi,guru_bk'],
            'jenis_karyawan_id' => ['required_if:kategori_tipe,karyawan', 'nullable', 'integer', 'exists:jenis_karyawan_master,id'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'toleransi_menit' => ['required', 'integer', 'min:0'],
            'hari_kerja' => ['nullable', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
            'is_nasional' => ['nullable', 'boolean'],
        ]);

        $isNasional = (bool) ($data['is_nasional'] ?? false);

        if ($isNasional && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh membuat Attendance Policy nasional.');
        }

        $lembagaId = $isNasional ? null : $this->resolveLembagaId($request);

        if (! $isNasional && $lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah Attendance Policy.']);
        }

        $yayasanId = $this->resolveYayasanId($request, $lembagaId);
        $jenisPtk = $data['kategori_tipe'] === 'guru' ? $data['jenis_ptk'] : null;
        $jenisKaryawanId = $data['kategori_tipe'] === 'karyawan' ? $data['jenis_karyawan_id'] : null;

        $sudahAda = AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where('lembaga_id', $lembagaId)
            ->where('jenis_ptk', $jenisPtk)
            ->where('jenis_karyawan_id', $jenisKaryawanId)
            ->exists();

        if ($sudahAda) {
            return back()->withErrors(['kategori_tipe' => 'Attendance Policy untuk kategori dan scope ini sudah ada. Edit yang sudah ada, jangan buat baru.']);
        }

        AttendancePolicy::create([
            'yayasan_id' => $yayasanId,
            'lembaga_id' => $lembagaId,
            'jenis_ptk' => $jenisPtk,
            'jenis_karyawan_id' => $jenisKaryawanId,
            'jam_masuk' => $data['jam_masuk'],
            'jam_pulang' => $data['jam_pulang'] ?? null,
            'toleransi_menit' => $data['toleransi_menit'],
            'hari_kerja' => $data['hari_kerja'] ?? null,
        ]);

        return back()->with('status', 'Attendance Policy berhasil ditambahkan.');
    }

    public function updatePolicy(Request $request, AttendancePolicy $policy): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($policy->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh mengubah Attendance Policy nasional.');
        }

        $data = $request->validate([
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'toleransi_menit' => ['required', 'integer', 'min:0'],
            'hari_kerja' => ['nullable', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
        ]);

        $policy->update([
            'jam_masuk' => $data['jam_masuk'],
            'jam_pulang' => $data['jam_pulang'] ?? null,
            'toleransi_menit' => $data['toleransi_menit'],
            'hari_kerja' => $data['hari_kerja'] ?? null,
        ]);

        return back()->with('status', 'Attendance Policy berhasil diperbarui.');
    }

    public function destroyPolicy(Request $request, AttendancePolicy $policy): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($policy->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh menghapus Attendance Policy nasional.');
        }

        $policy->delete();

        return back()->with('status', 'Attendance Policy berhasil dihapus.');
    }

    public function storeJenisShift(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i'],
            'is_nasional' => ['nullable', 'boolean'],
        ]);

        $isNasional = (bool) ($data['is_nasional'] ?? false);

        if ($isNasional && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh membuat jenis shift nasional.');
        }

        $lembagaId = $isNasional ? null : $this->resolveLembagaId($request);

        if (! $isNasional && $lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis shift.']);
        }

        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        JenisShift::create([
            'yayasan_id' => $yayasanId,
            'lembaga_id' => $lembagaId,
            'nama' => $data['nama'],
            'jam_masuk' => $data['jam_masuk'],
            'jam_pulang' => $data['jam_pulang'],
        ]);

        return back()->with('status', 'Jenis shift berhasil ditambahkan.');
    }

    public function updateJenisShift(Request $request, JenisShift $jenisShift): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($jenisShift->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh mengubah jenis shift nasional.');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i'],
        ]);

        $jenisShift->update($data);

        return back()->with('status', 'Jenis shift berhasil diperbarui.');
    }

    public function destroyJenisShift(Request $request, JenisShift $jenisShift): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($jenisShift->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh menghapus jenis shift nasional.');
        }

        $jenisShift->delete();

        return back()->with('status', 'Jenis shift berhasil dihapus.');
    }

    public function storePenugasanShift(Request $request, AssignShiftAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'pegawai_tipe' => ['required', 'in:guru,karyawan'],
            'pegawai_id' => ['required', 'integer'],
            'jenis_shift_id' => ['required', 'integer', 'exists:jenis_shift,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'hari_kerja' => ['nullable', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.']);
        }

        $pegawaiModel = $data['pegawai_tipe'] === 'guru' ? Guru::class : Karyawan::class;
        $pegawai = $pegawaiModel::find($data['pegawai_id']);

        abort_if($pegawai === null || (int) $pegawai->lembaga_id !== $lembagaId, 404, 'Pegawai tidak ditemukan di lembaga aktif Anda.');

        try {
            $action->execute($pegawai, new ShiftAssignmentData(
                lembagaId: $lembagaId,
                jenisShiftId: $data['jenis_shift_id'],
                tanggalMulai: $data['tanggal_mulai'],
                tanggalSelesai: $data['tanggal_selesai'] ?? null,
                hariKerja: $data['hari_kerja'] ?? null,
            ));
        } catch (ShiftAssignmentOverlapException $exception) {
            return back()->withErrors(['tanggal_mulai' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Penugasan shift berhasil ditambahkan.');
    }

    public function updatePenugasanShift(Request $request, PenugasanShift $penugasanShift, AssignShiftAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'jenis_shift_id' => ['required', 'integer', 'exists:jenis_shift,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'hari_kerja' => ['nullable', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
        ]);

        try {
            $action->execute($penugasanShift->pegawai, new ShiftAssignmentData(
                lembagaId: $penugasanShift->lembaga_id,
                jenisShiftId: $data['jenis_shift_id'],
                tanggalMulai: $data['tanggal_mulai'],
                tanggalSelesai: $data['tanggal_selesai'] ?? null,
                hariKerja: $data['hari_kerja'] ?? null,
            ), excludingId: $penugasanShift->id);
        } catch (ShiftAssignmentOverlapException $exception) {
            return back()->withErrors(['tanggal_mulai' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Penugasan shift berhasil diperbarui.');
    }

    public function destroyPenugasanShift(PenugasanShift $penugasanShift): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $penugasanShift->delete();

        return back()->with('status', 'Penugasan shift berhasil dihapus.');
    }

    public function storeKuotaCuti(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'jatah_hari_per_tahun' => ['required', 'integer', 'min:0'],
            'is_nasional' => ['nullable', 'boolean'],
        ]);

        $isNasional = (bool) ($data['is_nasional'] ?? false);

        if ($isNasional && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh membuat kuota cuti nasional.');
        }

        $lembagaId = $isNasional ? null : $this->resolveLembagaId($request);

        if (! $isNasional && $lembagaId === null) {
            return back()->withErrors(['jatah_hari_per_tahun' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah kuota cuti.']);
        }

        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        $sudahAda = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where('lembaga_id', $lembagaId)
            ->whereNull('jenis_ptk')
            ->whereNull('jenis_karyawan_id')
            ->exists();

        if ($sudahAda) {
            return back()->withErrors(['jatah_hari_per_tahun' => 'Kuota cuti untuk scope ini sudah ada. Edit yang sudah ada, jangan buat baru.']);
        }

        KuotaCutiConfig::create([
            'yayasan_id' => $yayasanId,
            'lembaga_id' => $lembagaId,
            'jenis_ptk' => null,
            'jenis_karyawan_id' => null,
            'jatah_hari_per_tahun' => $data['jatah_hari_per_tahun'],
        ]);

        return back()->with('status', 'Kuota cuti berhasil ditambahkan.');
    }

    public function updateKuotaCuti(Request $request, KuotaCutiConfig $kuotaCuti): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($kuotaCuti->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh mengubah kuota cuti nasional.');
        }

        $data = $request->validate([
            'jatah_hari_per_tahun' => ['required', 'integer', 'min:0'],
        ]);

        $kuotaCuti->update(['jatah_hari_per_tahun' => $data['jatah_hari_per_tahun']]);

        return back()->with('status', 'Kuota cuti berhasil diperbarui.');
    }

    public function destroyKuotaCuti(Request $request, KuotaCutiConfig $kuotaCuti): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($kuotaCuti->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh menghapus kuota cuti nasional.');
        }

        $kuotaCuti->delete();

        return back()->with('status', 'Kuota cuti berhasil dihapus.');
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
