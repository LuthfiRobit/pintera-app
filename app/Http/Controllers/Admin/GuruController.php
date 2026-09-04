<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Domains\Identity\Actions\CreatePersonAction;
use App\Domains\Identity\Actions\UpdatePersonAction;
use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GuruController extends BaseController
{
    use AuthorizesRequests, ResolveLembagaScopeTrait;

    private const JENIS_KELAMIN_OPTIONS = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    private const JENIS_PTK_OPTIONS = [
        'guru_kelas' => 'Guru Kelas',
        'guru_mapel' => 'Guru Mapel',
        'kepala_sekolah' => 'Kepala Sekolah',
        'tenaga_administrasi' => 'Tenaga Administrasi',
        'guru_bk' => 'Guru BK',
    ];

    private const STATUS_KEPEGAWAIAN_OPTIONS = [
        'PNS' => 'PNS', 'PPPK' => 'PPPK', 'GTY' => 'GTY', 'PTY' => 'PTY', 'Honorer' => 'Honorer',
    ];

    private const STATUS_AKTIF_OPTIONS = [
        'aktif' => 'Aktif', 'non_aktif' => 'Non Aktif', 'mutasi' => 'Mutasi', 'pensiun' => 'Pensiun',
    ];

    public function index(Request $request): View
    {
        $this->authorize('guru.view');

        $search = $request->query('search');
        $jenisPtk = $request->query('jenis_ptk');
        $statusAktif = $request->query('status_aktif');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $guruList = Guru::with(['user', 'person'])
            ->when($search, fn ($q) => $q->search($search))
            ->when($jenisPtk, fn ($q) => $q->where('jenis_ptk', $jenisPtk))
            ->when($statusAktif, fn ($q) => $q->where('status_aktif', $statusAktif))
            ->orderByNama()
            ->paginate($perPage)
            ->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.guru._daftar', [
                'guruList' => $guruList,
                'jenisPtkOptions' => self::JENIS_PTK_OPTIONS,
                'statusAktifOptions' => self::STATUS_AKTIF_OPTIONS,
                'perPage' => $perPage,
            ]);
        }

        return view('admin.guru.index', [
            'guruList' => $guruList,
            'search' => $search,
            'jenisPtk' => $jenisPtk,
            'statusAktif' => $statusAktif,
            'jenisPtkOptions' => self::JENIS_PTK_OPTIONS,
            'statusAktifOptions' => self::STATUS_AKTIF_OPTIONS,
            'perPage' => $perPage,
            'totalGuru' => Guru::count(),
            'totalAktif' => Guru::where('status_aktif', 'aktif')->count(),
            'totalPNS' => Guru::whereIn('status_kepegawaian', ['PNS', 'PPPK'])->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('guru.create');

        return view('admin.guru.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('guru.create');

        $data = $this->validateProfil($request);

        $lembagaId = $this->resolveLembagaId($request);
        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah data guru.'])->withInput();
        }

        DB::transaction(function () use ($data, $lembagaId) {
            $person = app(CreatePersonAction::class)->execute(
                identityData: [
                    'nama_lengkap' => $data['nama'],
                    'nik' => $data['nik'] ?? null,
                    'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                    'tempat_lahir' => $data['tempat_lahir'] ?? null,
                    'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                    'agama' => $data['agama'] ?? null,
                    'kewarganegaraan' => $data['kewarganegaraan'] ?? 'WNI',
                    'no_hp' => $data['no_hp'] ?? null,
                    'email' => $data['email'],
                    'alamat_jalan' => $data['alamat_jalan'] ?? null,
                    'rt' => $data['rt'] ?? null,
                    'rw' => $data['rw'] ?? null,
                    'desa_kelurahan' => $data['desa_kelurahan'] ?? null,
                    'kecamatan' => $data['kecamatan'] ?? null,
                    'kabupaten_kota' => $data['kabupaten_kota'] ?? null,
                    'provinsi' => $data['provinsi'] ?? null,
                    'kode_pos' => $data['kode_pos'] ?? null,
                ],
                lembagaId: $lembagaId,
                actingYayasanId: null,
            );

            $user = User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => Hash::make($data['nip']),
                'lembaga_id' => $lembagaId,
                'email_verified_at' => now(),
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $user->assignRole('guru');
            $person->update(['user_id' => $user->id]);

            Guru::create([
                'person_id' => $person->id,
                'lembaga_id' => $lembagaId,
                'nuptk' => $data['nuptk'] ?? null,
                'nip' => $data['nip'],
                'jenis_ptk' => $data['jenis_ptk'],
                'status_kepegawaian' => $data['status_kepegawaian'],
                'golongan_pangkat' => $data['golongan_pangkat'] ?? null,
                'tmt_tugas' => $data['tmt_tugas'] ?? null,
                'tmt_pns' => $data['tmt_pns'] ?? null,
                'status_aktif' => 'aktif',
                'kapasitas_kasus_aktif' => $data['kapasitas_kasus_aktif'] ?? null,
            ]);
        });

        return redirect()->route('admin.guru.index')->with('status', 'Data guru & akun berhasil dibuat.');
    }

    public function edit(Guru $guru): View
    {
        $this->authorize('guru.edit');

        $guru->load([
            'user',
            'person',
            'riwayatPendidikan' => fn ($query) => $query->orderBy('tahun_lulus', 'desc'),
            'sertifikasi' => fn ($query) => $query->orderBy('tahun_sertifikasi', 'desc'),
            'jabatanTambahan',
        ]);

        return view('admin.guru.edit', [
            'guru' => $guru,
            'jabatanTambahanMasterList' => JabatanTambahanMaster::orderBy('nama')->get(),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');

        $data = $this->validateProfil($request, $guru);

        DB::transaction(function () use ($data, $guru) {
            if ($guru->person) {
                app(UpdatePersonAction::class)->execute($guru->person, [
                    'nama_lengkap' => $data['nama'],
                    'email' => $data['email'],
                    'nik' => $data['nik'] ?? $guru->person->nik,
                    'jenis_kelamin' => $data['jenis_kelamin'] ?? $guru->person->jenis_kelamin,
                    'tempat_lahir' => $data['tempat_lahir'] ?? $guru->person->tempat_lahir,
                    'tanggal_lahir' => $data['tanggal_lahir'] ?? $guru->person->tanggal_lahir,
                    'agama' => $data['agama'] ?? $guru->person->agama,
                    'kewarganegaraan' => $data['kewarganegaraan'] ?? $guru->person->kewarganegaraan,
                    'no_hp' => $data['no_hp'] ?? $guru->person->no_hp,
                    'alamat_jalan' => $data['alamat_jalan'] ?? $guru->person->alamat_jalan,
                    'rt' => $data['rt'] ?? $guru->person->rt,
                    'rw' => $data['rw'] ?? $guru->person->rw,
                    'desa_kelurahan' => $data['desa_kelurahan'] ?? $guru->person->desa_kelurahan,
                    'kecamatan' => $data['kecamatan'] ?? $guru->person->kecamatan,
                    'kabupaten_kota' => $data['kabupaten_kota'] ?? $guru->person->kabupaten_kota,
                    'provinsi' => $data['provinsi'] ?? $guru->person->provinsi,
                    'kode_pos' => $data['kode_pos'] ?? $guru->person->kode_pos,
                ]);

                if ($guru->person->user !== null) {
                    $guru->person->user->update([
                        'name' => $data['nama'],
                        'email' => $data['email'],
                    ]);
                }
            } elseif ($guru->user !== null) {
                $guru->user->update([
                    'name' => $data['nama'],
                    'email' => $data['email'],
                ]);
            }

            $guru->update([
                'nuptk' => $data['nuptk'] ?? null,
                'nip' => $data['nip'],
                'jenis_ptk' => $data['jenis_ptk'],
                'status_kepegawaian' => $data['status_kepegawaian'],
                'golongan_pangkat' => $data['golongan_pangkat'] ?? null,
                'tmt_tugas' => $data['tmt_tugas'] ?? null,
                'tmt_pns' => $data['tmt_pns'] ?? null,
                'kapasitas_kasus_aktif' => $data['kapasitas_kasus_aktif'] ?? null,
            ]);
        });

        return redirect()->route('admin.guru.index')->with('status', 'Data guru berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');

        $data = $request->validate([
            'status_aktif' => ['required', 'in:aktif,non_aktif,mutasi,pensiun'],
        ]);

        DB::transaction(function () use ($data, $guru) {
            $guru->update(['status_aktif' => $data['status_aktif']]);
            $guru->user()->update(['is_active' => $data['status_aktif'] === 'aktif']);
        });

        return redirect()->route('admin.guru.index')->with('status', 'Status guru berhasil diperbarui.');
    }

    private function formOptions(): array
    {
        return [
            'jenisKelaminOptions' => self::JENIS_KELAMIN_OPTIONS,
            'jenisPtkOptions' => self::JENIS_PTK_OPTIONS,
            'statusKepegawaianOptions' => self::STATUS_KEPEGAWAIAN_OPTIONS,
        ];
    }

    private function resolveLembagaId(Request $request): ?int
    {
        return $this->resolveActiveLembagaId($request->user());
    }

    private function validateProfil(Request $request, ?Guru $guru = null): array
    {
        $lembagaId = $this->resolveLembagaId($request);
        $yayasanId = $lembagaId ? Lembaga::withoutGlobalScopes()->find($lembagaId)?->yayasan_id : ($request->user()?->yayasan_id ?? $request->user()?->lembaga?->yayasan_id);

        $data = $request->validate([
            'nik' => ['required', 'digits:16', function ($attribute, $value, $fail) use ($guru, $yayasanId) {
                $query = Person::withoutGlobalScopes()->where('nik_hash', hash('sha256', $value));
                if ($yayasanId) {
                    $query->where('yayasan_id', $yayasanId);
                }
                if ($guru && $guru->person_id) {
                    $query->where('id', '!=', $guru->person_id);
                }
                if ($query->exists()) {
                    $fail('NIK sudah terdaftar untuk guru lain.');
                }
            }],
            'nip' => ['required', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($guru?->user_id ?? $guru?->person?->user_id)],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'jenis_ptk' => ['required', 'in:guru_kelas,guru_mapel,kepala_sekolah,tenaga_administrasi,guru_bk'],
            'kapasitas_kasus_aktif' => ['nullable', 'integer', 'min:0'],
            'status_kepegawaian' => ['required', 'in:PNS,PPPK,GTY,PTY,Honorer'],
            'nuptk' => ['nullable', 'string', 'max:30', function ($attribute, $value, $fail) use ($guru) {
                if (blank($value)) {
                    return;
                }
                $query = Guru::withoutGlobalScopes()->where('nuptk', $value);
                if ($guru) {
                    $query->where('id', '!=', $guru->id);
                }
                if ($query->exists()) {
                    $fail('NUPTK sudah terdaftar untuk guru lain.');
                }
            }],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'alamat_jalan' => ['nullable', 'string'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'desa_kelurahan' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kabupaten_kota' => ['nullable', 'string', 'max:255'],
            'provinsi' => ['nullable', 'string', 'max:255'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'golongan_pangkat' => ['nullable', 'string', 'max:50'],
            'tmt_tugas' => ['nullable', 'date'],
            'tmt_pns' => ['nullable', 'date'],
        ]);

        $data['kewarganegaraan'] = ($data['kewarganegaraan'] ?? null) ?: 'WNI';

        return $data;
    }
}
