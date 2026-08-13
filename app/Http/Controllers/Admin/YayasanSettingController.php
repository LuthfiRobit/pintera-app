<?php
// app/Http/Controllers/Admin/YayasanSettingController.php

namespace App\Http\Controllers\Admin;

use App\Models\Yayasan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class YayasanSettingController extends BaseController
{
    use AuthorizesRequests;

    public function edit(): View
    {
        $this->authorize('yayasan.kelola');

        $yayasan = Yayasan::first();

        return view('admin.yayasan.edit', ['yayasan' => $yayasan]);
    }

    public function update(Request $request)
    {
        $this->authorize('yayasan.kelola');

        $yayasan = Yayasan::first();

        abort_if($yayasan === null, 404);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'npwp_yayasan' => ['nullable', 'string', 'max:255'],
            'akta_pendirian_nomor' => ['nullable', 'string', 'max:255'],
            'akta_pendirian_tanggal' => ['nullable', 'date'],
            'sk_kemenkumham_nomor' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'nama_ketua_pembina' => ['nullable', 'string', 'max:255'],
            'nama_ketua_pengurus' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg', 'max:1024'],
        ]);

        $oldLogo = null;

        if ($request->hasFile('logo')) {
            $oldLogo = $yayasan->logo;

            $data['logo'] = $request->file('logo')->store('yayasan-logo', 'public');
        }

        $yayasan->update($data);

        if ($oldLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        return redirect()->route('admin.yayasan.edit')->with('status', 'Data yayasan berhasil diperbarui.');
    }
}
