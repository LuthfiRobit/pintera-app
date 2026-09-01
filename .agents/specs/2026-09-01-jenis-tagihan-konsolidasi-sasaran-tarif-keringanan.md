# Spec: Konsolidasi Target Sasaran, Tarif Berdimensi & Keringanan di Form Jenis Tagihan + Engine Recalculate Tagihan

- **Branch**: `keuangan-v2`
- **Tanggal**: 1 September 2026
- **Konteks**: Audit menyeluruh (dengan bukti kode langsung) menemukan 7 masalah bisnis nyata di 3 fitur Jenis Tagihan (Target Sasaran, Tarif Berdimensi, Keringanan & Potongan Biaya). User eksplisit ingin SEMUA pengaturan ini selesai di satu form Jenis Tagihan — tidak perlu lagi ke halaman edit Siswa terpisah untuk assign keringanan. Spec ini hasil diskusi panjang bertahap (audit → 3 putaran pendalaman soal keputusan finansial sensitif → 3 tambahan final) — semua keputusan di bawah ini FINAL.

---

## 1. Ringkasan

Tiga perubahan besar yang saling berkaitan:

1. **Perbaikan teknis murni** (§3) — field kriteria yang mustahil berfungsi dihapus, validasi kelas diperketat, satu temuan audit awal dikoreksi (bukan bug).
2. **Redesain UI Form** (§4) — live preview jumlah siswa yang match di 3 kartu, kontrol urutan prioritas eksplisit untuk Tarif, dan widget assignment keringanan langsung di form (reuse backend yang sudah ada).
3. **Engine Recalculate Tagihan** (§5) — mekanisme generik untuk menghitung ulang `net_amount` tagihan yang belum lunas ketika Keringanan/Tarif berubah setelah tagihan dibuat, dengan pagar pengaman ketat untuk skenario finansial berisiko (overpayment, cicilan).

## 2. Non-Goals (eksplisit di luar scope)

1. **Rekonsiliasi tagihan bercicilan** (`SkemaCicilan`/`Cicilan`) yang terdampak perubahan Keringanan/Tarif — ditandai "perlu ditinjau", TIDAK diotomatisasi. Alasan lengkap di §5.2.
2. **Trigger recalc dari perubahan atribut siswa lain** (siswa pindah kelas, dll.) — hanya 4 sumber trigger yang didaftar di §5.4, tidak ditambah lebih jauh di paket ini.
3. **Perubahan makna kolom `kategori`** — sudah final dari spec sebelumnya, tidak disentuh di sini.

## 3. Perbaikan Teknis Murni (tanpa keputusan bisnis)

### 3.1 Hapus field kriteria "lembaga" dari Target Sasaran

`JenisTagihan` sudah terkunci ke satu `lembaga_id` sejak dibuat. Base query `JenisTagihanSasaranMatcher::resolveTargetSiswa()` (baris 26) hard-filter ke `lembaga_id` itu — memilih kriteria "lembaga" lain di form cuma menghasilkan 0 siswa cocok secara diam-diam, bukan penargetan lintas-lembaga sungguhan. Field ini dihapus total dari `KRITERIA_FIELDS` (`JenisTagihanController.php:34`) dan dari opsi dropdown di form.

### 3.2 Perketat validasi kriteria "kelas"

`JenisTagihanController::billingRules()` sekarang memvalidasi `sasaran.*.kriteria.*.value.*`/`tarif.*.kriteria.*.value.*` cuma sebagai `['string', 'max:255']` — tidak mengecek `kelas_id` itu benar milik lembaga yang sama. Diganti jadi tervalidasi silang:

**Keputusan implementasi**: pakai `Validator::after()` (bukan `Rule` object bersarang) — Laravel tidak native mendukung "validasi field X cuma kalau field saudaranya di array yang sama bernilai tertentu" untuk struktur bersarang seperti `sasaran.*.kriteria.*`. Tambahkan closure `after()` di `billingRules()` (atau langsung di `store()`/`update()` setelah `$request->validate($this->billingRules(...))` berhasil) yang meng-iterasi ulang payload `sasaran`/`tarif`, dan HANYA untuk baris kriteria dengan `field === 'kelas'`, cek `Kelas::where('lembaga_id', $lembagaId)->whereIn('id', $kriteriaData['value'])->count() === count($kriteriaData['value'])` — kalau tidak sama, tambahkan error via `$validator->errors()->add(...)`. Kriteria lain (`jenis_kelamin`, `status_siswa`, dst.) tetap divalidasi longgar seperti sekarang, tidak kena exists-check apapun.

