<?php
// app/Http/Controllers/Admin/TagihanSusulanController.php

namespace App\Http\Controllers\Admin;

use App\Models\Pendaftaran;
use App\Services\TagihanGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class TagihanSusulanController extends BaseController
{
    use AuthorizesRequests;

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }

    public function buatSusulan(Request $request, Pendaftaran $pendaftaran, TagihanGenerator $generator): RedirectResponse
    {
        $this->authorize('tagihan.buat-susulan');
        abort_unless($pendaftaran->lembaga_id === $this->lembagaId($request), 404);

        $data = $request->validate([
            'kategori' => ['required', 'in:pendaftaran,daftar_ulang'],
        ]);

        $tagihan = $generator->generate($pendaftaran, $data['kategori']);

        if (! $tagihan) {
            return back()->withErrors([
                'kategori' => 'Tagihan sudah ada, atau belum ada nominal yang dikonfigurasi untuk jalur ini.',
            ]);
        }

        return back()->with('status', 'Tagihan susulan berhasil dibuat.');
    }
}