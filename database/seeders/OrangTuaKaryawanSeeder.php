<?php
// database/seeders/OrangTuaKaryawanSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
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

        $sdit    = Lembaga::where('npsn', '20223333')->firstOrFail();
        $yayasan = Yayasan::firstOrFail();

        // ── 1. Upgrade Guru BK di SDIT ───────────────────────────────────────
        $this->seedGuruBk($sdit);

        // ── 2. Karyawan Pool Yayasan (Psikolog) ─────────────────────────────
        $this->seedKaryawanPool($yayasan);

        // ── 3. Orang Tua siswa SDIT ───────────────────────────────────────────
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

        Guru::where('user_id', $user->id)->update([
            'jenis_ptk'            => 'guru_bk',
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
            'name'                 => 'Dr. Rahma Aulia, M.Psi.',
            'email'                => 'psikolog.pool@demo.test',
            'username'             => $nik,
            'password'             => Hash::make($nik),
            'lembaga_id'           => null, // pool = lintas lembaga
            'email_verified_at'    => now(),
            'is_active'            => true,
            'must_change_password' => true,
        ]);
        $user->assignRole('karyawan_pool');

        Karyawan::create([
            'user_id'              => $user->id,
            'yayasan_id'           => $yayasan->id,
            'lembaga_id'           => null,
            'jenis_karyawan_id'    => $jenisKaryawan->id,
            'nama'                 => 'Dr. Rahma Aulia, M.Psi.',
            'nik'                  => $nik,
            'no_hp'                => '081298765099',
            'status_aktif'         => 'aktif',
            'kapasitas_kasus_aktif' => null,
        ]);
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
                'nik'          => '0000019901850051',
                'nama_lengkap' => 'Drs. Ahmad Pratama',
                'no_hp'        => '081234560051',
                'email'        => null,
                'hubungan'     => 'ayah',
                'siswa_idx'    => 0,
            ],
            [
                'nik'          => '0000019902860052',
                'nama_lengkap' => 'Ibu Sari Dewi',
                'no_hp'        => '081234560052',
                'email'        => null,
                'hubungan'     => 'ibu',
                'siswa_idx'    => 1,
            ],
            [
                'nik'          => '0000019903870053',
                'nama_lengkap' => 'Bp. Rizky Hidayat',
                'no_hp'        => '081234560053',
                'email'        => null,
                'hubungan'     => 'ayah',
                'siswa_idx'    => 2,
            ],
            [
                'nik'          => '0000019904880054',
                'nama_lengkap' => 'Ibu Nurhayati',
                'no_hp'        => '081234560054',
                'email'        => null,
                'hubungan'     => 'ibu',
                'siswa_idx'    => 3,
            ],
        ];

        foreach ($data as $item) {
            $siswa = $siswas[$item['siswa_idx']] ?? null;

            if (User::where('username', $item['nik'])->exists()) {
                $existing = OrangTua::where('nik', $item['nik'])->first();
                if ($existing && $siswa) {
                    $this->tautkanOrangTuaSiswa($existing, $siswa, $item['hubungan'], true);
                }
                continue;
            }

            if (! $siswa) {
                continue;
            }

            $user = User::create([
                'name'                 => $item['nama_lengkap'],
                'email'                => $item['email'],
                'username'             => $item['nik'],
                'password'             => Hash::make($item['nik']),
                'lembaga_id'           => null,
                'email_verified_at'    => now(),
                'is_active'            => true,
                'must_change_password' => true,
            ]);
            $user->assignRole('orang_tua');

            $orangTua = OrangTua::create([
                'user_id'      => $user->id,
                'nama_lengkap' => $item['nama_lengkap'],
                'nik'          => $item['nik'],
                'no_hp'        => $item['no_hp'],
                'email'        => $item['email'],
            ]);

            $this->tautkanOrangTuaSiswa($orangTua, $siswa, $item['hubungan'], true);
        }

        // 1 Akun Orang Tua Demo untuk Login (password: 'password')
        $this->seedOrangTuaDemoLogin($sdit);
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
            'name'                 => 'Ibu Eliana (Demo Login)',
            'email'                => 'ortu.sd@demo.test',
            'username'             => $nik,
            'password'             => Hash::make('password'),
            'lembaga_id'           => null,
            'email_verified_at'    => now(),
            'is_active'            => true,
            'must_change_password' => false, // demo account, tidak perlu ganti password
        ]);
        $user->assignRole('orang_tua');

        $orangTua = OrangTua::create([
            'user_id'      => $user->id,
            'nama_lengkap' => 'Ibu Eliana (Demo Login)',
            'nik'          => $nik,
            'no_hp'        => '081234560001',
            'email'        => 'ortu.sd@demo.test',
        ]);

        $this->tautkanOrangTuaSiswa($orangTua, $siswaTarget, 'ibu', true);
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
            'siswa_id'        => $siswa->id,
            'orang_tua_id'    => $orangTua->id,
            'hubungan'        => $hubungan,
            'is_kontak_utama' => $isKontakUtama,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
