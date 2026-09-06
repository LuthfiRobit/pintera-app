# Spec: Notifikasi Presensi Akademik (Opsi A2)

**Tanggal**: 2026-09-06
**Branch**: `akademik-v2`

---

## 1. Latar Belakang

Item roadmap lama "Notifikasi presensi & penjemputan (tap-in/tap-out)" ternyata mengasumsikan infrastruktur fisik (scan gerbang) yang **tidak ada sama sekali**. Yang benar-benar ada: `Presensi` (status hadir/izin/sakit/alpa/terlambat) dicatat GURU per sesi pelajaran lewat Jurnal KBM (`RecordJurnalDanPresensiAction`) — bukan real-time saat siswa masuk gerbang.

Setelah didiskusikan, item lama dipecah jadi 2 fitur konseptual terpisah:
- **Notifikasi Presensi Akademik (A2 — spec ini)**: notifikasi ke orang tua berbasis data Jurnal KBM yang sudah ada. Effort kecil, dikerjakan sekarang.
- **Presensi Fisik/Check-in-Check-out**: proyek terpisah jauh lebih besar (kartu pelajar QR, scan gerbang, reuse pola `EmployeeQrCode`/`ScanQrAttendanceAction` milik SDM). **TIDAK dikerjakan di spec ini, tidak disinggung sama sekali di kode.**

Ada juga rencana lanjutan **Opsi A3** (siswa scan barcode sendiri, guru cuma validasi yang tidak scan) — model data-nya SAMA PERSIS dengan A2 (`Presensi` per sesi), cuma beda cara input. A3 tidak dikerjakan sekarang, tapi desain A2 harus tidak menutup jalan untuk A3 nanti (lihat §4).

## 2. Keputusan Desain

1. **Status yang memicu notifikasi**: `izin`, `sakit`, `alpa`, **dan `terlambat`** (keempat status non-hadir, dikonfirmasi eksplisit oleh user — awalnya sempat diusulkan `terlambat` dikecualikan, tapi user memilih tetap diikutkan).
2. **Penerima**: **kontak utama saja** (`siswa_orang_tua.is_kontak_utama = true`) — bukan semua orang tua yang terhubung. Konsisten dengan pola yang sudah dipakai `RaporPdfDataBuilder` untuk `namaOrangTua`.
3. **Wajib deteksi PERUBAHAN status** — notifikasi HANYA terkirim kalau status BARU berbeda dari status LAMA (sebelum update). Guru yang re-save Jurnal KBM yang sama (mis. cuma ubah materi, presensi tidak berubah) **TIDAK BOLEH** memicu notifikasi ulang. Ini syarat keras, bukan nice-to-have — tanpa ini fitur jadi spam generator.
4. **Feature-gating platform/Yayasan**: SENGAJA TIDAK dibangun sama sekali di spec ini (keputusan YAGNI eksplisit — belum ada kebutuhan bisnis nyata untuk membedakan akses per Yayasan). Toggle level Lembaga juga TIDAK dibangun — untuk scope A2 saja tidak ada 2 mode untuk dipilih (cuma 1 cara: notifikasi otomatis atas perubahan status).
5. **Kegagalan kirim notifikasi TIDAK BOLEH menggagalkan penyimpanan Jurnal KBM** — dibungkus try/catch + `Log::error()`, pola persis `TagihanBillingGenerator::class` baris notifikasi Tagihan.
6. **Pola notification class**: extends `Illuminate\Notifications\Notification` LANGSUNG (pola `KonselorDipilihNotification`), **BUKAN** pola `FinanceNotification` (base class custom itu spesifik untuk flag `allowWa`/`allowEmail` milik Finance, tidak relevan di sini — jangan ditiru, jangan bikin base class serupa untuk Akademik kalau cuma dipakai 1 notification class).
7. **Template pesan lewat `WhatsAppTemplate` (database, admin-editable)** — BUKAN hardcode teks di kode. `WhatsAppTemplate::renderKode(string $kode, array $placeholders): ?string` mengganti `{key}` di `isi_template` dengan value placeholder; kalau `kode` belum ada row-nya di database, `renderKode()` mengembalikan `null` (notifikasi WA jadi senyap gagal, bukan error) — **WAJIB seeded** lewat `WhatsAppTemplateSeeder.php`, jangan lupa, jangan andalkan admin bikin manual dulu.
8. **Penamaan HARUS netral dari konsep "check-in/tap"** — nama class, method, dan pesan TIDAK BOLEH menyinggung "check-in", "tap", "gerbang", dsb. Ini soal presensi akademik (kehadiran di pelajaran), bukan presensi fisik.

