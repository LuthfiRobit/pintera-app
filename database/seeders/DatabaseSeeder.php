<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            YayasanSeeder::class,
            JabatanTambahanMasterSeeder::class,
            LembagaSeeder::class,
            EssentialUserSeeder::class,
            UserSeeder::class,
            TahunAjaranSeeder::class,
            SemesterSeeder::class,
            GuruSeeder::class,
            RiwayatPendidikanGuruSeeder::class,
            SertifikasiGuruSeeder::class,
            GuruJabatanTambahanSeeder::class,
            LembagaDataPeriodikSeeder::class,
            LayananKhususLembagaSeeder::class,
            ProgramInklusiLembagaSeeder::class,
            EkstrakurikulerLembagaSeeder::class,
            JenisTesMasterSeeder::class,
            GelombangPpdbSeeder::class,
            JalurPpdbSeeder::class,
            FormulirFieldSeeder::class,
            DokumenSyaratPpdbSeeder::class,
            SeleksiPpdbSeeder::class,
            JenisTagihanSeeder::class,
            NominalTagihanJalurSeeder::class,
            M3DemoDataSeeder::class,
        ]);
    }
}
