# Handoff Log: Fail-Closed Fix di ApproverResolverService (Lintas Rapor/Pengadaan/SDM)

- **Tanggal**: 2026-09-10
- **Branch**: `rbac-v2`
- **Ditemukan saat**: audit alur Izin/Cuti SDM (pengajuan → persetujuan), setelah audit menyeluruh Rapor selesai.

## 1. Konteks Penemuan

Saat mengaudit alur Izin/Cuti SDM, sempat dicurigai ada "kesenjangan defense-in-depth": `ProsesApprovalIzinCutiAction` (SDM) tidak punya guard kepemilikan lokal seperti yang dikembalikan di 2 Action Rapor (`VerifyPengajuanRaporAction`/`ApprovePengajuanRaporAction`) sebelumnya di sesi ini. Investigasi lebih dalam menemukan akar masalah yang lebih fundamental: guard lokal Rapor itu sebenarnya hanya menambal SATU domain dari gejala bug yang levelnya ada di **shared Workflow engine sendiri**.

## 2. Akar Masalah

`ApprovalRequest` (model inti Workflow engine) **tidak punya TenantScope** — sengaja, karena Workflow generik dipakai lintas domain, definisi alurnya bersifat global. Konsekuensinya: `ApproverResolverService::checkRoleApprover()` satu-satunya cara tahu "pengajuan ini milik lembaga mana" adalah bertanya ke relasi `approvable`/`requester` (yang MODEL TARGET-nya, seperti `PengajuanRapor`/`PengajuanIzinCuti`/model Pengadaan, BARU kena TenantScope lagi saat di-resolve).

Kode SEBELUM perbaikan:
```php
$targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;

if ($targetLembagaId !== null) {
    // ...cek kecocokan lembaga...
}

return true; // <- kalau $targetLembagaId TIDAK PERNAH ditemukan, langsung LOLOS
```

Kalau `approvable`/`requester` gagal di-resolve (null) -- karena alasan apa pun, termasuk TenantScope memfilternya -- kode ini menganggap "tidak tahu targetnya" sebagai **"izinkan saja"** (fail-open), bukan "tidak tahu = tolak" (fail-closed). Ini bukan bug spesifik satu domain -- fungsi ini dipakai SAMA PERSIS oleh Rapor, Pengadaan, dan SDM (5 step `scope_level: 'lembaga'` total di `WorkflowDefinitionSeeder`).

**Kenapa cuma Rapor yang "kebetulan aman"**: guard lokal redundan di `VerifyPengajuanRaporAction`/`ApprovePengajuanRaporAction` (yang sempat mau dihapus di Task 2 perbaikan Persetujuan Rapor, lalu dikembalikan setelah ditemukan `RaporApprovalTenantScopeTest` yang mendokumentasikannya) kebetulan jadi lapis kedua yang menahan skenario ini untuk Rapor. Pengadaan dan SDM TIDAK punya lapis kedua serupa -- kalau fail-open di engine ke-trigger untuk mereka, tidak ada yang menahan.

**Status eksploitasi saat ini**: TIDAK ada jalur HTTP nyata yang bisa memicu ini hari ini -- satu-satunya caller `ProsesApprovalIzinCutiAction`/Action serupa di Pengadaan selalu lewat route-model-binding yang sudah TenantScope-protected. Ini murni kerapuhan arsitektur (celah yang akan terbuka kalau suatu saat ada command/job/endpoint baru yang memanggil Action langsung), bukan bug aktif yang sudah tereksploitasi.

## 3. Perbaikan

`app/Domains/Workflow/Services/ApproverResolverService.php::checkRoleApprover()` -- fail-closed:

```php
if ($step->scope_level === 'lembaga') {
    $targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;
    $effectiveLembagaId = $this->resolveEffectiveLembagaId($user);

    if ($targetLembagaId === null || $effectiveLembagaId === null || (int) $targetLembagaId !== (int) $effectiveLembagaId) {
        return false;
    }
}
```

**Kenapa aman diterapkan tanpa merusak alur sah manapun**: diverifikasi bahwa untuk SEMUA 5 step `scope_level: 'lembaga'` yang ada, `approvable`-nya SELALU model yang punya `lembaga_id` sah begitu record dibuat dengan benar. Tidak ada skenario bisnis sah di mana step ber-scope lembaga punya target TANPA lembaga_id -- `null` di titik itu selalu berarti sesuatu yang salah, bukan kasus normal.

## 4. Pengujian

- Test baru: `it('fails closed (denies) when the approvable/requester target cannot be resolved at all, even for an otherwise-valid lembaga-scope approver')` di `tests/Feature/Workflow/ApproverResolverServiceTest.php` -- mengonstruksi `ApprovalRequest` dengan `approvable_id` menunjuk record yang tidak pernah ada, membuktikan sekarang DITOLAK (sebelumnya akan LOLOS).
- Regresi 198 test lintas Rapor + Pengadaan + SDM (termasuk seluruh alur approval ketiganya) -- semua tetap lolos, membuktikan perubahan ini TIDAK melonggarkan atau mematahkan alur sah manapun.
- Full test suite proyek dijalankan sekali di akhir (mengingat file ini sentral, dipakai 3 domain).

## 5. Hal yang Perlu Direview

- Guard lokal di `VerifyPengajuanRaporAction`/`ApprovePengajuanRaporAction` (Rapor) TETAP DIPERTAHANKAN -- sekarang murni jadi lapis defense-in-depth kedua yang genuinely redundan (tidak lagi satu-satunya andalan), bukan dihapus lagi.
- Pengadaan dan SDM TIDAK ditambahkan guard lokal serupa -- perbaikan di root sudah cukup menutup celah untuk ketiganya, konsisten dengan filosofi "perbaiki akar sekali, jangan tambal per-domain" yang dipakai sepanjang sesi ini.
- Belum ada command/job internal yang memanggil Action approval manapun secara langsung (di luar route-model-binding) di codebase ini saat ini -- kalau di masa depan ada fitur seperti itu (mis. auto-approve batch), sudah otomatis terlindungi oleh perbaikan ini.