### 3.3 Temuan #6 dikoreksi — bukan bug aktif

Audit awal mencurigai divergensi antara jalur SQL (`whereHas('person', ...)`) dan jalur PHP (`$siswa->jenis_kelamin`) untuk cek jenis kelamin. **Terverifikasi BUKAN bug**: `Siswa::getJenisKelaminAttribute()` (`Siswa.php:61-64`) sudah proxy ke `$this->person?->jenis_kelamin`, dan kolom `jenis_kelamin` lokal di tabel `siswa` sudah di-drop total sejak identity-v1 Task 28 (dikonfirmasi via `database/schema/mysql-schema.sql` — kolom itu tidak ada). Kedua jalur baca dari sumber yang **sama persis**. **Tidak ada perubahan kode untuk poin ini** — dicatat di sini murni sebagai dokumentasi audit-trail, supaya tidak dianggap masalah terbuka di masa depan.

## 4. Redesain UI Form

### 4.1 Live Preview di 3 kartu

Endpoint AJAX baru (`POST admin/jenis-tagihan/preview-sasaran` atau serupa) yang menerima payload draft form (BELUM disimpan) dan menjalankan `JenisTagihanSasaranMatcher`/`TagihanNominalResolver` terhadap kriteria yang sedang diketik admin, mengembalikan jumlah siswa yang match. Dipasang di:
- **Target Sasaran**: "N siswa akan kena tagihan ini" + link lihat daftar.
- **Tarif Berdimensi**: per grup, "N siswa masuk grup tarif ini".
- **Keringanan**: per rule kategori+nominal, "N siswa termasuk kategori ini saat ini" — kalau `N === 0`, tampilkan peringatan eksplisit: *"Belum ada siswa di kategori ini — potongan ini belum berefek ke siapapun."*

### 4.2 Kolom `priority` eksplisit untuk grup Tarif

**DDL**:
```sql
ALTER TABLE jenis_tagihan_sasaran_grup ADD COLUMN priority INT UNSIGNED NULL AFTER nominal;
```

**Backfill** (satu migration, deterministik, mengikuti pola "backfill kondisional dalam migration yang sama" dari spec Tipe Penjadwalan sebelumnya):
```sql
UPDATE jenis_tagihan_sasaran_grup g
JOIN (
    SELECT id, ROW_NUMBER() OVER (PARTITION BY jenis_tagihan_id ORDER BY id) AS rn
    FROM jenis_tagihan_sasaran_grup WHERE tipe = 'tarif'
) ranked ON g.id = ranked.id
SET g.priority = ranked.rn;
```
Partition PER `jenis_tagihan_id` (bukan global) menjamin urutan relatif antar-grup-tarif dalam SATU Jenis Tagihan sama persis seperti urutan `id` sekarang — grup yang menang sebelum migrasi TETAP menang sesudahnya, tidak ada perubahan perilaku tiba-tiba.

`TagihanNominalResolver::resolveNominal()` (baris 43) diubah dari `orderBy('id')` jadi `orderBy('priority')`. UI dapat kontrol naik/turun urutan eksplisit (drag handle atau tombol ↑↓) dengan badge nomor prioritas kelihatan di setiap kartu grup Tarif.

### 4.3 Widget assignment Keringanan langsung di form

Kartu Keringanan mendapat sub-panel: daftar siswa yang match Target Sasaran form ini (dari live preview §4.1), dengan checkbox per kategori keringanan yang didefinisikan di form. Toggle checkbox memanggil `SiswaKeringananController::store()`/`destroy()` **yang sudah ada** via AJAX — **tidak ada backend baru untuk assignment itu sendiri**, cuma permukaan UI baru.

**Prinsip yang dijaga ketat**: `SiswaKeringanan` tetap data GLOBAL milik siswa (status "yatim piatu" bukan milik satu Jenis Tagihan tertentu) — widget ini cuma PINTU MASUK tambahan ke data yang sama, bukan scoping baru. Halaman assign keringanan yang sudah ada di edit Siswa **tetap dipertahankan** sebagai jalur akses kedua/alternatif.

