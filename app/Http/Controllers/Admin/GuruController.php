<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
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
    use AuthorizesRequests;

    private const JENIS_KELAMIN_OPTIONS = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    private const JENIS_PTK_OPTIONS = [
        'guru_kelas' => 'Guru Kelas',
        'guru_mapel' => 'Guru Mapel',
        'kepala_sekolah' => 'Kepala Sekolah',
        'tenaga_administrasi' => 'Tenaga Administrasi',
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

        $guruList = Guru::with('user')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('nama', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%")))
            ->when($jenisPtk, fn ($q) => $q->where('jenis_ptk', $jenisPtk))
            ->when($statusAktif, fn ($q) => $q->where('status_aktif', $statusAktif))
            ->orderBy('nama')
            ->get();

        return view('admin.guru.index', [
            'guruList' => $guruList,
            'search' => $search,
            'jenisPtk' => $jenisPtk,
            'statusAktif' => $statusAktif,
            'jenisPtkOptions' => self::JENIS_PTK_OPTIONS,
            'statusAktifOptions' => self::STATUS_AKTIF_OPTIONS,
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
            $user = User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => Hash::make($data['nip']),
                'lembaga_id' => $lembagaId,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $user->assignRole('guru');

            Guru::create([
                ...$data,
                'user_id' => $user->id,
                'lembaga_id' => $lembagaId,
            ]);
        });

        return redirect()->route('admin.guru.index')->with('status', 'Data guru & akun berhasil dibuat.');
    }

    public function edit(Guru $guru): View
    {
        $this->authorize('guru.edit');

        return view('admin.guru.edit', [
            'guru' => $guru,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');

        $data = $this->validateProfil($request, $guru);

        DB::transaction(function () use ($data, $guru) {
            $guru->user()->update([
                'name' => $data['nama'],
                'email' => $data['email'],
            ]);

            $guru->update($data);
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
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }

    private function validateProfil(Request $request, ?Guru $guru = null): array
    {
        $data = $request->validate([
            'nik' => ['required', 'digits:16', function ($attribute, $value, $fail) use ($guru) {
                $query = Guru::withoutGlobalScopes()->where('nik_hash', hash('sha256', $value));
                if ($guru) {
                    $query->where('id', '!=', $guru->id);
                }
                if ($query->exists()) {
                    $fail('NIK sudah terdaftar untuk guru lain.');
                }
            }],
            'nip' => ['required', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($guru?->user_id)],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'jenis_ptk' => ['required', 'in:guru_kelas,guru_mapel,kepala_sekolah,tenaga_administrasi'],
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
