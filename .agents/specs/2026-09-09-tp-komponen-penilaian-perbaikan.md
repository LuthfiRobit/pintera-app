# Audit & Perbaikan Menu TP (Komponen Penilaian)

**Tanggal**: 2026-09-09
**Branch**: `akademik-v2`
**Status**: Draft — menunggu review user sebelum plan+kickoff

## Ringkasan

Audit menu TP (Tujuan Pembelajaran, dalam modul Komponen Penilaian) menemukan **1 bug kritis** di halaman Edit sisi Admin, dan 3 item wording/konsistensi kode minor. Backend inti (validasi bobot 100% dengan `lockForUpdate()`, proteksi hapus/ubah data yang sudah dipakai asesmen/nilai, cross-tenant check) sudah SOLID — tidak diubah di spec ini kecuali Item A.

**Item A ditemukan lewat 2 kali verifikasi empiris** (test HTTP sementara, dibuat lalu dihapus setelah verifikasi) dan 1 kali audit ulang atas permintaan eksplisit user ("audit ulang karena ini sangat sensitif"). Kesimpulan AWAL saya (view kehilangan field) TERNYATA SALAH setelah dibandingkan dengan jalur Guru — kesimpulan FINAL (setelah audit ulang): **desain "Subjek/Semester dikunci permanen setelah TP dibuat" SUDAH BENAR dan disengaja di sisi Guru (lengkap dengan wording penjelasan), tapi validasi Admin ketinggalan diselaraskan** — bukan "field yang hilang harus dikembalikan", tapi "validasi yang harus dilonggarkan supaya sinkron dengan desain yang sudah benar di sisi lain". User sudah konfirmasi **Opsi A**: ikuti pola Guru, kunci Subjek/Semester permanen di kedua sisi, dengan syarat alur bisnisnya harus jelas (workflow "dikunci setelah dibuat" harus konsisten dan dijelaskan eksplisit ke user).

---

## Item A — 🔴 KRITIS: Selaraskan Validasi Edit Admin dengan Desain "Subjek/Semester Terkunci Permanen"

### Masalah

**Dibuktikan empiris (2x)**: submit form "Edit Komponen Penilaian (TP)" sisi Admin, dengan payload PERSIS seperti yang benar-benar dikirim `edit.blade.php` (TANPA `subjek_type`/`subjek_id`/`semester_id`, karena field itu memang tidak ada di form — read-only), **SELALU gagal validasi** dengan pesan "subjek_type/subjek_id/semester_id wajib diisi" — untuk SETIAP TP yang belum pernah dipakai di asesmen/nilai (`!$dipakai`, kondisi paling umum untuk TP baru). Tidak ada perubahan (kode/deskripsi/bobot/kktp/kktp_minimal/tipe penilaian) yang bisa disimpan sama sekali dalam kondisi ini.

**Root cause SEBENARNYA** (setelah audit ulang, BUKAN "field hilang dari view"): `UpdateKomponenPenilaianRequest.php` (`rules()`) masih mewajibkan `subjek_type`/`subjek_id`/`semester_id` saat `!$dipakai` — padahal `edit.blade.php` (Admin) SUDAH SEJAK LAMA sengaja menampilkan Subjek/Semester sebagai read-only (`<p>`), TANPA `<input>`/`<select>` apa pun untuk field itu. Perbandingan dengan jalur **Guru** (`Guru\KomponenPenilaianController`, `UpdateKomponenPenilaianSendiriRequest`, `portals/guru/akademik/komponen-penilaian/edit.blade.php`) membuktikan desain "read-only, terkunci permanen" ini MEMANG SUDAH BENAR DAN DISENGAJA di sisi Guru:
- View Guru punya teks penjelasan eksplisit: *"Subjek Penilaian dan Semester tidak bisa diubah di sini — hapus lalu buat TP baru kalau butuh Subjek/Semester yang berbeda."*
- `UpdateKomponenPenilaianSendiriRequest::rules()` TIDAK PERNAH mewajibkan `subjek_type`/`subjek_id`/`semester_id` — desainnya sudah konsisten dengan view-nya.