### 4.4 Kolom `bisa_digabung` pada `KategoriKeringanan`

**DDL**: `ALTER TABLE kategori_keringanan ADD COLUMN bisa_digabung BOOLEAN NOT NULL DEFAULT FALSE;`

**WAJIB diimplementasikan logic penjumlahan AKTUAL** di `TagihanNominalResolver::resolveDiscount()` — bukan kolom skema mati (menghindari pola `last_generated_period` yang sudah dikoreksi di paket sebelumnya):

```php
private function resolveDiscount(Siswa $siswa, JenisTagihan $jenisTagihan, float $nominal): array
{
    $rules = /* ...query existing, JOIN ke kategori_keringanan untuk ambil bisa_digabung... */;

    $nonCombinable = $rules->where('kategori.bisa_digabung', false);
    $combinable = $rules->where('kategori.bisa_digabung', true);

    $bestNonCombinable = 0.0;
    $bestType = null;
    foreach ($nonCombinable as $rule) {
        $amount = $this->hitungNominalPotongan($rule, $nominal);
        if ($amount > $bestNonCombinable) {
            $bestNonCombinable = $amount;
            $bestType = $rule->tipe_potongan;
        }
    }

    $totalCombinable = $combinable->sum(fn ($rule) => $this->hitungNominalPotongan($rule, $nominal));

    $totalDiscount = min($nominal, $bestNonCombinable + $totalCombinable); // clamp, net_amount tidak boleh negatif

    return [$totalDiscount, $bestType ?? ($totalCombinable > 0 ? 'gabungan' : null)];
}
```

Default `bisa_digabung = false` untuk SEMUA kategori existing — perilaku hari ini (best-only) tidak berubah kecuali admin sengaja mengaktifkan per kategori.

## 5. Engine Recalculate Tagihan

Ini bagian paling sensitif — menyangkut uang sungguhan siswa aktif. Setiap keputusan di bawah sudah melalui 3 putaran pendalaman eksplisit.

### 5.1 `RecalculateTagihanNominalAction`

Untuk 1 `Tagihan`, jalankan ULANG PENUH `resolveNominal()` DAN `resolveDiscount()` (update `total_tagihan`, `discount_amount`, `discount_type`, `net_amount` sekaligus) — **selalu re-resolve keduanya**, apapun sumber trigger-nya (§5.4), supaya invariant `net_amount = total_tagihan - discount_amount` tidak pernah pincang.

```php
class RecalculateTagihanNominalAction
{
    public function __construct(
        private readonly TagihanNominalResolver $nominalResolver,
        private readonly TagihanStatusResolver $statusResolver,
    ) {}

    public function execute(int $tagihanId): void
    {
        DB::transaction(function () use ($tagihanId) {
            $tagihan = Tagihan::withoutGlobalScope(TenantScope::class)->lockForUpdate()->find($tagihanId);
            if ($tagihan === null || in_array($tagihan->status, ['lunas', 'dibatalkan'], true)) {
                return;
            }

            // Guard defensif WAJIB, terlepas dari bagaimana query pemanggil (§5.4) ditulis:
            // Sasaran/Tarif/Keringanan cuma berlaku untuk tagihan Siswa (mode billing berulang).
            // Tagihan berkategori PPDB (tagihable_type = Pendaftaran::class) pakai mekanisme
            // nominal-per-jalur yang sama sekali berbeda -- resolveNominal()/resolveDiscount()
            // akan salah/error kalau dipaksa jalan terhadap Pendaftaran. No-op, bukan exception,
            // karena guard ini murni pertahanan lapis kedua terhadap kesalahan pemanggil, bukan
            // kondisi error yang perlu ditinjau admin.
            if ($tagihan->tagihable_type !== Siswa::class) {
                return;
            }

            $siswa = $tagihan->tagihable; // MorphTo, dijamin instance Siswa oleh guard di atas
            $jenisTagihan = $tagihan->jenisTagihan;
            $resolved = $this->nominalResolver->resolve($siswa, $jenisTagihan);
            $newNetAmount = max(0, $resolved['nominal'] - $resolved['discount_amount']);

            $adaOverpayment = $newNetAmount < $tagihan->paid_amount;
            $adaCicilan = $tagihan->skemaCicilan()->exists();

            if ($adaOverpayment || $adaCicilan) {
                $alasan = $adaOverpayment
                    ? "Net amount baru Rp".number_format($newNetAmount, 0, ',', '.')." lebih kecil dari yang sudah dibayar Rp".number_format($tagihan->paid_amount, 0, ',', '.')
                    : "Tagihan sudah punya skema cicilan -- rekonsiliasi manual via halaman cicilan.";

                $tagihan->update(['perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => $alasan]);
                return;
            }

            $netAmountBerubah = (float) $tagihan->net_amount !== $newNetAmount;

            $tagihan->total_tagihan = $resolved['nominal'];
            $tagihan->discount_amount = $resolved['discount_amount'];
            $tagihan->discount_type = $resolved['discount_type'];
            $tagihan->net_amount = $newNetAmount;
            $tagihan->status = $this->statusResolver->resolve((float) $tagihan->paid_amount, $newNetAmount, $tagihan->status);
            $tagihan->perlu_ditinjau_ulang = false;
            $tagihan->alasan_perlu_ditinjau = null;
            $tagihan->save();

            if ($netAmountBerubah) {
                // dispatch TagihanDirevisiNotification, lihat §5.6
            }
        });
    }
}
```

