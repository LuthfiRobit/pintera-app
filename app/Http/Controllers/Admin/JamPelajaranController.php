<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Hari;
use App\Models\JamPelajaran;
use App\Models\PolaJam;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JamPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
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

        $berhasil = [];
        $dilewati = [];

        foreach ($data['hari'] as $hari) {
            if ($this->tabrakanSlot($data['pola_jam_id'], $hari, $data['urutan'])) {
                $dilewati[] = $hari;
                continue;
            }

            JamPelajaran::create([...$data, 'hari' => $hari]);
            $berhasil[] = $hari;
        }

        if (empty($berhasil)) {
            return back()->withErrors([
                'hari' => 'Semua hari yang dipilih (' . $this->formatDaftarHari($data['hari']) . ') sudah punya slot di urutan ini — tidak ada yang ditambahkan.',
            ])->withInput();
        }

        $status = 'Slot berhasil ditambahkan untuk ' . $this->formatDaftarHari($berhasil) . '.';
        if (! empty($dilewati)) {
            $status .= ' ' . $this->formatDaftarHari($dilewati) . ' dilewati karena urutan ini sudah dipakai.';
        }

        return redirect()->route('admin.pola-jam.index')->with('status', $status);
    }

    public function edit(JamPelajaran $jamPelajaran): View
    {
        $this->authorize('jam-pelajaran.edit');

        if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
            abort(404);
        }

        return view('admin.jam-pelajaran.edit', ['jamPelajaran' => $jamPelajaran]);
    }

    public function update(Request $request, JamPelajaran $jamPelajaran): RedirectResponse
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

        if ($this->tabrakanSlot($jamPelajaran->pola_jam_id, $hari, $urutan, $jamPelajaran->id)) {
            return back()->withErrors(['urutan' => 'Urutan ini sudah dipakai pada hari yang sama di pola jam ini.'])->withInput();
        }

        $jamPelajaran->update([...$data, 'hari' => $hari, 'urutan' => $urutan]);

        return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil diperbarui.');
    }

    public function destroy(JamPelajaran $jamPelajaran): RedirectResponse
    {
        $this->authorize('jam-pelajaran.delete');

        if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
            abort(404);
        }

        if ($jamPelajaran->jadwalPelajaran()->exists()) {
            return back()->withErrors(['jam_pelajaran' => 'Slot ini masih dipakai di Jadwal Pelajaran — hapus jadwalnya dulu sebelum menghapus slot ini.']);
        }

        $jamPelajaran->delete();

        return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil dihapus.');
    }

    private function tabrakanSlot(int $polaJamId, string $hari, int $urutan, ?int $kecualiId = null): bool
    {
        return JamPelajaran::where('pola_jam_id', $polaJamId)
            ->where('hari', $hari)
            ->where('urutan', $urutan)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->exists();
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
