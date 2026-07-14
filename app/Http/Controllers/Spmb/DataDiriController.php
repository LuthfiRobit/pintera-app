<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\CalonMurid;
use App\Models\JalurPpdb;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class DataDiriController extends BaseController
{
    use ResolvesSpmbTenant;

    private const PESAN_NIK_DIBLOKIR = 'NIK ini sudah pernah terdaftar. Gunakan email yang sama dengan pendaftaran sebelumnya, atau hubungi admin sekolah untuk bantuan.';

    public function create(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        return view('spmb.data-diri', ['lembaga' => $lembaga, 'jalur' => $jalur]);
    }

    public function cekNik(Request $request, string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): JsonResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        $data = $request->validate(['nik' => ['required', 'digits:16']]);

        $calonMurid = CalonMurid::findByNik($data['nik']);

        if (! $calonMurid) {
            return response()->json(['ditemukan' => false]);
        }

        $emailSesi = $wizardSession->get($lembaga, $jalur)['email_pendaftaran'] ?? null;

        if (! $this->emailCocokDenganCalonMurid($calonMurid, $emailSesi)) {
            return response()->json([
                'ditemukan' => true,
                'diblokir' => true,
                'pesan' => self::PESAN_NIK_DIBLOKIR,
            ], 422);
        }

        return response()->json([
            'ditemukan' => true,
            'diblokir' => false,
            'data_pribadi' => [
                'nama_lengkap' => $calonMurid->nama_lengkap,
                'nisn' => $calonMurid->nisn,
                'jenis_kelamin' => $calonMurid->jenis_kelamin,
                'tempat_lahir' => $calonMurid->tempat_lahir,
                'tanggal_lahir' => $calonMurid->tanggal_lahir->format('Y-m-d'),
                'agama' => $calonMurid->agama,
                'golongan_darah' => $calonMurid->golongan_darah,
                'no_telepon' => $calonMurid->no_telepon,
            ],
            'alamat' => $calonMurid->alamat ? $calonMurid->alamat->only([
                'alamat_jalan', 'rt', 'rw', 'dusun', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
            ]) : null,
            'keluarga' => $calonMurid->keluarga->map->only(['jenis', 'nama', 'tahun_lahir', 'pendidikan_terakhir', 'pekerjaan', 'penghasilan']),
        ]);
    }

    public function store(Request $request, string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        $data = $request->validate([
            'nik' => ['required', 'digits:16'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nisn' => ['nullable', 'string', 'max:20'],
            'no_kk' => ['nullable', 'digits:16'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date'],
            'agama' => ['required', 'string', 'max:50'],
            'golongan_darah' => ['nullable', 'string', 'max:5'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'alamat_jalan' => ['required', 'string', 'max:255'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'dusun' => ['nullable', 'string', 'max:255'],
            'desa_kelurahan' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:255'],
            'kabupaten_kota' => ['required', 'string', 'max:255'],
            'provinsi' => ['required', 'string', 'max:255'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'keluarga' => ['required', 'array', 'min:1'],
            'keluarga.*.jenis' => ['required', 'in:ayah,ibu,wali'],
            'keluarga.*.nama' => ['required', 'string', 'max:255'],
            'keluarga.*.tahun_lahir' => ['nullable', 'integer'],
            'keluarga.*.pendidikan_terakhir' => ['nullable', 'string', 'max:255'],
            'keluarga.*.pekerjaan' => ['nullable', 'string', 'max:255'],
            'keluarga.*.penghasilan' => ['nullable', 'string', 'max:255'],
            'data_periodik' => ['nullable', 'array'],
            'data_khusus' => ['nullable', 'array'],
        ]);

        $calonMuridLama = CalonMurid::findByNik($data['nik']);

        if ($calonMuridLama) {
            $emailSesi = $wizardSession->get($lembaga, $jalur)['email_pendaftaran'] ?? null;

            if (! $this->emailCocokDenganCalonMurid($calonMuridLama, $emailSesi)) {
                return back()->withErrors(['nik' => self::PESAN_NIK_DIBLOKIR])->withInput();
            }
        }

        $wizardSession->put($lembaga, $jalur, [
            'nik' => $data['nik'],
            'data_pribadi' => collect($data)->only([
                'nama_lengkap', 'nisn', 'no_kk', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'golongan_darah', 'no_telepon',
            ])->all(),
            'alamat' => collect($data)->only([
                'alamat_jalan', 'rt', 'rw', 'dusun', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
            ])->all(),
            'keluarga' => $data['keluarga'],
            'data_periodik' => $data['data_periodik'] ?? null,
            'data_khusus' => $data['data_khusus'] ?? null,
        ]);

        return redirect()->route('spmb.formulir-tambahan', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]);
    }

    private function emailCocokDenganCalonMurid(CalonMurid $calonMurid, ?string $emailSesi): bool
    {
        return Pendaftaran::where('calon_murid_id', $calonMurid->id)
            ->where('email_pendaftaran', $emailSesi)
            ->exists();
    }
}
