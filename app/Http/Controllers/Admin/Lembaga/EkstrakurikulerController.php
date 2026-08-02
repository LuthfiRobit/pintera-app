<?php

namespace App\Http\Controllers\Admin\Lembaga;

use App\Http\Controllers\Controller;
use App\Models\EkstrakurikulerLembaga;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EkstrakurikulerController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $validated = $request->validate([
            'jenis_ekskul' => 'required|string|max:255',
            'nama_ekskul' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tanggal_sk' => 'nullable|date',
            'jam_per_minggu' => 'nullable|integer|min:1|max:50',
        ]);

        $lembaga->ekstrakurikuler()->create($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#ekstrakurikuler')
            ->with('status', 'Ekstrakurikuler berhasil ditambahkan.');
    }

    public function update(Request $request, Lembaga $lembaga, EkstrakurikulerLembaga $ekstrakurikuler): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($ekstrakurikuler->lembaga_id === $lembaga->id, 404);

        $validated = $request->validate([
            'jenis_ekskul' => 'required|string|max:255',
            'nama_ekskul' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tanggal_sk' => 'nullable|date',
            'jam_per_minggu' => 'nullable|integer|min:1|max:50',
        ]);

        $ekstrakurikuler->update($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#ekstrakurikuler')
            ->with('status', 'Ekstrakurikuler berhasil diperbarui.');
    }

    public function destroy(Request $request, Lembaga $lembaga, EkstrakurikulerLembaga $ekstrakurikuler): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($ekstrakurikuler->lembaga_id === $lembaga->id, 404);

        $ekstrakurikuler->delete();

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#ekstrakurikuler')
            ->with('status', 'Ekstrakurikuler berhasil dihapus.');
    }
}