## 3. Desain Teknis

### 3.1 File Baru

- **`app/Domains/Akademik/Services/PresensiNotificationService.php`** — Service baru (bukan Action), method tunggal:
  ```php
  public function kirimJikaPerluAtasPerubahan(Presensi $presensi, ?string $statusLama): void
  ```
  Isi: cek `$presensi->status->value !== $statusLama` dan `in_array($presensi->status->value, ['izin','sakit','alpa','terlambat'], true)`. Kalau keduanya benar: cari kontak utama (`$presensi->siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()`), kalau ada, `Notification::send($kontakUtamaUser, new PresensiPengecualianNotification($presensi))` dibungkus try/catch + `Log::error()`. Kalau kontak utama tidak ditemukan, cukup skip diam-diam (bukan error) — banyak siswa demo/data lama mungkin belum ada kontak utama-nya.

  **Kenapa Service, bukan ditulis inline di Action**: supaya A3 nanti (jalur input beda — hasil scan barcode) bisa panggil method yang SAMA tanpa duplikasi logic notifikasi. `RecordJurnalDanPresensiAction` jadi konsumen pertama, bukan satu-satunya yang boleh memanggilnya.

- **`app/Notifications/Akademik/PresensiPengecualianNotification.php`** — extends `Notification` (bukan base class custom), constructor `public function __construct(public Presensi $presensi) {}`:
  ```php
  public function via(object $notifiable): array
  {
      $channels = ['database'];
      if (filled($notifiable->routeNotificationFor('mail'))) {
          $channels[] = 'mail';
      }
      $channels[] = 'whatsapp';
      return $channels;
  }

  public function toDatabase(object $notifiable): array
  {
      return [
          'presensi_id' => $this->presensi->id,
          'message' => "Presensi {$this->presensi->siswa->nama_lengkap} tercatat {$this->presensi->status->label()} pada {$this->presensi->sesiPembelajaran->tanggal->translatedFormat('d F Y')}.",
      ];
  }

  public function toWhatsApp(object $notifiable): ?string
  {
      return WhatsAppTemplate::renderKode('presensi_pengecualian', [
          'nama_siswa' => $this->presensi->siswa->nama_lengkap,
          'status' => $this->presensi->status->label(),
          'tanggal' => $this->presensi->sesiPembelajaran->tanggal->translatedFormat('d F Y'),
          'keterangan' => $this->presensi->keterangan ?: '-',
      ]);
  }
  ```
  Cek dulu saat implementasi: apakah `Presensi` model punya relasi `sesiPembelajaran()` dan `siswa()` yang sudah eager-loadable (harusnya sudah ada, dipakai di banyak tempat sesi ini) — kalau perlu, eager-load di Service sebelum construct notification supaya tidak N+1.

### 3.2 File Dimodifikasi

- **`app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`** — inject `PresensiNotificationService` via constructor, ubah `execute()`:
  ```php
  final class RecordJurnalDanPresensiAction
  {
      public function __construct(
          private readonly PresensiNotificationService $presensiNotificationService,
      ) {}

      public function execute(SesiPembelajaran $sesi, JurnalPresensiData $data): SesiPembelajaran
      {
          $perluDicek = [];

          $sesiTerbaru = DB::transaction(function () use ($sesi, $data, &$perluDicek) {
              $sesi->update(['materi' => $data->materi]);

              $statusLamaPerSiswa = $sesi->presensi()->get()->keyBy('siswa_id')
                  ->map(fn ($p) => $p->status->value);

              foreach ($data->presensi as $siswaId => $status) {
                  $sesi->presensi()->where('siswa_id', $siswaId)->update([
                      'status' => $status,
                      'keterangan' => $data->keterangan[$siswaId] ?? null,
                  ]);

                  $perluDicek[] = ['siswa_id' => $siswaId, 'status_lama' => $statusLamaPerSiswa->get($siswaId)];
              }

              return $sesi->fresh();
          });

          // WAJIB di luar transaksi -- pengiriman notifikasi (network I/O ke WhatsApp
          // Gateway) tidak boleh menahan transaksi DB terbuka.
          foreach ($perluDicek as $item) {
              $presensiTerbaru = $sesiTerbaru->presensi()->where('siswa_id', $item['siswa_id'])->first();
              if ($presensiTerbaru !== null) {
                  $this->presensiNotificationService->kirimJikaPerluAtasPerubahan($presensiTerbaru, $item['status_lama']);
              }
          }

          return $sesiTerbaru;
      }
  }
  ```
  **Perhatikan**: `$statusLamaPerSiswa` diambil SEBELUM loop update (baris presensi lama, sebelum di-update) — urutan ini kritis, jangan sampai kebalik (kalau diambil setelah update, status lama akan selalu sama dengan status baru dan notifikasi tidak akan pernah terkirim).