`UpdateKomponenPenilaianRequest` (Admin) ketinggalan diselaraskan — inilah SATU-SATUNYA sumber bug ini, bukan view yang "kehilangan" apa pun.

**Efek samping tersembunyi**: field "Tipe Penilaian" (assessment_type) yang MEMANG masih bisa diedit di UI Admin (dropdown aktif saat `!$dipakai`) JUGA ikut tidak pernah tersimpan — karena di `UpdateKomponenPenilaianAction`, logic pembaruan `assessment_type` ada DI DALAM blok kondisi yang mensyaratkan `subjek_type` dkk juga terisi (yang tidak pernah terjadi). Perbaikan Item A ini WAJIB melepaskan `assessment_type` dari blok itu supaya bisa diedit independen, konsisten dengan apa yang ditampilkan UI.

**Bukti tambahan (kode mati)**: `resources/js/komponen-penilaian-edit.js` punya fungsi `initMataPelajaranSelect()`/`initSemesterSelect()` yang TIDAK PERNAH dipanggil di manapun di `edit.blade.php` Admin — sisa dari desain lama yang sudah tidak dipakai, harus dibersihkan supaya kode tidak menyesatkan pembaca berikutnya (relevan dengan concern "alur bisnisnya harus jelas").

### Keputusan Bisnis yang Ditegakkan (WAJIB jelas ke user & dicerminkan konsisten di 2 sisi Admin+Guru)

**Subjek Penilaian dan Semester SEBUAH TP TIDAK BISA DIUBAH SETELAH DIBUAT — SELAMANYA, baik TP itu sudah dipakai di asesmen/nilai ATAUPUN BELUM.** Satu-satunya cara mengganti Subjek/Semester adalah hapus TP itu (kalau belum dipakai — TP yang sudah dipakai memang sudah tidak bisa dihapus juga, lihat `DeleteKomponenPenilaianAction`) lalu buat TP baru. Field yang MASIH BISA diedit kapan saja (bahkan setelah dipakai): `kode`, `deskripsi`, `bobot` (dengan guard 100%), `kktp`, `kktp_minimal`. Field `assessment_type` (Tipe Penilaian) HANYA bisa diedit SELAMA `!$dipakai` (kalau sudah dipakai, tipe penilaian numerik/naratif/predikat juga ikut terkunci — konsisten dengan alasan yang sama: tidak boleh mengubah "bentuk" data setelah ada nilai riil yang bergantung padanya).

### Perbaikan

**1. `app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php`** — ganti seluruh isi `rules()`:

```php
public function rules(): array
{
    $komponen = $this->route('komponenPenilaian');
    $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

    $rules = [
        'kode' => ['nullable', 'string', 'max:50'],
        'deskripsi' => ['required', 'string'],
        'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
        'kktp' => ['nullable', 'string'],
        'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],
    ];

    if (! $dipakai) {
        $rules['subjek_type'] = ['required', Rule::in(['mata_pelajaran', 'elemen_cp'])];
        $rules['subjek_id'] = ['required', 'integer', function ($attribute, $value, $fail) {
            $exists = match ($this->input('subjek_type')) {
                'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->where('id', $value)->exists(),
                'elemen_cp' => ElemenCp::where('id', $value)->exists(),
                default => false,
            };
            if (! $exists) {
                $fail('Subjek penilaian yang dipilih tidak valid.');
            }
        }];
        $rules['semester_id'] = ['required', 'integer'];
        $rules['assessment_type'] = ['nullable', Rule::enum(AssessmentType::class)];
    }

    return $rules;
}
```

menjadi (SAMA PERSIS strukturnya dengan `UpdateKomponenPenilaianSendiriRequest::rules()` yang sudah benar — HAPUS baris `subjek_type`/`subjek_id`/`semester_id`, PERTAHANKAN `assessment_type`):

```php
public function rules(): array
{
    $komponen = $this->route('komponenPenilaian');
    $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

    $rules = [
        'kode' => ['nullable', 'string', 'max:50'],
        'deskripsi' => ['required', 'string'],
        'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
        'kktp' => ['nullable', 'string'],
        'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],
    ];

    if (! $dipakai) {
        $rules['assessment_type'] = ['nullable', Rule::enum(AssessmentType::class)];
    }

    return $rules;
}
```

