<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use App\Services\AkunSiswaGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PendaftaranSiswaController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('siswa.spmb-daftar');

        return view('admin.siswa.spmb-daftar', [
            'pendaftaranList' => Pendaftaran::siapDidaftarkanSebagaiSiswa()
                ->with(['calonMurid', 'jalurPpdb', 'gelombangPpdb'])
                ->latest('submitted_at')
                ->get(),
            'kelasList' => Kelas::orderBy('nama')->get(),
            'nisSaran' => $this->nisBerikutnya(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('siswa.spmb-daftar');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer'],
            'pendaftaran_ids' => ['required', 'array', 'min:1'],
            'pendaftaran_ids.*' => ['exists:pendaftaran,id'],
            'nis' => ['required', 'array'],
        ]);

        $kelas = Kelas::find($data['kelas_id']);
        abort_if($kelas === null, 404);

        $pendaftaranTerpilih = Pendaftaran::siapDidaftarkanSebagaiSiswa()
            ->whereIn('id', $data['pendaftaran_ids'])
            ->with('calonMurid')
            ->get();

        DB::transaction(function () use ($pendaftaranTerpilih, $data, $kelas) {
            $lembaga = Lembaga::withoutGlobalScopes()->findOrFail($kelas->lembaga_id);

            foreach ($pendaftaranTerpilih as $pendaftaran) {
                abort_if($pendaftaran->lembaga_id !== $kelas->lembaga_id, 404);

                $calonMurid = $pendaftaran->calonMurid;
                $nis = $data['nis'][$pendaftaran->id] ?? $this->nisBerikutnya();
                $user = app(AkunSiswaGenerator::class)->buat($calonMurid->nama_lengkap, $nis, $lembaga);

                Siswa::create([
                    'lembaga_id' => $pendaftaran->lembaga_id,
                    'kelas_id' => $kelas->id,
                    'calon_murid_id' => $calonMurid->id,
                    'pendaftaran_asal_id' => $pendaftaran->id,
                    'user_id' => $user->id,
                    'sumber_data' => SumberDataSiswa::Spmb->value,
                    'nis' => $nis,
                    'nisn' => $calonMurid->nisn,
                    'nama_lengkap' => $calonMurid->nama_lengkap,
                    'jenis_kelamin' => $calonMurid->jenis_kelamin,
                    'tempat_lahir' => $calonMurid->tempat_lahir,
                    'tanggal_lahir' => $calonMurid->tanggal_lahir,
                    'agama' => $calonMurid->agama,
                ]);
            }
        });

        return redirect()->route('admin.siswa.index')->with('status', count($pendaftaranTerpilih).' siswa berhasil didaftarkan.');
    }

    private function nisBerikutnya(): string
    {
        $tahun = now()->year;
        $urutan = Siswa::where('nis', 'like', $tahun.'%')->count() + 1;

        return $tahun.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
    }
}
