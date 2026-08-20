<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);

        // Auto-discovers permissions referenced via $this->authorize('module.action') in
        // app/Http/Controllers (e.g. the academic-module permissions for Siswa, Kelas, Mata
        // Pelajaran) that PermissionSeeder deliberately doesn't hand-list. Must run before
        // RoleSeeder so yayasan_super_admin's Permission::pluck('name')->all() grant includes
        // them.
        Artisan::call('permissions:sync');

        $this->call([
            RoleSeeder::class,
            YayasanSeeder::class,
            JabatanTambahanMasterSeeder::class,
            JenisKaryawanMasterSeeder::class,
            WhatsAppTemplateSeeder::class,
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
            CalonMuridSeeder::class,
            PendaftaranSeeder::class,
            DokumenPendaftaranSeeder::class,
            HasilSeleksiSeeder::class,
            SkPpdbSeeder::class,
            TagihanSeeder::class,
            TagihanItemSeeder::class,
            SkemaCicilanSeeder::class,
            CicilanSeeder::class,
            PembayaranSeeder::class,
            AkunPendaftarSeeder::class,
            GelombangJalurSeeder::class,
            MataPelajaranSeeder::class,
            PolaJamSeeder::class,
            JamPelajaranSeeder::class,
            KelasSeeder::class,
            SiswaSeeder::class,
            JadwalPelajaranSeeder::class,
            SesiPembelajaranSeeder::class,
            PresensiSeeder::class,
            KomponenPenilaianSeeder::class,
            AsesmenSeeder::class,
            NilaiSiswaSeeder::class,
            OrangTuaKaryawanSeeder::class,
            PendampinganSeeder::class,
            KeuanganDemoSeeder::class,
            WorkflowDefinitionSeeder::class,
            SarprasPengadaanDemoSeeder::class,
            RolePermissionAssignmentSeeder::class,
        ]);
    }
}