Import `Rule::in`, `MataPelajaran`, `ElemenCp` yang sudah tidak dipakai lagi di file ini BOLEH dihapus dari `use` statement (`MataPelajaran`, `ElemenCp`) — `Rule` TETAP dipakai (`Rule::enum`).

**2. `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`** — hapus blok reassignment subjek/semester yang sudah permanen tidak terjangkau, lepaskan `assessment_type` jadi guard independen. Ganti:

```php
public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
{
    return DB::transaction(function () use ($komponen, $data) {
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        if (! $dipakai && $data->subjekType !== null && $data->subjekId !== null && $data->semesterId !== null) {
            $komponen->subjek_type = $data->subjekType;
            $komponen->subjek_id = $data->subjekId;
            $komponen->semester_id = $data->semesterId;
            $komponen->lembaga_id = Semester::findOrFail($data->semesterId)->lembaga_id;
            if ($data->assessmentType !== null) {
                $komponen->assessment_type = $data->assessmentType;
            }
        }

        Semester::where('id', $komponen->semester_id)->lockForUpdate()->first();

        $newBobot = $data->bobot ?? $komponen->bobot;
        $existingSum = KomponenPenilaian::where('subjek_type', $komponen->subjek_type)
            ->where('subjek_id', $komponen->subjek_id)
            ->where('semester_id', $komponen->semester_id)
            ->where('id', '!=', $komponen->id)
            ->sum('bobot');

        if (($existingSum + $newBobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk subjek ini adalah {$remaining}%.",
            ]);
        }

        $komponen->kode = $data->kode;
        $komponen->deskripsi = $data->deskripsi;
        $komponen->bobot = $newBobot;
        $komponen->kktp = $data->kktp;
        $komponen->kktp_minimal = $data->kktpMinimal;
        $komponen->save();

        return $komponen;
    });
}
```

menjadi:

```php
public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
{
    return DB::transaction(function () use ($komponen, $data) {
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        // Subjek Penilaian dan Semester TIDAK BISA diubah sejak TP dibuat --
        // baik sudah dipakai maupun belum (lihat spec Item A, keputusan bisnis
        // "hapus lalu buat baru"). Blok reassignment lama SENGAJA dihapus, bukan
        // dilewati -- subjek_type/subjek_id/semester_id di form manapun (Admin
        // maupun Guru) memang tidak pernah lagi dikirim ke sini.
        if (! $dipakai && $data->assessmentType !== null) {
            $komponen->assessment_type = $data->assessmentType;
        }

        Semester::where('id', $komponen->semester_id)->lockForUpdate()->first();

        $newBobot = $data->bobot ?? $komponen->bobot;
        $existingSum = KomponenPenilaian::where('subjek_type', $komponen->subjek_type)
            ->where('subjek_id', $komponen->subjek_id)
            ->where('semester_id', $komponen->semester_id)
            ->where('id', '!=', $komponen->id)
            ->sum('bobot');

        if (($existingSum + $newBobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk subjek ini adalah {$remaining}%.",
            ]);
        }

        $komponen->kode = $data->kode;
        $komponen->deskripsi = $data->deskripsi;
        $komponen->bobot = $newBobot;
        $komponen->kktp = $data->kktp;
        $komponen->kktp_minimal = $data->kktpMinimal;
        $komponen->save();

        return $komponen;
    });
}
```

Import `use App\Models\Semester;` TETAP dipakai (baris `Semester::where('id', $komponen->semester_id)->lockForUpdate()`), JANGAN dihapus.

**3. `app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php`** — hapus 3 properti yang sudah permanen tidak terpakai (`subjekType`, `subjekId`, `semesterId`). Ganti seluruh isi file:

