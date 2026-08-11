<?php

namespace App\Models;

use App\Exceptions\InsufficientBalanceException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Services\Finance\AutoAllocationEngine;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'siswa_id',
        'balance',
        'va_number',
        'total_topup',
        'total_deducted',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(WalletMutasi::class);
    }

    /**
     * Top-up saldo secara aman dengan lock for update.
     */
    public function topup(float $amount, ?Pembayaran $pembayaran = null, ?string $keterangan = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount top-up harus lebih dari 0");
        }

        DB::transaction(function () use ($amount, $pembayaran, $keterangan) {
            // Pessimistic lock on this row
            $wallet = self::where('id', $this->id)->lockForUpdate()->first();
            
            $saldoSebelum = $wallet->balance;
            $saldoSesudah = $saldoSebelum + $amount;

            $wallet->balance = $saldoSesudah;
            $wallet->total_topup += $amount;
            $wallet->save();

            $wallet->mutasi()->create([
                'pembayaran_id' => $pembayaran?->id,
                'tipe'          => 'topup',
                'amount'        => $amount,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan'    => $keterangan ?? 'Top-up wallet',
            ]);

            // Sync current instance
            $this->refresh();
        });

        // Cek toggle auto debit dan jalankan engine jika aktif
        // (Di luar transaction agar lock wallet terlepas dulu saat proses alokasi berjalan)
        if (SystemSetting::getResolved('auto_debit_enabled', $this->siswa->lembaga_id, true)) {
            app(AutoAllocationEngine::class)->run($this);
        }
    }

    /**
     * Debit saldo secara aman dengan pengecekan strict.
     */
    public function debit(float $amount, ?Pembayaran $pembayaran = null, ?string $keterangan = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount debit harus lebih dari 0");
        }

        DB::transaction(function () use ($amount, $pembayaran, $keterangan) {
            // Pessimistic lock
            $wallet = self::where('id', $this->id)->lockForUpdate()->first();
            
            if ($wallet->balance < $amount) {
                throw new InsufficientBalanceException("Saldo tidak mencukupi untuk debit sejumlah " . $amount);
            }

            $saldoSebelum = $wallet->balance;
            $saldoSesudah = $saldoSebelum - $amount;

            $wallet->balance = $saldoSesudah;
            $wallet->total_deducted += $amount;
            $wallet->save();

            $wallet->mutasi()->create([
                'pembayaran_id' => $pembayaran?->id,
                'tipe'          => 'debit',
                'amount'        => $amount,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan'    => $keterangan ?? 'Debit wallet',
            ]);

            // Sync current instance
            $this->refresh();
        });
    }
}
