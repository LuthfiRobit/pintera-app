<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Actions\Piket\RegenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Guru;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JadwalPiketMingguanController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    public function index(Request $request): View
    {
        $this->authorize('piket.kelola');

        $lembagaId = $this->resolveLembagaIdAktif($request);

        return view('portals.lembaga.akademik.piket-guru.index', [
            'jadwalList' => JadwalPiketMingguan::where('lembaga_id', $lembagaId)->with(['guru', 'semester'])->orderBy('hari')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('piket.kelola');

        $lembagaId = $this->resolveLembagaIdAktif($request);

        return view('portals.lembaga.akademik.piket-guru.create', [
            'guruList' => Guru::where('lembaga_id', $lembagaId)->orderBy('nama_lengkap')->get(),
            'semesterAktif' => Semester::where('lembaga_id', $lembagaId)->where('status_aktif', true)->first(),
        ]);
    }

    public function store(Request $request, GenerateJadwalPiketHarianAction $generateAction, RegenerateJadwalPiketHarianAction $regenerateAction): RedirectResponse
    {
        $this->authorize('piket.kelola');

        $lembagaId = $this->resolveLembagaIdAktif($request);

        $data = $request->validate([
            'guru_id' => ['required', 'integer', 'exists:guru,id'],
            'hari' => ['required', 'integer', 'between:0,6'],
            'semester_id' => ['required', 'integer', 'exists:semester,id'],
        ]);

        $jadwal = JadwalPiketMingguan::create([
            'lembaga_id' => $lembagaId,
            'guru_id' => $data['guru_id'],
            'hari' => $data['hari'],
            'semester_id' => $data['semester_id'],
            'dibuat_oleh_user_id' => $request->user()->id,
        ]);

        $semester = Semester::findOrFail($data['semester_id']);
        $sudahAdaPiketHarian = PiketHarian::where('lembaga_id', $lembagaId)
            ->where('tanggal', '>=', $semester->tanggal_mulai)
            ->exists();

        if ($sudahAdaPiketHarian) {
            $regenerateAction->execute($lembagaId, $data['semester_id']);
        } else {
            $generateAction->execute($jadwal);
        }

        return redirect()->route('admin.piket-guru.index')->with('status', 'Jadwal piket mingguan berhasil disimpan.');
    }

    public function edit(JadwalPiketMingguan $jadwalPiketMingguan, Request $request): View
    {
        $this->authorize('piket.kelola');

        $lembagaId = $this->resolveLembagaIdAktif($request);

        return view('portals.lembaga.akademik.piket-guru.edit', [
            'jadwal' => $jadwalPiketMingguan,
            'guruList' => Guru::where('lembaga_id', $lembagaId)->orderBy('nama_lengkap')->get(),
        ]);
    }

    public function update(Request $request, JadwalPiketMingguan $jadwalPiketMingguan, RegenerateJadwalPiketHarianAction $regenerateAction): RedirectResponse
    {
        $this->authorize('piket.kelola');

        $data = $request->validate([
            'guru_id' => ['required', 'integer', 'exists:guru,id'],
            'hari' => ['required', 'integer', 'between:0,6'],
        ]);

        $jadwalPiketMingguan->update($data);

        // Baris ini SUDAH ADA sebelumnya (sedang diedit) -> lembaga PASTI sudah punya PiketHarian
        // untuk semester ini -> SELALU Regenerate, tidak pernah Generate ulang dari nol.
        $regenerateAction->execute($jadwalPiketMingguan->lembaga_id, $jadwalPiketMingguan->semester_id);

        return redirect()->route('admin.piket-guru.index')->with('status', 'Jadwal piket mingguan berhasil diperbarui.');
    }

    public function destroy(JadwalPiketMingguan $jadwalPiketMingguan, RegenerateJadwalPiketHarianAction $regenerateAction): RedirectResponse
    {
        $this->authorize('piket.kelola');

        $lembagaId = $jadwalPiketMingguan->lembaga_id;
        $semesterId = $jadwalPiketMingguan->semester_id;
        $jadwalPiketMingguan->delete();

        $regenerateAction->execute($lembagaId, $semesterId);

        return redirect()->route('admin.piket-guru.index')->with('status', 'Jadwal piket mingguan berhasil dihapus.');
    }

    private function resolveLembagaIdAktif(Request $request): int
    {
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;

        abort_if($lembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.');

        return $lembagaId;
    }
}
