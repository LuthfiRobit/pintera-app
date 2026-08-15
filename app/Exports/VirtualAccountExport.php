<?php

namespace App\Exports;

use App\Models\BriVirtualAccount;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VirtualAccountExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly ?int $lembagaId)
    {
    }

    public function headings(): array
    {
        return ['Nama Siswa', 'NIS', 'Kelas', 'Lembaga', 'Nomor VA', 'Tanggal Dibuat', 'Saldo Wallet'];
    }

    public function collection()
    {
        return BriVirtualAccount::where('va_type', 'WALLET_PERMANENT')
            ->whereHas('wallet.siswa', fn ($q) => $q->where('lembaga_id', $this->lembagaId))
            ->with(['wallet.siswa.kelas', 'wallet.siswa.lembaga'])
            ->get()
            ->map(function (BriVirtualAccount $va) {
                $siswa = $va->wallet->siswa;

                return [
                    $siswa->nama_lengkap,
                    $siswa->nis,
                    $siswa->kelas->nama ?? '-',
                    $siswa->lembaga->nama ?? '-',
                    $va->va_number,
                    $va->created_at->format('d-m-Y'),
                    number_format((float) $va->wallet->balance, 0, ',', '.'),
                ];
            });
    }
}
