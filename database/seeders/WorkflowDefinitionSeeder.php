<?php

namespace Database\Seeders;

use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Models\Role;
use Illuminate\Database\Seeder;

class WorkflowDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Workflow Pengadaan Sarpras Sekolah
        $pengadaan = WorkflowDefinition::updateOrCreate(
            ['code' => 'PENGADAAN_SARPRAS'],
            [
                'nama_workflow' => 'Pengadaan Sarana & Prasarana',
                'deskripsi' => 'Alur persetujuan usulan belanja & inventaris dari unit lembaga ke yayasan.',
                'is_active' => true,
            ]
        );

        $this->assertRoleExists('kepala_sekolah');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $pengadaan->id, 'step_number' => 1],
            [
                'step_name' => 'Verifikasi Internal Kepala Sekolah',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'kepala_sekolah',
                'scope_level' => 'lembaga',
                'is_final_step' => false,
            ]
        );

        $this->assertRoleExists('bendahara_yayasan');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $pengadaan->id, 'step_number' => 2],
            [
                'step_name' => 'Persetujuan & Pencairan Yayasan',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'bendahara_yayasan',
                'scope_level' => 'yayasan',
                'is_final_step' => true,
            ]
        );

        // 2. Workflow Persetujuan Rapor Semester
        $rapor = WorkflowDefinition::updateOrCreate(
            ['code' => 'RAPOR_SEMESTER'],
            [
                'nama_workflow' => 'Persetujuan Rapor Semester',
                'deskripsi' => 'Alur verifikasi Waka Kurikulum dan persetujuan akhir Kepala Sekolah untuk pengajuan rapor per kelas per semester.',
                'is_active' => true,
            ]
        );

        $this->assertRoleExists('admin_akademik');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $rapor->id, 'step_number' => 1],
            [
                'step_name' => 'Verifikasi Waka Kurikulum',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'admin_akademik',
                'scope_level' => 'lembaga',
                'is_final_step' => false,
            ]
        );

        $this->assertRoleExists('kepala_sekolah');
        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $rapor->id, 'step_number' => 2],
            [
                'step_name' => 'Persetujuan Akhir Kepala Sekolah',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'kepala_sekolah',
                'scope_level' => 'lembaga',
                'is_final_step' => true,
            ]
        );
    }

    private function assertRoleExists(string $roleName): void
    {
        abort_unless(
            Role::where('name', $roleName)->exists(),
            500,
            "WorkflowDefinitionSeeder: role '{$roleName}' tidak ditemukan — cek RoleSeeder."
        );
    }
}