```php
final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?string $subjekType,
        public ?int $subjekId,
        public ?int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
        public ?string $assessmentType,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subjekType: $data['subjek_type'] ?? null,
            subjekId: isset($data['subjek_id']) ? (int) $data['subjek_id'] : null,
            semesterId: isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
            assessmentType: isset($data['assessment_type']) && $data['assessment_type'] !== ''
                ? (string) $data['assessment_type']
                : null,
        );
    }
}
```

menjadi:

```php
final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
        public ?string $assessmentType,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
            assessmentType: isset($data['assessment_type']) && $data['assessment_type'] !== ''
                ? (string) $data['assessment_type']
                : null,
        );
    }
}
```

**PENTING**: dicek langsung — TIDAK ADA pemanggilan `new UpdateKomponenPenilaianData(...)` di manapun di codebase SELAIN lewat `fromArray()` (dikonfirmasi via pencarian penuh), jadi perubahan constructor ini AMAN, tidak ada caller lain yang perlu disesuaikan.

**4. `resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php`** — tambahkan wording penjelasan yang SAMA seperti sisi Guru, setelah baris ±51 (`</div>` penutup grid Subjek Penilaian/Semester, sebelum blok "Tipe Penilaian"):

```blade
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label value="Subjek Penilaian" />
        <p class="mt-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $komponenPenilaian->subjek->nama }}</p>
    </div>

    <div>
        <x-input-label value="Semester" />
        <p class="mt-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $komponenPenilaian->semester->nama }} — {{ $komponenPenilaian->semester->tahunAjaran->nama }}</p>
    </div>
</div>
```

menjadi:

```blade
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label value="Subjek Penilaian" />
        <p class="mt-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $komponenPenilaian->subjek->nama }}</p>
    </div>

    <div>
        <x-input-label value="Semester" />
        <p class="mt-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $komponenPenilaian->semester->nama }} — {{ $komponenPenilaian->semester->tahunAjaran->nama }}</p>
    </div>
</div>
<p class="text-xs text-gray-400 -mt-3">Subjek Penilaian dan Semester tidak bisa diubah di sini — hapus lalu buat TP baru kalau butuh Subjek/Semester yang berbeda.</p>
```

(Teks dan posisi PERSIS meniru `resources/views/portals/guru/akademik/komponen-penilaian/edit.blade.php` baris 66, supaya konsisten kata-per-kata di kedua sisi.)

**5. `resources/js/komponen-penilaian-edit.js`** — hapus 2 fungsi mati (tidak pernah dipanggil di `edit.blade.php` manapun setelah perbaikan Item A):

```js
export function komponenPenilaianEditForm() {
    return {
        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
            });
        },

        initSemesterSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari semester...',
            });
        },
    };
}
```

menjadi:

```js
export function komponenPenilaianEditForm() {
    return {};
}
```

(Import `TomSelect` di baris 1 file JADI TIDAK DIPAKAI LAGI — hapus juga `import TomSelect from 'tom-select';`. Fungsi `komponenPenilaianEditForm()` TETAP DIPERTAHANKAN sebagai objek kosong, JANGAN dihapus seluruhnya — `edit.blade.php` masih memanggilnya lewat `x-data="komponenPenilaianEditForm()"` di form-nya, meski isinya sekarang kosong.)

**6. Test existing yang WAJIB disesuaikan — TERNYATA ADA 3, BUKAN 1** (koreksi setelah audit ulang menyisir seluruh file test, bukan cuma test 258 yang saya temukan pertama kali): `tests/Feature/Admin/KomponenPenilaianCrudTest.php` punya 3 test yang SEMUANYA menguji kemampuan reassignment subjek/semester yang SENGAJA dihapus di Item A ini — SEMUANYA lewat HTTP penuh (`$this->actingAs($manager)->put(route('admin.komponen-penilaian.update', ...` dengan payload berisi `subjek_type`/`subjek_id`/`semester_id`, PERSIS pola yang sudah dibuktikan TIDAK PERNAH benar-benar dikirim `edit.blade.php` manapun):

