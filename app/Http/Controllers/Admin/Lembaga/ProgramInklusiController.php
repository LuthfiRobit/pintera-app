<?php

namespace App\Http\Controllers\Admin\Lembaga;

use App\Http\Controllers\Controller;
use App\Models\Lembaga;
use App\Models\ProgramInklusiLembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProgramInklusiController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $validated = $request->validate([
            'kebutuhan_khusus' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tanggal_sk' => 'nullable|date',
            'tmt' => 'nullable|date',
            'tst' => 'nullable|date|after_or_equal:tmt',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $lembaga->programInklusi()->create($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#program-inklusi')
            ->with('status', 'Program inklusi berhasil ditambahkan.');
    }

    public function update(Request $request, Lembaga $lembaga, ProgramInklusiLembaga $programInklusi): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($programInklusi->lembaga_id === $lembaga->id, 404);

        $validated = $request->validate([
            'kebutuhan_khusus' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:255',
            'tanggal_sk' => 'nullable|date',
            'tmt' => 'nullable|date',
            'tst' => 'nullable|date|after_or_equal:tmt',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        $programInklusi->update($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#program-inklusi')
            ->with('status', 'Program inklusi berhasil diperbarui.');
    }

    public function destroy(Request $request, Lembaga $lembaga, ProgramInklusiLembaga $programInklusi): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($programInklusi->lembaga_id === $lembaga->id, 404);

        $programInklusi->delete();

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#program-inklusi')
            ->with('status', 'Program inklusi berhasil dihapus.');
    }
}
