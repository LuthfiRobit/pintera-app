<?php

namespace App\Models;

use App\Exceptions\AutoAllocationFailedException;
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

    public function briVirtualAccounts(): HasMany
    {
        return $this->hasMany(\App\Domains\Keuangan\Models\BriVirtualAccount::class);
    }

    /**
     * Top-up saldo secara aman dengan lock for update.
     */
    public function topup(float $amount, ?\App\Domains\Keuangan\Models\Pembayaran $pembayaran = null, ?string $keterangan = null): void
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
            try {
                app(AutoAllocationEngine::class)->run($this);
            } catch (\Throwable $e) {
                // The balance increment above has ALREADY committed at this point --
                // wrap in a distinct exception type so callers can tell "balance was
                // credited, only allocation failed" apart from "balance never credited".
                throw new AutoAllocationFailedException(
                    'AutoAllocationEngine::run() gagal setelah saldo wallet berhasil di-topup: '.$e->getMessage(),
                    $e
                );
            }
        }
    }

    /**
     * Debit saldo secara aman dengan pengecekan strict.
     * Gunakan ini dari luar engine (membungkus dalam transaction sendiri).
     */
    public function debit(float $amount, ?\App\Domains\Keuangan\Models\Pembayaran $pembayaran = null, ?string $keterangan = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount debit harus lebih dari 0");
        }

        DB::transaction(function () use ($amount, $pembayaran, $keterangan) {
            $this->debitCore($amount, $pembayaran, $keterangan, lockRow: true);
        });
    }

    /**
     * Debit saldo tanpa membuka transaction baru.
     * Dipakai oleh AutoAllocationEngine yang sudah dalam transaction + lockForUpdate.
     * Tidak melakukan re-lock (wallet sudah dikunci oleh caller).
     *
     * @internal Jangan pakai dari luar kecuali dalam konteks DB::transaction yang sudah ada.
     */
    public function debitWithinTransaction(float $amount, ?\App\Domains\Keuangan\Models\Pembayaran $pembayaran = null, ?string $keterangan = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount debit harus lebih dari 0");
        }

        $this->debitCore($amount, $pembayaran, $keterangan, lockRow: false);
    }

    /**
     * Core debit logic, bisa dipakai dengan atau tanpa lockForUpdate.
     */
    private function debitCore(float $amount, ?\App\Domains\Keuangan\Models\Pembayaran $pembayaran, ?string $keterangan, bool $lockRow): void
    {
        // Pessimistic lock (opsional, tidak dibutuhkan jika caller sudah lock)
        $wallet = $lockRow
            ? self::where('id', $this->id)->lockForUpdate()->first()
            : self::where('id', $this->id)->first();

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
    }
}