- Baris ±258, *"updates a komponen penilaian including mata pelajaran and semester when not yet used"* — aktor lembaga-scope, ganti mata pelajaran+semester DALAM lembaga yang sama.
- Baris ±592, *"recomputes lembaga_id to follow the new semester for elemen_cp when a yayasan actor moves it across lembaga"* — aktor YAYASAN-scope, pindahkan TP (Elemen CP) ke semester LEMBAGA LAIN dalam yayasan yang sama, `lembaga_id` ikut berubah otomatis.
- Baris ±632, *"recomputes lembaga_id to follow the new semester for mata_pelajaran when a yayasan actor moves it across lembaga"* — SAMA seperti di atas, untuk subjek `mata_pelajaran`.

**Catatan penting**: 2 test terakhir (yayasan lintas-lembaga) SEMPAT membuat saya khawatir ada UI TERPISAH untuk aktor yayasan-scope yang belum saya temukan (mis. halaman reorganisasi kurikulum lintas-lembaga). Sudah dicek ulang MENYELURUH — `edit.blade.php` (satu-satunya view edit Admin yang ada) TIDAK PUNYA percabangan apa pun berdasarkan `widestScopeLevel()`/yayasan-scope, tampilannya SAMA PERSIS (read-only) untuk semua jenis aktor. Jadi KETIGA test ini murni menguji kemampuan backend yang SAMA-SAMA TIDAK PERNAH punya jalur UI nyata, hanya beda skenario data uji — bukan bukti adanya fitur/halaman lain yang terlewat. **Keputusan Opsi A tetap sama, TIDAK berubah** — cuma cakupan pembersihan test-nya lebih besar dari perkiraan awal.

**1 test LAIN yang TIDAK perlu diubah**: baris ±674, *"does not touch lembaga_id when updating a komponen without changing semester_id"* — payload-nya JUGA mengirim subjek_type/subjek_id/semester_id, TAPI dengan nilai yang SAMA seperti sebelumnya (kasus "tidak ganti apa-apa"). Assertion test ini (`deskripsi` berubah, `lembaga_id` TIDAK berubah) tetap benar dengan kode BARU (lembaga_id memang tidak pernah lagi disentuh sama sekali) — JANGAN diubah, biarkan apa adanya, akan tetap lulus.

Ganti isi test 258 jadi menguji perilaku BARU (subjek_type/subjek_id/semester_id di payload — kalau dikirim lewat jalur non-browser — diam-diam DIABAIKAN, bukan menyebabkan subjek berubah, TIDAK JUGA menyebabkan error validasi karena field itu sudah tidak ada di `rules()` sama sekali):

```php
it('updates a komponen penilaian including mata pelajaran and semester when not yet used', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap']);
    $mapelLama = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelBaru = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelLama->id, 'semester_id' => $semesterLama->id, 'kode' => 'TP LAMA']);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelBaru->id,
        'semester_id' => $semesterBaru->id,
        'kode' => 'TP BARU',
        'deskripsi' => 'Deskripsi baru',
        'kktp' => 'KKTP baru',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->subjek_id)->toBe($mapelLama->id);
    expect($komponen->semester_id)->toBe($semesterLama->id);
    expect($komponen->kode)->toBe('TP BARU');
    expect($komponen->deskripsi)->toBe('Deskripsi baru');
});
```

Catatan: TETAP mengirim `subjek_type`/`subjek_id`/`semester_id` di payload test ini (mensimulasikan permintaan mentah non-browser) — TAPI sekarang assert `subjek_id`/`semester_id` TIDAK BERUBAH (tetap `$mapelLama`/`$semesterLama`), field lain (`kode`/`deskripsi`) TETAP berubah normal. Nama test TIDAK diubah (masih ada kata "including mata pelajaran and semester") karena tetap relevan — tesnya kini MEMBUKTIKAN field itu diabaikan, bukan lagi membuktikan field itu ikut berubah.

Ganti isi test baris ±592 (*"recomputes lembaga_id to follow the new semester for elemen_cp when a yayasan actor moves it across lembaga"*) — ganti NAMA test-nya juga (nama lama tidak relevan lagi, perilaku yang diuji sudah terbalik) jadi *"ignores subjek/semester reassignment payload for elemen_cp even from a yayasan actor (lembaga_id stays put)"*:

