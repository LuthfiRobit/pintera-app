<?php

namespace App\Http\Controllers\Admin\Lembaga;

use App\Http\Controllers\Controller;
use App\Models\LayananKhususLembaga;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LayananKhususController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $validated = $request->validate([
            'jenis_layanan' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tmt' => 'nullable|date',
            'tst' => 'nullable|date|after_or_equal:tmt',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $lembaga->layananKhusus()->create($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#layanan-khusus')
            ->with('status', 'Layanan khusus berhasil ditambahkan.');
    }

    public function update(Request $request, Lembaga $lembaga, LayananKhususLembaga $layananKhusus): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($layananKhusus->lembaga_id === $lembaga->id, 404);

        $validated = $request->validate([
            'jenis_layanan' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tmt' => 'nullable|date',
            'tst' => 'nullable|date|after_or_equal:tmt',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $layananKhusus->update($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#layanan-khusus')
            ->with('status', 'Layanan khusus berhasil diperbarui.');
    }

    public function destroy(Request $request, Lembaga $lembaga, LayananKhususLembaga $layananKhusus): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($layananKhusus->lembaga_id === $lembaga->id, 404);

        $layananKhusus->delete();

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#layanan-khusus')
            ->with('status', 'Layanan khusus berhasil dihapus.');
    }
}
