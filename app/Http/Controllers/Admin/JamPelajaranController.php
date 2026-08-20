<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\PolaJam\CreateJamPelajaranAction;
use App\Domains\Akademik\Actions\PolaJam\DeleteJamPelajaranAction;
use App\Domains\Akademik\Actions\PolaJam\UpdateJamPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\JamPelajaranData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Enums\Hari;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JamPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, CreateJamPelajaranAction $action): RedirectResponse
    {
        $this->authorize('jam-pelajaran.create');

        $data = $request->validate([
            'pola_jam_id' => ['required', 'integer'],
            'hari' => ['required', 'array', 'min:1'],
            'hari.*' => ['in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'urutan' => ['required', 'integer', 'min:1'],
            'label' => ['required', 'string', 'max:255'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'is_pelajaran' => ['required', 'boolean'],
        ]);

        $polaJam = PolaJam::find($data['pola_jam_id']);
        if (! $polaJam) {
            abort(404);
        }

        $result = $action->execute(new JamPelajaranData(
            polaJamId: $data['pola_jam_id'],
            hari: $data['hari'],
            urutan: $data['urutan'],
            label: $data['label'],
            jamMulai: $data['jam_mulai'],
            jamSelesai: $data['jam_selesai'],
            isPelajaran: $data['is_pelajaran'],
        ));

        if (empty($result['berhasil'])) {
            return back()->withErrors([
                'hari' => 'Semua hari yang dipilih (' . $this->formatDaftarHari($data['hari']) . ') sudah punya slot di urutan ini — tidak ada yang ditambahkan.',
            ])->withInput();
        }

        $status = 'Slot berhasil ditambahkan untuk ' . $this->formatDaftarHari($result['berhasil']) . '.';
        if (! empty($result['dilewati'])) {
            $status .= ' ' . $this->formatDaftarHari($result['dilewati']) . ' dilewati karena urutan ini sudah dipakai.';
        }

        return redirect()->route('admin.pola-jam.index')->with('status', $status);
    }

    public function edit(JamPelajaran $jamPelajaran): View
    {
        $this->authorize('jam-pelajaran.edit');

        if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
            abort(404);
        }

        return view('portals.lembaga.akademik.jam-pelajaran.edit', ['jamPelajaran' => $jamPelajaran]);
    }

    public function update(Request $request, JamPelajaran $jamPelajaran, UpdateJamPelajaranAction $action): RedirectResponse
    {
        $this->authorize('jam-pelajaran.edit');

        if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
            abort(404);
        }

        $data = $request->validate([
            'hari' => ['sometimes', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'urutan' => ['sometimes', 'integer', 'min:1'],
            'label' => ['required', 'string', 'max:255'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'is_pelajaran' => ['required', 'boolean'],
        ]);

        $hari = $data['hari'] ?? $jamPelajaran->hari->value;
        $urutan = $data['urutan'] ?? $jamPelajaran->urutan;

        try {
            $action->execute($jamPelajaran, $hari, $urutan, $data['label'], $data['jam_mulai'], $data['jam_selesai'], $data['is_pelajaran']);
        } catch (ValidationException $e) {
            return back()->withErrors(['urutan' => $e->validator->errors()->first('urutan')])->withInput();
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil diperbarui.');
    }

    public function destroy(JamPelajaran $jamPelajaran, DeleteJamPelajaranAction $action): RedirectResponse
    {
        $this->authorize('jam-pelajaran.delete');

        try {
            $action->execute($jamPelajaran);
        } catch (ValidationException $e) {
            return back()->withErrors(['jam_pelajaran' => $e->validator->errors()->first('jam_pelajaran')]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil dihapus.');
    }

    private function formatDaftarHari(array $nilaiHari): string
    {
        $label = collect($nilaiHari)->map(fn ($h) => Hari::from($h)->label())->all();

        if (count($label) === 1) {
            return $label[0];
        }

        $terakhir = array_pop($label);

        return implode(', ', $label) . ' dan ' . $terakhir;
    }
}