```php
it('ignores subjek/semester reassignment payload for elemen_cp even from a yayasan actor (lembaga_id stays put)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $elemenCp = ElemenCp::factory()->create();

    $createAction = app(CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new KomponenPenilaianData(
        subjekType: 'elemen_cp',
        subjekId: $elemenCp->id,
        semesterId: $semesterA->id,
        kode: 'ECP-1',
        deskripsi: 'Deskripsi awal',
        bobot: 100,
        kktp: null,
        kktpMinimal: null,
        assessmentType: null,
    ));
    expect($komponen->lembaga_id)->toBe($lembagaA->id);

    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemenCp->id,
        'semester_id' => $semesterB->id,
        'kode' => 'ECP-1',
        'deskripsi' => 'Deskripsi diubah',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->semester_id)->toBe($semesterA->id);
    expect($komponen->lembaga_id)->toBe($lembagaA->id);
    expect($komponen->deskripsi)->toBe('Deskripsi diubah');
});
```

Ganti isi test baris ±632 (*"recomputes lembaga_id to follow the new semester for mata_pelajaran when a yayasan actor moves it across lembaga"*) — ganti nama jadi *"ignores subjek/semester reassignment payload for mata_pelajaran even from a yayasan actor (lembaga_id stays put)"*:

```php
it('ignores subjek/semester reassignment payload for mata_pelajaran even from a yayasan actor (lembaga_id stays put)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $createAction = app(CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new KomponenPenilaianData(
        subjekType: 'mata_pelajaran',
        subjekId: $mapelA->id,
        semesterId: $semesterA->id,
        kode: 'MP-1',
        deskripsi: 'Deskripsi awal',
        bobot: 100,
        kktp: null,
        kktpMinimal: null,
        assessmentType: null,
    ));
    expect($komponen->lembaga_id)->toBe($lembagaA->id);

    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelB->id,
        'semester_id' => $semesterB->id,
        'kode' => 'MP-1',
        'deskripsi' => 'Deskripsi diubah',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->subjek_id)->toBe($mapelA->id);
    expect($komponen->semester_id)->toBe($semesterA->id);
    expect($komponen->lembaga_id)->toBe($lembagaA->id);
    expect($komponen->deskripsi)->toBe('Deskripsi diubah');
});
```

Test baris ±674 (*"does not touch lembaga_id when updating a komponen without changing semester_id"*) TIDAK DIUBAH sama sekali — sudah dicek, assertion-nya tetap benar dengan kode baru.

---

## Item B — Tidak Ada Badge Scope (isYayasan/activeLembaga) + Label "(Aktif)"

### Masalah

`index.blade.php` (Admin) tidak punya badge scope sama sekali, dan dropdown Tahun Ajaran tidak ada label "(Aktif)" — pola yang sudah kita tegakkan konsisten di semua menu lain sepanjang rangkaian audit ini (RPP, Jadwal Pelajaran, Pola Jam, dst).

### Perbaikan

`app/Http/Controllers/Admin/KomponenPenilaianController.php` — tambahkan import `use App\Models\Lembaga;` (dicek: BELUM ada), tambahkan method `scopeHeaderData()` (pola PERSIS sama seperti menu lain).

**Catatan beda dari pola RPP/Jadwal Pelajaran**: DI SANA `scopeHeaderData()` WAJIB dipanggil di KEDUA cabang `index()` karena ADA item lain yang menaruh sesuatu (dropdown/badge tambahan) di dalam partial yang dirender ulang lewat AJAX. **DI SINI TIDAK ADA kebutuhan seperti itu** — badge scope HANYA muncul di header `index.blade.php` (halaman penuh), yang TIDAK PERNAH dirender ulang oleh AJAX (dikonfirmasi: `_daftar.blade.php` Komponen Penilaian TIDAK menyebut `isYayasan`/`activeLembaga` sama sekali). Jadi `scopeHeaderData()` CUKUP dipanggil di cabang HALAMAN PENUH SAJA — memanggilnya juga di cabang ajax (baris 55-56, `return view('...._daftar', ['komponenList' => $komponenList])->render();`) hanya buang-buang komputasi tanpa manfaat. Cabang ajax TIDAK PERLU disentuh sama sekali di item ini.

