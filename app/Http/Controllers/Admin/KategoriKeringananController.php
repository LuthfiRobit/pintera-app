<?php
// app/Http/Controllers/Admin/KategoriKeringananController.php

namespace App\Http\Controllers\Admin;

use App\Models\KategoriKeringanan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class KategoriKeringananController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('jenis-tagihan.create') || $request->user()->can('jenis-tagihan.edit'), 403);

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            return response()->json([
                'message' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah kategori keringanan.',
                'errors' => ['lembaga_id' => ['Pilih lembaga aktif melalui pengalih lembaga sebelum menambah kategori keringanan.']],
            ], 422);
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('kategori_keringanan', 'nama')
                ->where(fn ($query) => $query->where('lembaga_id', $lembagaId))],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
        $data['lembaga_id'] = $lembagaId;

        $kategori = KategoriKeringanan::create($data);

        return response()->json(['data' => $kategori], 201);
    }
}