### 5.2 Guard wajib sebelum auto-apply

Kalau salah satu gagal, nilai TIDAK diubah, tagihan masuk alur "perlu ditinjau" (§5.3):

1. **`net_amount` baru ≥ `paid_amount`** — cegah overpayment diam-diam. Kalau siswa sudah bayar Rp500rb dan hasil recalc bilang tagihan cuma Rp450rb, sistem TIDAK memaksa perubahan itu — kelebihan bayar butuh keputusan manusia (refund? kredit wallet? biarkan sebagai kredit periode depan?).
2. **`$tagihan->skemaCicilan()->doesntExist()`** — tagihan bercicilan di luar scope otomatisasi ini. **Alasan konkret** (dari audit `PembayaranService::buatSkemaCicilan()`): `Cicilan.nominal` adalah kolom snapshot yang dihitung SEKALI dari `total_tagihan` saat skema dibuat, dan alur pembayaran cicilan (`PembayaranService::tandaiLunas()`) sama sekali tidak membaca `net_amount`/`total_tagihan` saat memproses pembayaran — kalau `net_amount` di-recalc setelah skema dibuat, sistem cicilan tidak akan pernah tahu, termin-termin tetap ditagih nominal LAMA selamanya secara diam-diam. Rekonsiliasi tagihan bercicilan memakai `simpanNominalManual()` yang **sudah ada** (guard: tidak bisa ubah termin yang sudah lunas, sum harus pas) — dipicu manual oleh admin, bukan otomatis.
3. **Status bukan `lunas`/`dibatalkan`** — transaksi yang sudah selesai/dibatalkan tidak pernah diutak-atik, sesuai praktik akuntansi standar.

### 5.3 Siklus hidup flag "perlu ditinjau"

**DDL**: `ALTER TABLE tagihan ADD COLUMN perlu_ditinjau_ulang BOOLEAN NOT NULL DEFAULT FALSE, ADD COLUMN alasan_perlu_ditinjau TEXT NULL;`

- Guard gagal → `perlu_ditinjau_ulang = true`, `alasan_perlu_ditinjau` **ditimpa** dengan kondisi TERBARU (bukan ditumpuk/append).
- Tagihan yang sudah di-flag **tetap di-re-evaluasi** setiap trigger baru datang (§5.4) — TIDAK di-skip. Kalau situasi berubah dan sekarang lolos guard, auto-apply hasil recalc DAN auto-clear flag (`perlu_ditinjau_ulang = false`, `alasan_perlu_ditinjau = null`) dalam operasi yang sama (lihat kode §5.1 — clear terjadi otomatis di jalur sukses).
- **`SelesaikanTinjauanTagihanAction`** — aksi TERPISAH, HANYA dipicu manual oleh admin lewat satu tombol di halaman "Tagihan Perlu Ditinjau" (§5.7):
  ```php
  class SelesaikanTinjauanTagihanAction
  {
      public function execute(Tagihan $tagihan): void
      {
          $tagihan->update(['perlu_ditinjau_ulang' => false, 'alasan_perlu_ditinjau' => null]);
      }
  }
  ```
  Tidak ada jalur otomatis lain yang meng-clear flag ini selain re-evaluasi yang lolos guard.