```php
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

**PENTING**: `KomponenPenilaianController` (Admin) SAAT INI **TIDAK** `use ResolveLembagaScopeTrait;` sama sekali (dicek langsung — beda dari `RppController`/`JadwalPelajaranController`/dll yang sudah). WAJIB tambahkan `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;` (import) DAN `use ResolveLembagaScopeTrait;` (trait use di dalam class) SEBELUM method `scopeHeaderData()` bisa memanggil `$this->resolveActiveLembagaId()`.

`index.blade.php` header, ganti:

```blade
<h1 class="font-display text-lg font-bold text-gray-900">Komponen Penilaian (TP)</h1>
```

menjadi:

```blade
<div class="flex flex-wrap items-center gap-2.5">
    <h1 class="font-display text-lg font-bold text-gray-900">Komponen Penilaian (TP)</h1>
    @if ($isYayasan ?? false)
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
            <x-icon name="apartment" class="h-3.5 w-3.5" />
            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
        </span>
    @endif
</div>
```

Dropdown Tahun Ajaran, ganti:

```blade
<option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
```

menjadi:

```blade
<option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}{{ $tahunAjaran->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}</option>
```

(Perlu eager-load `lembaga` pada `$tahunAjaranList` — dicek langsung, `index()` method (baris ±60) SAAT INI `TahunAjaran::orderByDesc('id')->get()` TANPA eager-load. Ganti jadi `TahunAjaran::with('lembaga')->orderByDesc('id')->get()`. **HANYA di method `index()`** — method `create()` (baris ±95) TIDAK PERLU diubah, karena dropdown Tahun Ajaran di `create.blade.php` TIDAK disentuh Item B ini (di luar scope, dropdown itu konteksnya beda — 1 form pembuatan TP baru, bukan daftar agregat).)

---

## Item C — Daftar Bentuk Pendidikan PAUD Di-hardcode, Bukan Pakai Enum

### Masalah

`create.blade.php` baris 31: `in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true)` (2 kali, untuk `subjekType` dan `assessmentType` default) — padahal sudah ada `BentukPendidikan::isPaud()` yang jadi single-source-of-truth dipakai di tempat lain (`Guru\KomponenPenilaianController::create()` baris 98 SUDAH pakai `BentukPendidikan::tryFrom($bentukPendidikan ?? '')?->isPaud()`). Risiko: kalau definisi PAUD berubah (misalnya nanti SLB ikut jalur Elemen CP), array hardcode ini diam-diam tidak ikut ter-update.

### Perbaikan

`app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `create()` — kirim hasil `isPaud()` yang sudah dihitung dari controller (BUKAN string mentah `bentukPendidikan`), konsisten dengan pola Guru:

Ganti:

```php
'bentukPendidikan' => $request->user()->lembaga?->bentuk_pendidikan,
```

menjadi:

```php
'isPaud' => \App\Domains\Akademik\Enums\BentukPendidikan::tryFrom($request->user()->lembaga?->bentuk_pendidikan ?? '')?->isPaud() ?? false,
```

`create.blade.php` baris 31, ganti:

```blade
{{ old('subjek_type', in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true) ? 'elemen_cp' : 'mata_pelajaran') }}', assessmentType: '{{ old('assessment_type', in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true) ? 'narrative' : 'numeric') }}'
```

menjadi:

```blade
{{ old('subjek_type', $isPaud ? 'elemen_cp' : 'mata_pelajaran') }}', assessmentType: '{{ old('assessment_type', $isPaud ? 'narrative' : 'numeric') }}'
```

**Catatan**: cek dulu apakah `$bentukPendidikan` (variabel LAMA) dipakai di tempat LAIN di `create.blade.php` selain baris 31 — kalau ADA (mis. ditampilkan sebagai info di suatu tempat), variabel lama TETAP dikirim controller berdampingan dengan `isPaud` baru, JANGAN dihapus sepenuhnya tanpa cek pemakaian lain dulu.

---

