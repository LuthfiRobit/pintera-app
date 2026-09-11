<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Guru;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;

class PiketHarianController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('piket.kelola');

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            return redirect()->route('admin.piket-guru.index')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.'])
                ->withInput();
        }

        $data = $request->validate([
            'guru_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
        ]);

        $guruValid = Guru::where('id', $data['guru_id'])->where('lembaga_id', $lembagaId)->exists();
        if (! $guruValid) {
            throw ValidationException::withMessages(['guru_id' => 'Guru ini bukan bagian dari lembaga Anda.']);
        }

        PiketHarian::updateOrCreate(
            ['lembaga_id' => $lembagaId, 'guru_id' => $data['guru_id'], 'tanggal' => $data['tanggal']],
            ['sumber' => 'override_manual', 'jadwal_piket_mingguan_id' => null]
        );

        return redirect()->route('admin.piket-guru.index')->with('status', 'Override piket harian berhasil disimpan.');
    }

    public function destroy(PiketHarian $piketHarian, Request $request): RedirectResponse
    {
        $this->authorize('piket.kelola');

        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';

        if ($isYayasan) {
            $isMilikYayasan = $piketHarian->lembaga && $piketHarian->lembaga->yayasan_id === $request->user()->yayasan_id;
            if (! $isMilikYayasan) {
                return redirect()->route('admin.piket-guru.index')
                    ->withErrors(['lembaga_id' => 'Override piket tidak ditemukan atau di luar wewenang yayasan Anda.']);
            }
        } else {
            if ($piketHarian->lembaga_id !== $request->user()->lembaga_id) {
                return redirect()->route('admin.piket-guru.index')
                    ->withErrors(['lembaga_id' => 'Override piket bukan milik lembaga Anda.']);
            }
        }

        $piketHarian->delete();

        return redirect()->route('admin.piket-guru.index')->with('status', 'Override piket harian berhasil dihapus.');
    }
}