### 5.4 Empat sumber trigger

Semua panggil `RecalculateTagihanNominalAction` yang sama:

| # | Trigger | Skala | Mekanisme |
|---|---|---|---|
| 1 | `SiswaKeringanan` dibuat/diubah/dicabut | 1 siswa → tagihan siswa itu lintas Jenis Tagihan | **Sinkron** |
| 2 | `JenisTagihanKeringanan` rule berubah nominal ATAU **dihapus** | Bisa ratusan siswa | **Queued job, 1 job per tagihan** |
| 3 | Tarif grup berubah nominal ATAU **dihapus** | Bisa ratusan siswa | **Queued job, 1 job per tagihan** |
| 4 | **Tarif grup di-reorder (priority berubah tanpa nominal berubah)** | Bisa ratusan siswa | **Queued job, 1 job per tagihan** |

**Trigger #1 — query eksplisit, WAJIB `tagihable_type`+`tagihable_id`, BUKAN `person_id`**:
```php
Tagihan::where('tagihable_type', Siswa::class)
    ->where('tagihable_id', $siswa->id)
    ->whereNotIn('status', ['lunas', 'dibatalkan'])
    ->pluck('id');
```
`tagihan.person_id` (dari paket person_id/keringanan sebelumnya) sengaja TIDAK dipakai di sini, meski secara semantik "semua tagihan orang ini" kedengarannya cocok — `person_id` menyatukan ledger LINTAS `tagihable_type` (Pendaftaran DAN Siswa), sehingga query berbasis `person_id` akan ikut menarik tagihan PPDB lama (`tagihable_type = Pendaftaran::class`) ke dalam scope recalc, padahal Sasaran/Tarif/Keringanan sama sekali tidak berlaku untuk jalur PPDB. Guard defensif di `RecalculateTagihanNominalAction::execute()` (§5.1) menangkap kesalahan ini kalau terjadi, tapi query pemanggil di sini WAJIB sudah benar dari awal, bukan mengandalkan guard sebagai satu-satunya lapis pertahanan.

**Trigger #4 (baru)**: mengubah urutan prioritas TANPA mengubah nominal tetap bisa mengubah hasil `resolveNominal()` untuk siswa yang match LEBIH DARI SATU grup Tarif sekaligus — grup yang tadinya kalah prioritas bisa jadi menang setelah reorder. Diberi perlakuan identik dengan trigger #3 (queued, 1 job per tagihan).

**Titik pemicu trigger #4 — endpoint terpisah, BUKAN lewat submit form penuh**: kontrol naik/turun prioritas di UI (§4.2) adalah AJAX ringan langsung ke endpoint baru `PATCH admin/jenis-tagihan/{jenisTagihan}/tarif-grup/reorder` (menerima daftar id grup dalam urutan baru), diproses oleh `ReorderTarifGrupAction` — SAMA SEKALI TIDAK lewat `SyncJenisTagihanBillingConfigAction`/submit form penuh. Ini penting karena §5.5's mekanisme diff-aware HANYA berjalan saat form Jenis Tagihan disubmit penuh — aksi reorder cepat ini TIDAK PERNAH melewati jalur itu, jadi `ReorderTarifGrupAction` WAJIB dispatch trigger recalc **secara langsung di dalam aksinya sendiri**, segera setelah kolom `priority` berhasil diperbarui:
```php
class ReorderTarifGrupAction
{
    public function execute(JenisTagihan $jenisTagihan, array $urutanGrupId): void
    {
        DB::transaction(function () use ($jenisTagihan, $urutanGrupId) {
            foreach ($urutanGrupId as $index => $grupId) {
                JenisTagihanSasaranGrup::where('id', $grupId)
                    ->where('jenis_tagihan_id', $jenisTagihan->id) // guard tenant, cegah reorder grup Jenis Tagihan lain
                    ->update(['priority' => $index + 1]);
            }
        });

        $this->dispatchRecalcUntukJenisTagihan($jenisTagihan); // sama seperti trigger #3, queued 1 job per tagihan
    }
}
```

