<?php

// database/seeders/OrangTuaKaryawanSeeder.php

namespace Database\Seeders;

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Services\AkunKaryawanGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrangTuaKaryawanSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();
        $yayasan = Yayasan::firstOrFail();

        // ── 1. Upgrade Guru BK di SDIT ───────────────────────────────────────
        $this->seedGuruBk($sdit);

        // ── 2. Karyawan Pool Yayasan (Psikolog) ─────────────────────────────
        $this->seedKaryawanPool($yayasan);

        // ── 3. Karyawan Staf Umum Lembaga (Satpam, Petugas Kebersihan) ───────
        $this->seedKaryawanStafUmum($sdit, $yayasan);

        // ── 4. Orang Tua siswa SDIT ───────────────────────────────────────────
        $this->seedOrangTua($sdit);
    }

    // ── Guru BK ─────────────────────────────────────────────────────────────

    private function seedGuruBk(Lembaga $sdit): void
    {
        // Jadikan Hendra Gunawan sebagai Guru BK SDIT
        $user = User::where('email', 'hendra.gunawan@demo.test')->first();
        if (! $user) {
            return;
        }

        $user->guru?->update([
            'jenis_ptk' => 'guru_bk',
            'kapasitas_kasus_aktif' => null, // tidak dibatasi
        ]);
    }

    // ── Karyawan Pool ────────────────────────────────────────────────────────

    private function seedKaryawanPool(Yayasan $yayasan): void
    {
        $jenisKaryawan = JenisKaryawanMaster::where('nama', 'Psikolog')->firstOrFail();

        $nik = '0000019901900099';

        if (User::where('username', $nik)->exists()) {
            return;
        }

        $user = User::create([
            'name' => 'Dr. Rahma Aulia, M.Psi.',
            'email' => 'psikolog.pool@demo.test',
            'username' => $nik,
            'password' => Hash::make($nik),
            'lembaga_id' => null, // pool = lintas lembaga
            'email_verified_at' => now(),
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $user->assignRole('pegawai_yayasan');

        $person = Person::create([
            'yayasan_id' => $yayasan->id,
            'user_id' => $user->id,
            'nik' => $nik,
            'nama_lengkap' => 'Dr. Rahma Aulia, M.Psi.',
            'no_hp' => '081298765099',
        ]);

        Karyawan::create([
            'person_id' => $person->id,
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => null,
            'jenis_karyawan_id' => $jenisKaryawan->id,
            'status_aktif' => 'aktif',
            'kapasitas_kasus_aktif' => null,
        ]);
    }

    // ── Karyawan Staf Umum Lembaga ────────────────────────────────────────────

    private function seedKaryawanStafUmum(Lembaga $sdit, Yayasan $yayasan): void
    {
        // Dibuat lewat AkunKaryawanGenerator yang SAMA dipakai KaryawanController
        // (jalur resmi Admin -> Karyawan), supaya data demo mencerminkan alur
        // produksi nyata: username = NIK, password awal = NIK, role otomatis
        // pegawai_lembaga -- BUKAN lewat form Pengguna generik.
        $generator = app(AkunKaryawanGenerator::class);

        $stafUmum = [
            ['nama' => 'Slamet Riyadi', 'nik' => '3273019900020001', 'jenis' => 'Satpam', 'no_hp' => '081234570001'],
            ['nama' => 'Warsiti', 'nik' => '3273019900020002', 'jenis' => 'Petugas Kebersihan', 'no_hp' => '081234570002'],
        ];

        foreach ($stafUmum as $data) {
            if (User::where('username', $data['nik'])->exists()) {
                continue;
            }

            $jenisKaryawan = JenisKaryawanMaster::where('nama', $data['jenis'])->firstOrFail();

            $generator->buat(
                $data['nama'],
                $data['nik'],
                $yayasan->id,
                $sdit->id,
                $jenisKaryawan->id,
                $data['no_hp'],
            );
        }
    }

    // ── Orang Tua SDIT ─────────────────────────────────────────────────────

    private function seedOrangTua(Lembaga $sdit): void
    {
        $siswas = Siswa::where('lembaga_id', $sdit->id)->take(6)->get();

        if ($siswas->isEmpty()) {
            return;
        }

        $data = [
            [
                'nik' => '0000019901850051',
                'nama_lengkap' => 'Drs. Ahmad Pratama',
                'no_hp' => '081234560051',
                'email' => null,
                'hubungan' => 'ayah',
                'siswa_idx' => 0,
            ],
            [
                'nik' => '0000019902860052',
                'nama_lengkap' => 'Ibu Sari Dewi',
                'no_hp' => '081234560052',
                'email' => null,
                'hubungan' => 'ibu',
                'siswa_idx' => 1,
            ],
            [
                'nik' => '0000019903870053',
                'nama_lengkap' => 'Bp. Rizky Hidayat',
                'no_hp' => '081234560053',
                'email' => null,
                'hubungan' => 'ayah',
                'siswa_idx' => 2,
            ],
            [
                'nik' => '0000019904880054',
                'nama_lengkap' => 'Ibu Nurhayati',
                'no_hp' => '081234560054',
                'email' => null,
                'hubungan' => 'ibu',
                'siswa_idx' => 3,
            ],
        ];

        foreach ($data as $item) {
            $siswa = $siswas[$item['siswa_idx']] ?? null;

            if (User::where('username', $item['nik'])->exists()) {
                $existing = OrangTua::whereHas('person', fn ($q) => $q->where('nik', $item['nik']))->first();
                if ($existing && $siswa) {
                    $this->tautkanOrangTuaSiswa($existing, $siswa, $item['hubungan'], true);
                }

                if ($siswa) {
                    $this->pastikanSiswaBisaLogin($siswa);
                }

                continue;
            }

            if (! $siswa) {
                continue;
            }

            $user = User::create([
                'name' => $item['nama_lengkap'],
                'email' => $item['email'],
                'username' => $item['nik'],
                'password' => Hash::make($item['nik']),
                'lembaga_id' => null,
                'email_verified_at' => now(),
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $user->assignRole('orang_tua');

            $person = Person::create([
                'yayasan_id' => $sdit->yayasan_id,
                'user_id' => $user->id,
                'nama_lengkap' => $item['nama_lengkap'],
                'nik' => $item['nik'],
                'no_hp' => $item['no_hp'],
                'email' => $item['email'],
            ]);

            $orangTua = OrangTua::create([
                'person_id' => $person->id,
            ]);

            $this->tautkanOrangTuaSiswa($orangTua, $siswa, $item['hubungan'], true);

            // Orang tua ini bisa login -- anaknya juga WAJIB bisa login, supaya
            // pasangan akun ortu+anak bisa sama-sama dipraktikkan (bukan cuma
            // salah satu pihak yang bisa masuk ke sistem).
            $this->pastikanSiswaBisaLogin($siswa);
        }

        // 1 Akun Orang Tua Demo untuk Login (password: 'password')
        $this->seedOrangTuaDemoLogin($sdit);
    }

    /**
     * Pastikan Siswa punya akun login sendiri (role `siswa`), username = NISN,
     * password awal = NISN (konsisten dengan pola username=NIK/password=NIK
     * yang dipakai orang tua di atas). Idempotent -- aman dipanggil ulang.
     */
    private function pastikanSiswaBisaLogin(Siswa $siswa): void
    {
        $person = $siswa->person;
        if (! $person) {
            return;
        }

        if ($person->user_id) {
            return; // sudah punya akun login (mis. dari SiswaSeeder::seedSiswaAccount()).
        }

        $username = $siswa->nisn;

        $user = User::firstOrCreate(
            ['username' => $username],
            [
                'name' => $person->nama_lengkap,
                'password' => Hash::make($username),
                'lembaga_id' => $siswa->lembaga_id,
                'email_verified_at' => now(),
                'is_active' => true,
                'must_change_password' => true,
            ]
        );

        if (! $user->hasRole('siswa')) {
            $user->assignRole('siswa');
        }

        $person->update(['user_id' => $user->id]);
    }

    private function seedOrangTuaDemoLogin(Lembaga $sdit): void
    {
        $nik = '0000019901850001';

        if (User::where('username', $nik)->exists()) {
            return;
        }

        $siswaTarget = Siswa::where('lembaga_id', $sdit->id)->skip(4)->first();
        if (! $siswaTarget) {
            return;
        }

        $user = User::create([
            'name' => 'Ibu Eliana (Demo Login)',
            'email' => 'ortu.sd@demo.test',
            'username' => $nik,
            'password' => Hash::make('password'),
            'lembaga_id' => null,
            'email_verified_at' => now(),
            'is_active' => true,
            'must_change_password' => false, // demo account, tidak perlu ganti password
        ]);
        $user->assignRole('orang_tua');

        $person = Person::create([
            'yayasan_id' => $sdit->yayasan_id,
            'user_id' => $user->id,
            'nama_lengkap' => 'Ibu Eliana (Demo Login)',
            'nik' => $nik,
            'no_hp' => '081234560001',
            'email' => 'ortu.sd@demo.test',
        ]);

        $orangTua = OrangTua::create([
            'person_id' => $person->id,
        ]);

        $this->tautkanOrangTuaSiswa($orangTua, $siswaTarget, 'ibu', true);

        // Anak dari akun demo login ini juga dapat login mudah (email + password
        // "password"), simetris dengan kemudahan akun orang tuanya -- pasangan
        // ortu.sd@demo.test + anaknya jadi bisa dipraktikkan bersamaan.
        $childPerson = $siswaTarget->person;
        if ($childPerson && ! $childPerson->user_id) {
            $childUser = User::firstOrCreate(
                ['email' => 'siswa.sd2@demo.test'],
                [
                    'name' => $childPerson->nama_lengkap,
                    'password' => 'password',
                    'lembaga_id' => $siswaTarget->lembaga_id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'must_change_password' => false,
                ]
            );
            if (! $childUser->hasRole('siswa')) {
                $childUser->assignRole('siswa');
            }
            $childPerson->update(['user_id' => $childUser->id]);
        }
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function tautkanOrangTuaSiswa(OrangTua $orangTua, Siswa $siswa, string $hubungan, bool $isKontakUtama): void
    {
        $alreadyLinked = DB::table('siswa_orang_tua')
            ->where('siswa_id', $siswa->id)
            ->where('orang_tua_id', $orangTua->id)
            ->exists();

        if ($alreadyLinked) {
            return;
        }

        if ($isKontakUtama) {
            DB::table('siswa_orang_tua')
                ->where('siswa_id', $siswa->id)
                ->update(['is_kontak_utama' => false]);
        }

        DB::table('siswa_orang_tua')->insert([
            'siswa_id' => $siswa->id,
            'orang_tua_id' => $orangTua->id,
            'hubungan' => $hubungan,
            'is_kontak_utama' => $isKontakUtama,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
