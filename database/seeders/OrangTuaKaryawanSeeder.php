<?php
// database/seeders/OrangTuaKaryawanSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\JenisKaryawanMaster;
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
        $kbit    = Lembaga::where('npsn', '20223311')->firstOrFail();
        $tkit    = Lembaga::where('npsn', '20223322')->firstOrFail();
        $sdit    = Lembaga::where('npsn', '20223333')->firstOrFail();
        $smpit   = Lembaga::where('npsn', '20223344')->firstOrFail();
        $yayasan = Yayasan::firstOrFail();

        // ── 1. Upgrade Guru BK di SMPIT ─────────────────────────────────────
        $this->seedGuruBk($smpit);

        // ── 2. Karyawan Pool Yayasan (Psikolog) ─────────────────────────────
        $this->seedKaryawanPool($yayasan);

        // ── 3. Orang Tua siswa SMPIT ─────────────────────────────────────────
        $this->seedOrangTua($smpit);

        // ── 4. Orang Tua lintas lembaga SDIT+SMPIT (demo) ────────────────────
        $this->seedOrangTuaLintasLembaga($sdit, $smpit);

        // ── 5. Orang Tua demo KB & TK (untuk skenario kasus pendampingan
        //      ringan yang diajukan langsung oleh orang tua) ─────────────────
        $this->seedOrangTuaDemoKb($kbit);
        $this->seedOrangTuaDemoTk($tkit);
    }

    // ── Guru BK ─────────────────────────────────────────────────────────────

    private function seedGuruBk(Lembaga $smpit): void
    {
        // Jadikan Budi Santoso sebagai Guru BK SMPIT
        $user = User::where('email', 'budi.santoso@permata.sch.id')->first();
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

        $nik = '3273019901900099';

        if (User::where('username', $nik)->exists()) {
            return;
        }

        $user = User::create([
            'name'                 => 'Dr. Rahma Aulia, M.Psi.',
            'email'                => 'psikolog.pool@permatakraksaan.sch.id',
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

    // ── Orang Tua SMPIT ─────────────────────────────────────────────────────

    private function seedOrangTua(Lembaga $smpit): void
    {
        $siswas = Siswa::where('lembaga_id', $smpit->id)->take(6)->get();

        if ($siswas->isEmpty()) {
            return;
        }

        $data = [
            [
                'nik'          => '3273019901850051',
                'nama_lengkap' => 'Drs. Ahmad Pratama',
                'no_hp'        => '081234560051',
                'email'        => null,
                'hubungan'     => 'ayah',
                'siswa_idx'    => 0,
            ],
            [
                'nik'          => '3273019902860052',
                'nama_lengkap' => 'Ibu Sari Dewi',
                'no_hp'        => '081234560052',
                'email'        => null,
                'hubungan'     => 'ibu',
                'siswa_idx'    => 1,
            ],
            [
                'nik'          => '3273019903870053',
                'nama_lengkap' => 'Bp. Rizky Hidayat',
                'no_hp'        => '081234560053',
                'email'        => null,
                'hubungan'     => 'ayah',
                'siswa_idx'    => 2,
            ],
            [
                'nik'          => '3273019904880054',
                'nama_lengkap' => 'Ibu Nurhayati',
                'no_hp'        => '081234560054',
                'email'        => null,
                'hubungan'     => 'ibu',
                'siswa_idx'    => 3,
            ],
        ];

        foreach ($data as $item) {
            $this->createOrangTua($item, $siswas);
        }

        // Akun demo orang tua (password = "password", mudah dipakai login)
        $this->seedOrangTuaDemo($smpit);
    }

    private function createOrangTua(array $item, $siswas): void
    {
        if (User::where('username', $item['nik'])->exists()) {
            $existing = OrangTua::where('nik', $item['nik'])->first();
            if ($existing && isset($siswas[$item['siswa_idx']])) {
                $this->tautkanOrangTuaSiswa($existing, $siswas[$item['siswa_idx']], $item['hubungan'], true);
            }
            return;
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

        if (isset($siswas[$item['siswa_idx']])) {
            $this->tautkanOrangTuaSiswa($orangTua, $siswas[$item['siswa_idx']], $item['hubungan'], true);
        }
    }

    private function seedOrangTuaDemo(Lembaga $smpit): void
    {
        $nik = '3273019901850001';

        if (User::where('username', $nik)->exists()) {
            return;
        }

        $siswaTarget = Siswa::where('lembaga_id', $smpit->id)->skip(4)->first();
        if (! $siswaTarget) {
            return;
        }

        $user = User::create([
            'name'                 => 'Ibu Eliana (Demo Login)',
            'email'                => 'ortu.demo@permatakraksaan.sch.id',
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
            'email'        => 'ortu.demo@permatakraksaan.sch.id',
        ]);

        $this->tautkanOrangTuaSiswa($orangTua, $siswaTarget, 'ibu', true);
    }

    // ── Orang Tua demo KB & TK ──────────────────────────────────────────────

    private function seedOrangTuaDemoKb(Lembaga $kbit): void
    {
        $nik = '3273019901850002';

        if (User::where('username', $nik)->exists()) {
            return;
        }

        $siswaTarget = Siswa::where('lembaga_id', $kbit->id)->first();
        if (! $siswaTarget) {
            return;
        }

        $user = User::create([
            'name'                 => 'Ibu Wulan (Demo Login KB)',
            'email'                => 'ortu.kb.demo@permatakraksaan.sch.id',
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
            'nama_lengkap' => 'Ibu Wulan (Demo Login KB)',
            'nik'          => $nik,
            'no_hp'        => '081234560002',
            'email'        => 'ortu.kb.demo@permatakraksaan.sch.id',
        ]);

        $this->tautkanOrangTuaSiswa($orangTua, $siswaTarget, 'ibu', true);
    }

    private function seedOrangTuaDemoTk(Lembaga $tkit): void
    {
        $nik = '3273019901850003';

        if (User::where('username', $nik)->exists()) {
            return;
        }

        $siswaTarget = Siswa::where('lembaga_id', $tkit->id)->first();
        if (! $siswaTarget) {
            return;
        }

        $user = User::create([
            'name'                 => 'Bp. Hendra (Demo Login TK)',
            'email'                => 'ortu.tk.demo@permatakraksaan.sch.id',
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
            'nama_lengkap' => 'Bp. Hendra (Demo Login TK)',
            'nik'          => $nik,
            'no_hp'        => '081234560003',
            'email'        => 'ortu.tk.demo@permatakraksaan.sch.id',
        ]);

        $this->tautkanOrangTuaSiswa($orangTua, $siswaTarget, 'ayah', true);
    }

    // ── Demo lintas lembaga ─────────────────────────────────────────────────

    private function seedOrangTuaLintasLembaga(Lembaga $sdit, Lembaga $smpit): void
    {
        $siswaSdit  = Siswa::where('lembaga_id', $sdit->id)->first();
        $siswaSmpit = Siswa::where('lembaga_id', $smpit->id)->skip(5)->first();

        if (! $siswaSdit || ! $siswaSmpit) {
            return;
        }

        $nik = '3273019905890055';

        if (User::where('username', $nik)->exists()) {
            return;
        }

        $user = User::create([
            'name'                 => 'Bp. Fahri (Demo Lintas Lembaga)',
            'email'                => 'ortu.lintaslembaga@gmail.com',
            'username'             => $nik,
            'password'             => Hash::make($nik),
            'lembaga_id'           => null,
            'email_verified_at'    => now(),
            'is_active'            => true,
            'must_change_password' => true,
        ]);
        $user->assignRole('orang_tua');

        $orangTua = OrangTua::create([
            'user_id'      => $user->id,
            'nama_lengkap' => 'Bp. Fahri (Demo Lintas Lembaga)',
            'nik'          => $nik,
            'no_hp'        => '081234560055',
            'email'        => 'ortu.lintaslembaga@gmail.com',
        ]);

        // Anak di SDIT
        $this->tautkanOrangTuaSiswa($orangTua, $siswaSdit, 'ayah', true);
        // Anak di SMPIT (lintas lembaga)
        $this->tautkanOrangTuaSiswa($orangTua, $siswaSmpit, 'ayah', true);
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