- **`database/seeders/WhatsAppTemplateSeeder.php`** — tambah entri baru mengikuti pola existing:
  ```php
  WhatsAppTemplate::firstOrCreate(['kode' => 'presensi_pengecualian'], [
      'isi_template' => 'Yth. Orang Tua {nama_siswa}, presensi tercatat {status} pada {tanggal}. Keterangan: {keterangan}.',
      'deskripsi' => 'Dikirim ke kontak utama orang tua saat presensi siswa dicatat guru sebagai Izin/Sakit/Alpa/Terlambat. Placeholder tersedia: {nama_siswa}, {status}, {tanggal}, {keterangan}.',
  ]);
  ```

## 4. Desain untuk A3 (tanpa membangunnya sekarang)

`PresensiNotificationService::kirimJikaPerluAtasPerubahan()` menerima `Presensi $presensi` (state SETELAH update) dan `?string $statusLama` (state SEBELUM) sebagai parameter murni — TIDAK mengasumsikan sumber perubahan dari form guru manapun. Ketika A3 dibangun nanti (siswa scan barcode → sebagian besar siswa otomatis `hadir`, guru cuma koreksi sisanya), jalur baru itu tinggal memanggil Service yang SAMA dengan pasangan (status lama, status baru) miliknya sendiri — tidak perlu menyalin ulang logic deteksi-perubahan atau logic kirim-notifikasi.

## 5. Di Luar Cakupan

- Opsi A3 (scan barcode) — sepenuhnya di luar cakupan, cuma dipertimbangkan di §4 sebagai constraint desain.
- Opsi B (check-in/check-out fisik, Kartu Pelajar Digital QR) — sepenuhnya di luar cakupan, tidak boleh disinggung di kode/nama apa pun.
- Feature-gating platform/Yayasan dan toggle level Lembaga — sepenuhnya di luar cakupan (§2.4).
- Notifikasi untuk status `hadir` — tidak pernah dikirim, sesuai desain (§2.1 cuma status non-hadir).

## 6. Test yang Diperlukan

1. Guru submit Jurnal KBM dengan status baru `izin` untuk siswa yang sebelumnya `hadir` → notifikasi terkirim ke kontak utama, cek via `Notification::fake()` + `Notification::assertSentTo()`.
2. Guru re-submit Jurnal KBM dengan status YANG SAMA (`izin` → `izin`, tidak berubah) → notifikasi TIDAK terkirim (`Notification::assertNothingSent()` atau `assertNotSentTo()`).
3. Status berubah ke `hadir` (dari status apa pun) → notifikasi TIDAK terkirim.
4. Keempat status (`izin`, `sakit`, `alpa`, `terlambat`) masing-masing terbukti memicu notifikasi saat berubah dari `hadir`.
5. Siswa tanpa kontak utama terdaftar (atau tanpa orang tua sama sekali) → tidak error, cuma skip diam-diam.
6. Kegagalan pengiriman notifikasi (mis. `WhatsAppTemplate` belum ada row `presensi_pengecualian` di test environment, atau exception dari channel) → penyimpanan Jurnal KBM tetap berhasil (assert `Presensi.status` ter-update di DB meski notifikasi gagal).
7. Isi `toWhatsApp()`/`toDatabase()` mengandung nama siswa, status label yang benar, tanggal, dan keterangan.
