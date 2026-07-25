<?php

namespace App\Http\Controllers\Admin;

use App\Models\JamPelajaran;
use App\Models\PolaJam;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class JamPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('jam-pelajaran.create');

        $data = $request->validate([
            'pola_jam_id' => ['required', 'integer'],
            'hari' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
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

        JamPelajaran::create($data);

        return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil ditambahkan.');
    }
}