**Trigger #2 dan #3 mencakup event DELETE, bukan cuma UPDATE** — lihat §5.5 untuk cara mendeteksinya dengan benar lewat jalur submit form penuh (bukan naif hook ke event Eloquent `deleted`, dan bukan jalur yang sama dengan trigger #4 di atas).

### 5.5 Temuan kritis: `SyncJenisTagihanBillingConfigAction` delete-lalu-buat-ulang SETIAP save form

**Fakta yang mengubah desain trigger #2/#3**: `SyncJenisTagihanBillingConfigAction::execute()` (kode saat ini):
```php
$jenisTagihan->sasaranGrup()->delete();      // SEMUA grup (sasaran+tarif) dihapus
$jenisTagihan->keringananRules()->delete();  // SEMUA rule keringanan dihapus
// ...lalu dibuat ulang dari data form yang disubmit
```
Ini jalan **SETIAP KALI** form Jenis Tagihan disimpan — termasuk saat admin cuma ganti `nama` dan sama sekali tidak menyentuh Tarif/Keringanan. Kalau trigger recalc dipasang naif di event model `deleted`, SETIAP save form (relevan atau tidak) akan memicu event delete utk semua grup/rule, memicu recalc massal palsu ke semua tagihan terkait.

**Perbaikan wajib**: `SyncJenisTagihanBillingConfigAction` di-refactor supaya **membandingkan konfigurasi lama vs baru SEBELUM delete-recreate**, dan cuma menandai "ada perubahan nyata" (lalu memicu trigger #2/#3/#4) kalau memang ada perbedaan substantif:

```php
class SyncJenisTagihanBillingConfigAction
{
    public function execute(JenisTagihan $jenisTagihan, ?array $billing): SyncResult
    {
        $tarifLama = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->with('kriteria')->orderBy('priority')->get();
        $keringananLama = $jenisTagihan->keringananRules()->get();

        // ...delete-recreate seperti sekarang...

        $tarifBerubah = $this->tarifBerbeda($tarifLama, $billing['tarif'] ?? []);
        $keringananBerubah = $this->keringananBerbeda($keringananLama, $billing['keringanan'] ?? []);

        return new SyncResult(tarifBerubah: $tarifBerubah, keringananBerubah: $keringananBerubah);
    }
}
```
Controller (`JenisTagihanController::update()`) memeriksa `SyncResult` setelah sync selesai, dan HANYA dispatch trigger #2/#3/#4 kalau `tarifBerubah`/`keringananBerubah` benar `true`. Ini tetap memenuhi maksud "penghapusan rule/grup memicu recalc" (karena `tarifBerbeda()`/`keringananBerbeda()` akan mendeteksi baris yang hilang di data baru dibanding data lama sebagai perubahan) TANPA efek samping recalc palsu di setiap save form yang tidak relevan.

### 5.6 `TagihanStatusResolver` — satu sumber kebenaran

```php
class TagihanStatusResolver
{
    public function resolve(float $paidAmount, float $netAmount, string $currentStatus): string
    {
        if ($currentStatus === 'dibatalkan') {
            return $currentStatus;
        }
        if ($paidAmount >= $netAmount) {
            return 'lunas';
        }
        return $paidAmount > 0 ? 'sebagian' : 'belum_bayar';
    }
}
```
Direplikasi EXACT dari logic yang sudah ada di `PaymentAllocationService::allocate()` (baris 48-54). **`PaymentAllocationService::allocate()` di-refactor untuk pakai service ini juga** (constructor-inject `TagihanStatusResolver`, ganti blok `if/elseif` manual dengan panggilan `$this->statusResolver->resolve(...)`) — supaya tidak ada 2 salinan logic transisi status yang bisa diam-diam menyimpang di masa depan.

`RecalculateTagihanNominalAction` WAJIB pakai `Tagihan::lockForUpdate()->find($id)` di awal transaksi (persis pola `PaymentAllocationService::allocate()` baris 39) — mencegah race condition kalau ada pembayaran diproses BERSAMAAN dengan recalc terhadap tagihan yang sama.

### 5.7 Notifikasi, audit trail, dan badge counter

**Notifikasi** — `TagihanDirevisiNotification` (`App\Notifications\Finance`), pola PERSIS `TagihanDiterbitkanNotification`:
```php
class TagihanDirevisiNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan, public float $netAmountLama) {}
    public function isUrgent(): bool { return false; }
    public function via(object $notifiable): array { return $this->baseChannels(); }
    // toDatabase()/toMail()/toWhatsApp() ikuti pola TagihanDiterbitkanNotification,
    // sebutkan net_amount lama vs baru secara eksplisit di pesan.
}
```
Dikirim via `NotificationDispatcher::send()` yang sama, **HANYA kalau `net_amount` benar-benar berubah** dari sebelumnya (bukan setiap kali recalc dijalankan meski hasilnya sama persis).

**Audit trail** — reuse `Tagihan::getActivitylogOptions()`'s `logOnly([...])` yang SUDAH ADA (`Tagihan.php:90-96`, sekarang cuma `['status', 'total_tagihan']`), tambahkan: `'net_amount', 'discount_amount', 'discount_type', 'perlu_ditinjau_ulang', 'alasan_perlu_ditinjau'`. Tidak ada mekanisme audit baru yang dibuat.

**Badge counter** — indikator visual jumlah tagihan `perlu_ditinjau_ulang = true`, mengikuti pola badge unread-notification yang SUDAH ADA di `resources/views/layouts/topbar.blade.php` (baris 11, 91 — `$unreadCount` dihitung server-side, ditampilkan sebagai lingkaran merah kecil dengan angka, capped "9+"). Ditempatkan di link sidebar/navigasi menuju halaman "Tagihan Perlu Ditinjau" untuk role bendahara/admin keuangan. **Tidak ada notifikasi WA/email baru untuk ini** — cukup indikator visual yang konsisten terlihat setiap kali admin membuka portal, supaya antrean tinjau tidak diam-diam menumpuk tanpa disadari staf.

## 6. Test Requirements

- §3: validasi kelas lintas-lembaga ditolak; field "lembaga" tidak lagi muncul di opsi kriteria; test regresi memastikan kriteria non-kelas (jenis_kelamin, status_siswa) tidak ikut kena validasi exists yang salah sasaran.
- §4.2: migration backfill `priority` — assert urutan menang SAMA PERSIS sebelum/sesudah migrasi untuk kasus multi-grup existing; test reorder via UI mengubah hasil `resolveNominal()` untuk siswa yang match multiple grup.
- §4.4: `resolveDiscount()` dengan kombinasi kategori combinable+non-combinable — assert hasil penjumlahan benar, assert clamp ke `nominal` saat total diskon melebihi nominal, assert perilaku TIDAK berubah untuk kategori `bisa_digabung=false` (regresi terhadap perilaku lama).
- §5.1-5.2: test untuk SETIAP guard gagal (overpayment, ada cicilan, status lunas/dibatalkan) — assert nilai TIDAK berubah, `perlu_ditinjau_ulang=true` dengan alasan yang benar. Test jalur sukses — assert `total_tagihan`/`discount_amount`/`discount_type`/`net_amount`/`status` semua ter-update konsisten dalam satu transaksi.
- §5.3: test flag di-re-evaluasi (bukan di-skip) pada trigger kedua setelah pertama gagal guard — assert alasan TERTIMPA bukan ditumpuk; test auto-clear saat kondisi membaik; test `SelesaikanTinjauanTagihanAction` cuma clear flag tanpa mengubah nominal apapun.
- §5.4: test 4 sumber trigger masing-masing memicu recalc yang benar dengan scope yang benar (1 siswa vs semua siswa Jenis Tagihan); test trigger #4 (reorder tanpa ubah nominal) benar-benar mengubah hasil resolve untuk siswa multi-match; test trigger #1's query cuma menghasilkan id tagihan dengan `tagihable_type = Siswa::class` meski siswa itu juga punya tagihan PPDB lama (`tagihable_type = Pendaftaran::class`) dengan `person_id` yang sama — tagihan PPDB itu TIDAK BOLEH ikut ke daftar id yang di-dispatch; test `ReorderTarifGrupAction` dispatch recalc langsung tanpa melalui `SyncJenisTagihanBillingConfigAction` sama sekali.
- **§5.1 (guard tagihable_type)**: test `RecalculateTagihanNominalAction::execute()` dipanggil langsung dengan id tagihan berkategori PPDB (`tagihable_type = Pendaftaran::class`) — assert no-op (tidak ada exception, tidak ada kolom yang berubah), bukan error. Ini test defense-in-depth yang harus tetap lolos SEKALIPUN §5.4's query pemanggil entah bagaimana salah kirim id yang tidak seharusnya.
- §5.5: **test paling kritis** — simpan form Jenis Tagihan TANPA mengubah Tarif/Keringanan sama sekali, assert TIDAK ADA job recalc yang di-dispatch (mencegah regresi ke pola delete-recreate naif). Test terpisah: hapus 1 rule Keringanan lewat form, assert job recalc DI-dispatch untuk tagihan yang terdampak rule itu.
- §5.6: test `PaymentAllocationService::allocate()` dan `RecalculateTagihanNominalAction` menghasilkan status yang SAMA untuk kombinasi paid_amount/net_amount yang sama (bukti keduanya benar-benar pakai `TagihanStatusResolver` yang sama, tidak divergen). Test `lockForUpdate()` mencegah race — pembayaran dan recalc terhadap tagihan yang sama diproses berurutan (serial), tidak keduanya baca nilai stale.
- §5.7: test notifikasi cuma terkirim kalau `net_amount` benar-benar beda; test Activitylog mencatat perubahan `net_amount`/`discount_amount`/`perlu_ditinjau_ulang`; test badge counter menghitung jumlah tagihan `perlu_ditinjau_ulang=true` dengan benar dan ter-scope ke tenant/lembaga yang benar.

## 7. Urutan Implementasi (garis besar, detail penuh di plan)

1. **§3** — perbaikan teknis murni (independen, tidak bergantung apapun).
2. **§5.6** — `TagihanStatusResolver` + refactor `PaymentAllocationService` (fondasi, harus ada duluan sebelum §5.1 dipakai).
3. **§5.1-5.3** — migration kolom `perlu_ditinjau_ulang`/`alasan_perlu_ditinjau`, `RecalculateTagihanNominalAction`, `SelesaikanTinjauanTagihanAction`.
4. **§4.2** — migration+backfill `priority`, ubah `resolveNominal()` pakai `orderBy('priority')`, UI kontrol urutan.
5. **§4.4** — migration `bisa_digabung`, logic penjumlahan di `resolveDiscount()`.
6. **§5.5** — refactor `SyncJenisTagihanBillingConfigAction` jadi diff-aware (WAJIB selesai sebelum §5.4 diaktifkan, supaya trigger tidak menghasilkan recalc palsu).
7. **§5.4** — wiring 4 sumber trigger ke `RecalculateTagihanNominalAction` (sinkron untuk #1, queued job untuk #2/#3/#4).
8. **§5.7** — notifikasi, audit trail, badge counter, halaman "Tagihan Perlu Ditinjau".
9. **§4.1, §4.3** — live preview + widget assignment keringanan di form (murni UI, paling aman dikerjakan terakhir karena tidak berisiko finansial).
10. Full test suite.

## 8. Risiko

- **Skala queued job untuk Jenis Tagihan dengan ribuan siswa**: 1 job per tagihan berarti Jenis Tagihan "semua siswa" di lembaga besar bisa dispatch ribuan job sekaligus saat 1 rule Tarif/Keringanan diubah. Perlu dipantau beban queue worker saat implementasi — kalau jadi masalah nyata, mitigasi ada di masa depan (rate-limiting dispatch, bukan mengubah granularitas job).
- **Rekonsiliasi cicilan tetap manual selamanya (kalau tidak ada paket lanjutan)**: tagihan bercicilan yang terdampak perubahan Keringanan/Tarif akan terus menumpuk di antrean "perlu ditinjau" tanpa jalan otomatis. Kalau volume tagihan bercicilan tinggi, ini bisa jadi beban kerja manual admin yang signifikan — worth dipantau setelah rilis, mungkin perlu paket rekonsiliasi cicilan terpisah nanti.
