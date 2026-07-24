<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DataDiriController extends BaseController
{
    use ResolvesWizardContext;

    private const PESAN_NIK_DIBLOKIR = 'NIK ini sudah pernah terdaftar oleh akun lain. Hubungi admin sekolah untuk bantuan.';

    private const AGAMA_OPTIONS = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];

    private const GOLONGAN_DARAH_OPTIONS = ['A', 'B', 'AB', 'O'];

    private const PEKERJAAN_OPTIONS = [
        'PNS/ASN', 'TNI', 'POLRI', 'Guru/Dosen', 'Karyawan Swasta', 'Wiraswasta/Pedagang',
        'Petani/Peternak', 'Nelayan', 'Buruh', 'Tenaga Kesehatan', 'Pengacara/Notaris',
        'Sopir/Pengemudi', 'Pensiunan', 'Ibu Rumah Tangga', 'Tidak Bekerja', 'Lainnya',
    ];

    public function create(): View
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $nominal = $this->resolveNominalPendaftaran($lembaga, $jalur);

        return view('spmb.data-diri', [
            'lembaga' => $lembaga, 'jalur' => $jalur, 'nominal' => $nominal,
            'agamaOptions' => self::AGAMA_OPTIONS,
            'golonganDarahOptions' => self::GOLONGAN_DARAH_OPTIONS,
            'pekerjaanOptions' => self::PEKERJAAN_OPTIONS,
        ]);
    }

    public function cekNik(Request $request): JsonResponse
    {
        $this->resolveWizardContext();

        $data = $request->validate(['nik' => ['required', 'digits:16']]);

        $calonMurid = CalonMurid::findByNik($data['nik']);

        if (! $calonMurid) {
            return response()->json(['ditemukan' => false]);
        }

        if (! $this->calonMuridBolehDiaksesOlehAkunIni($calonMurid)) {
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

    public function store(Request $request, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();

        $data = $request->validate([
            'nik' => ['required', 'digits:16'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nisn' => ['nullable', 'digits:10'],
            'no_kk' => ['nullable', 'digits:16'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'agama' => ['required', 'string', Rule::in(self::AGAMA_OPTIONS)],
            'golongan_darah' => ['nullable', Rule::in(self::GOLONGAN_DARAH_OPTIONS)],
            'no_telepon' => ['nullable', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'alamat_jalan' => ['required', 'string', 'max:255'],
            'rt' => ['nullable', 'digits_between:1,3'],
            'rw' => ['nullable', 'digits_between:1,3'],
            'dusun' => ['nullable', 'string', 'max:255'],
            'desa_kelurahan' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:255'],
            'kabupaten_kota' => ['required', 'string', 'max:255'],
            'provinsi' => ['required', 'string', 'max:255'],
            'kode_pos' => ['nullable', 'digits:5'],
            'nama_ayah' => ['required', 'string', 'max:255'],
            'pekerjaan_ayah' => ['nullable', Rule::in(self::PEKERJAAN_OPTIONS)],
            'nama_ibu' => ['required', 'string', 'max:255'],
            'pekerjaan_ibu' => ['nullable', Rule::in(self::PEKERJAAN_OPTIONS)],
            'nama_wali' => ['nullable', 'string', 'max:255'],
            'pekerjaan_wali' => ['nullable', Rule::in(self::PEKERJAAN_OPTIONS)],
            'data_periodik' => ['nullable', 'array'],
            'data_khusus' => ['nullable', 'array'],
        ]);

        $calonMuridLama = CalonMurid::findByNik($data['nik']);

        if ($calonMuridLama && ! $this->calonMuridBolehDiaksesOlehAkunIni($calonMuridLama)) {
            return back()->withErrors(['nik' => self::PESAN_NIK_DIBLOKIR])->withInput();
        }

        $wizardSession->put($lembaga, $jalur, [
            'nik' => $data['nik'],
            'data_pribadi' => collect($data)->only([
                'nama_lengkap', 'nisn', 'no_kk', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'golongan_darah', 'no_telepon',
            ])->all(),
            'alamat' => collect($data)->only([
                'alamat_jalan', 'rt', 'rw', 'dusun', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
            ])->all(),
            'keluarga' => $this->bangunDaftarKeluarga($data),
            'data_periodik' => $data['data_periodik'] ?? null,
            'data_khusus' => $data['data_khusus'] ?? null,
        ]);

        return redirect()->route('portal.wizard.formulir-tambahan');
    }

    /**
     * The form no longer lets the user pick a "jenis" for each family member — Ayah and
     * Ibu are fixed, required slots and Wali is a single optional slot (not a repeater) —
     * but downstream (session shape, ReviewSubmitController's KeluargaCalonMurid writes,
     * the review summary view) all still expect the old array-of-{jenis,nama,pekerjaan}
     * shape, so it's rebuilt here rather than changed everywhere else.
     */
    private function bangunDaftarKeluarga(array $data): array
    {
        $keluarga = [
            ['jenis' => 'ayah', 'nama' => $data['nama_ayah'], 'pekerjaan' => $data['pekerjaan_ayah'] ?? null],
            ['jenis' => 'ibu', 'nama' => $data['nama_ibu'], 'pekerjaan' => $data['pekerjaan_ibu'] ?? null],
        ];

        if (! empty($data['nama_wali'])) {
            $keluarga[] = ['jenis' => 'wali', 'nama' => $data['nama_wali'], 'pekerjaan' => $data['pekerjaan_wali'] ?? null];
        }

        return $keluarga;
    }

    private function calonMuridBolehDiaksesOlehAkunIni(CalonMurid $calonMurid): bool
    {
        $adaPendaftaranSebelumnya = Pendaftaran::where('calon_murid_id', $calonMurid->id)->exists();

        if (! $adaPendaftaranSebelumnya) {
            return true;
        }

        return Pendaftaran::where('calon_murid_id', $calonMurid->id)
            ->where('akun_pendaftar_id', Auth::guard('portal')->user()->id)
            ->exists();
    }
}