## Item D — 3 Default "Bobot" yang Tidak Konsisten (Minor, Kode Rapi)

### Masalah

- `KomponenPenilaianData::fromArray()` (Store, CREATE): fallback `10` kalau `bobot` tidak ada di payload.
- `create.blade.php`: `value="{{ old('bobot', 100) }}"` — default tampilan `100`.
- `_daftar.blade.php`/`edit.blade.php`: `$komponen->bobot ?? 100` — fallback tampilan `100` untuk data legacy null.

**Koreksi presisi setelah dicek ulang**: `bobot` di `rules()` Store (Admin MAUPUN Guru) sebenarnya `nullable`, BUKAN `required` — jadi klaim "dead code" tidak 100% akurat di level FormRequest. Yang benar: KEDUA form CREATE (`create.blade.php` Admin baris 147, Guru baris 106) punya atribut HTML `required` + default tampilan `100` — jadi lewat UI BROWSER NORMAL, `bobot` memang selalu terkirim dan fallback `10` tidak pernah kepakai. Fallback `10` HANYA akan kepakai kalau ada permintaan HTTP mentah (API/Postman) yang sengaja tidak menyertakan `bobot` sama sekali — jalur yang secara teknis ADA tapi di luar UI resmi manapun. Tetap inkonsisten dan berpotensi membingungkan kalau dibaca ulang nanti (angka `10` vs `100` tanpa penjelasan kenapa beda).

### Perbaikan

Samakan fallback DTO dengan yang dipakai di UI (`100`), murni untuk konsistensi kode (perilaku UI browser normal TIDAK berubah karena bobot selalu terkirim dari sana; HANYA memengaruhi jalur API mentah yang sengaja tidak menyertakan bobot):

`app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`, ganti:

```php
bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
```

menjadi:

```php
bobot: isset($data['bobot']) ? (int) $data['bobot'] : 100,
```

---

## Di Luar Scope

- Backend inti (guard bobot 100%, `lockForUpdate()`, proteksi hapus/ubah TP yang sudah dipakai, cross-tenant check `mata_pelajaran`) TIDAK diubah — sudah solid, dikonfirmasi lewat audit mendalam.
- Jalur Guru (`Guru\KomponenPenilaianController`, `UpdateKomponenPenilaianSendiriRequest`, view `portals/guru/...`) TIDAK diubah — SUDAH BENAR, jadi rujukan/pembanding untuk Item A, bukan target perbaikan.
- Fitur baru (live indicator "sisa bobot X%" saat mengisi form CREATE, sebelum submit) TIDAK ditambahkan — di luar scope, "Live Calculator" yang sudah ada di halaman daftar (`_daftar.blade.php`) dianggap cukup.

## Tabel Panduan Test

| Item | Test yang dibutuhkan |
|---|---|
| A | Edit TP yang BELUM dipakai (`!$dipakai`) berhasil menyimpan perubahan kode/deskripsi/bobot/kktp/assessment_type TANPA error validasi; payload berisi subjek_type/subjek_id/semester_id (kalaupun dikirim, dari lembaga-scope MAUPUN yayasan-scope actor, subjek mata_pelajaran MAUPUN elemen_cp) diabaikan diam-diam, bukan mengubah data ATAU menyebabkan error; regresi 2 test "locks..." existing (dipakai=true) DAN 1 test "does not touch lembaga_id..." (baris 674) tetap lulus tanpa perubahan |
| B | Badge scope + label "(Aktif)" konsisten dengan pola menu lain (agregat/narrow) |
| C | Aktor lembaga PAUD (bentuk_pendidikan KB/TPA/SPS/TK) tetap default ke `elemen_cp`/`narrative` di form create, aktor non-PAUD tetap default ke `mata_pelajaran`/`numeric` (regresi perilaku, BUKAN fitur baru) |
| D | Store TP via payload TANPA `bobot` (mensimulasikan API mentah tanpa lewat UI form) menghasilkan `bobot` tersimpan `100`, bukan `10` — satu-satunya perilaku nyata yang berubah, jalur UI form normal TIDAK terpengaruh |
