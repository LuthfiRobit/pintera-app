<?php

// app/Http/Controllers/Lembaga/Keuangan/SiswaKeringananController.php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiswaKeringananController extends Controller
{
    use AuthorizesRequests;

    public function index(Siswa $siswa): View
    {
        $this->authorize('siswa-keringanan.kelola');

        $keringanan = $siswa->siswaKeringanan()->with('kategoriKeringanan')->latest('berlaku_dari')->get();

        return view('admin.siswa.tabs.keringanan', compact('siswa', 'keringanan'));
    }

    public function store(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa-keringanan.kelola');

        $validated = $request->validate([
            'kategori_keringanan_id' => [
                'required',
                Rule::exists('kategori_keringanan', 'id')->where('lembaga_id', $siswa->lembaga_id),
            ],
            'berlaku_dari' => ['required', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_dari'],
        ]);

        $siswa->siswaKeringanan()->create($validated);

        return back()->with('success', 'Keringanan berhasil ditambahkan.');
    }

    public function destroy(SiswaKeringanan $siswaKeringanan): RedirectResponse
    {
        $this->authorize('siswa-keringanan.kelola');

        $siswaKeringanan->delete();

        return back()->with('success', 'Keringanan berhasil dicabut.');
    }
}
