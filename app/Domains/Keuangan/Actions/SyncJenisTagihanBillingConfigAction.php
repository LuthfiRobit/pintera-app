<?php

namespace App\Domains\Keuangan\Actions;

use App\Domains\Keuangan\Models\JenisTagihan;

class SyncJenisTagihanBillingConfigAction
{
    /**
     * Parse array payload dari form/request dan update kolom-kolom billing pada $jenisTagihan.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(JenisTagihan $jenisTagihan, array $input): void
    {
        $kategori = $jenisTagihan->kategori;

        // Kategori pendaftaran / daftar_ulang tidak memiliki konfigurasi billing fleksibel
        if (in_array($kategori, ['pendaftaran', 'daftar_ulang'], true)) {
            return;
        }

        $mode = $input['mode'] ?? 'manual';
        $updateData = ['mode' => $mode];

        if ($mode === 'manual') {
            $updateData['default_amount'] = ! empty($input['default_amount']) ? (float) $input['default_amount'] : null;
            $updateData['due_days'] = ! empty($input['due_days']) ? (int) $input['due_days'] : null;
            $updateData['interval'] = null;
            $updateData['billing_cycle'] = null;
            $updateData['cron_expression'] = null;
            $updateData['generate_day'] = null;
            $updateData['generate_month'] = null;
            $updateData['trigger_event'] = null;
            $updateData['is_active'] = false;
        } elseif ($mode === 'otomatis') {
            $interval = $input['interval'] ?? 'monthly';
            $updateData['interval'] = $interval;
            $updateData['billing_cycle'] = $interval;
            $updateData['default_amount'] = (float) ($input['default_amount'] ?? 0);
            $updateData['due_days'] = ! empty($input['due_days']) ? (int) $input['due_days'] : 30;
            $updateData['is_active'] = ! empty($input['is_active']);
            $updateData['trigger_event'] = null;

            if ($interval === 'monthly') {
                $day = (int) ($input['generate_day'] ?? 1);
                $updateData['generate_day'] = $day;
                $updateData['generate_month'] = null;
                $updateData['cron_expression'] = "0 0 {$day} * *";
            } elseif ($interval === 'yearly') {
                $day = (int) ($input['generate_day'] ?? 1);
                $month = (int) ($input['generate_month'] ?? 1);
                $updateData['generate_day'] = $day;
                $updateData['generate_month'] = $month;
                $updateData['cron_expression'] = "0 0 {$day} {$month} *";
            } elseif ($interval === 'custom_cron') {
                $updateData['cron_expression'] = $input['cron_expression'] ?? null;
                $updateData['generate_day'] = null;
                $updateData['generate_month'] = null;
            }
        } elseif ($mode === 'event_driven') {
            $updateData['trigger_event'] = $input['trigger_event'] ?? null;
            $updateData['default_amount'] = (float) ($input['default_amount'] ?? 0);
            $updateData['due_days'] = ! empty($input['due_days']) ? (int) $input['due_days'] : 30;
            $updateData['is_active'] = ! empty($input['is_active']);
            $updateData['interval'] = null;
            $updateData['billing_cycle'] = null;
            $updateData['cron_expression'] = null;
            $updateData['generate_day'] = null;
            $updateData['generate_month'] = null;
        }

        $jenisTagihan->update($updateData);
    }
}
