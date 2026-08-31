<?php

namespace App\Http\Controllers\Admin;

use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Services\AkunOrangTuaGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SiswaOrangTuaController extends BaseController
{
    use AuthorizesRequests;

    public function cari(Request $request, Siswa $siswa): JsonResponse
    {
        $this->authorize('siswa.edit');
        $this->authorize('orang-tua.create');

        $data = $request->validate(['nik' => ['required', 'digits:16']]);

        $user = User::where('username', $data['nik'])->first();
        $orangTua = $user ? $user->orangTua : null;

        if (! $orangTua) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'orang_tua' => [
                'id' => $orangTua->id,
                'nama_lengkap' => $orangTua->nama_lengkap,
                'nik' => $orangTua->nik,
                'no_hp' => $orangTua->no_hp,
            ],
        ]);
    }

    public function store(Request $request, Siswa $siswa, AkunOrangTuaGenerator $generator): RedirectResponse
    {
        $this->authorize('siswa.edit');
        $this->authorize('orang-tua.create');

        $data = $request->validate([
            'orang_tua_id' => ['nullable', 'integer', 'exists:orang_tua,id'],
            'hubungan' => ['required', 'in:ayah,ibu,wali'],
            'is_kontak_utama' => ['nullable', 'boolean'],
            'nama_lengkap' => ['required_without:orang_tua_id', 'nullable', 'string', 'max:255'],
            'nik' => ['required_without:orang_tua_id', 'nullable', 'digits:16'],
            'no_hp' => ['required_without:orang_tua_id', 'nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($data['orang_tua_id'])) {
            $orangTua = OrangTua::findOrFail($data['orang_tua_id']);
        } else {
            $existingUser = User::where('username', $data['nik'])->first();

            if ($existingUser) {
                $existingOrangTua = $existingUser->orangTua;

                if ($existingOrangTua === null) {
                    return back()
                        ->withErrors(['nik' => 'NIK ini sudah terdaftar ke akun lain yang bukan profil Orang Tua.'])
                        ->withInput();
                }

                return back()
                    ->withErrors(['nik' => 'NIK sudah terdaftar — cari NIK ini dulu untuk menautkan profil yang sudah ada.'])
                    ->withInput();
            }

            // Defense-in-depth backstop for the race window between the manual check above and
            // the insert below: re-validates uniqueness immediately before account creation.
            // The manual check above already handles the common case with a friendly, specific
            // message; this only fires if a concurrent request created the same username in
            // between (see Finding 7 of the final review).
            $request->validate(['nik' => [Rule::unique('users', 'username')]]);

            $orangTua = $generator->buat(
                $data['nama_lengkap'],
                $data['nik'],
                $data['no_hp'],
                $data['email'] ?? null,
                $data['alamat'] ?? null,
                $data['pekerjaan'] ?? null,
                $siswa->lembaga?->yayasan_id ?? $request->user()->yayasan_id ?? $request->user()->lembaga?->yayasan_id,
            );
        }

        if ($siswa->orangTua()->where('orang_tua_id', $orangTua->id)->exists()) {
            return back()->withErrors(['orang_tua_id' => 'Orang tua ini sudah tertaut ke siswa ini.']);
        }

        $isKontakUtama = $request->boolean('is_kontak_utama');

        DB::transaction(function () use ($siswa, $orangTua, $data, $isKontakUtama) {
            if ($isKontakUtama) {
                DB::table('siswa_orang_tua')->where('siswa_id', $siswa->id)->update(['is_kontak_utama' => false]);
            }

            $siswa->orangTua()->attach($orangTua->id, [
                'hubungan' => $data['hubungan'],
                'is_kontak_utama' => $isKontakUtama,
            ]);
        });

        return redirect()->route('admin.siswa.edit', $siswa)->with('status', 'Orang tua berhasil ditautkan.');
    }

    public function updateKontakUtama(Siswa $siswa, OrangTua $orangTua): RedirectResponse
    {
        $this->authorize('siswa.edit');
        $this->authorize('orang-tua.edit');

        if (! $siswa->orangTua()->where('orang_tua_id', $orangTua->id)->exists()) {
            return back()->withErrors(['orang_tua_id' => 'Orang tua ini belum tertaut ke siswa ini.']);
        }

        DB::transaction(function () use ($siswa, $orangTua) {
            DB::table('siswa_orang_tua')->where('siswa_id', $siswa->id)->update(['is_kontak_utama' => false]);
            DB::table('siswa_orang_tua')
                ->where('siswa_id', $siswa->id)
                ->where('orang_tua_id', $orangTua->id)
                ->update(['is_kontak_utama' => true]);
        });

        return redirect()->route('admin.siswa.edit', $siswa)->with('status', 'Kontak utama berhasil diperbarui.');
    }

    public function destroy(Siswa $siswa, OrangTua $orangTua): RedirectResponse
    {
        $this->authorize('siswa.edit');
        $this->authorize('orang-tua.edit');

        $siswa->orangTua()->detach($orangTua->id);

        return redirect()->route('admin.siswa.edit', $siswa)->with('status', 'Tautan orang tua berhasil dihapus.');
    }
}
