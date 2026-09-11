# Spec: Perbaikan Audit Menyeluruh Halaman Kenaikan Kelas

> **Tanggal**: 11 September 2026
> **Konteks**: Audit menyeluruh (backend/bisnis/scope + UI/UX/frontend/wording, 2 putaran audit + 1 putaran audit ulang atas permintaan eksplisit user) pada fitur Kenaikan Kelas (`app/Http/Controllers/Admin/KenaikanKelasController.php`, `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php`, `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`). TIDAK menyentuh `app/Domains/Workflow/*`.

---

## 1. Latar Belakang

Fitur ini pernah disentuh spec sebelumnya (`.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md`, branch `akademik-v2`) yang menetapkan `UpdateStatusSiswaAction` sebagai jalur RESMI untuk transisi status siswa (termasuk menonaktifkan akun `user.is_active` dan snapshot `kelas_terakhir_id`). Spec itu secara eksplisit mendaftarkan `ProsesKenaikanKelasAction` sebagai salah satu dari "5 titik gratis" — TAPI hanya dalam artian "query lain yang membaca `kelas_id` otomatis benar begitu `kelas_id` di-null-kan", BUKAN audit apakah `ProsesKenaikanKelasAction` SENDIRI benar-benar memakai jalur resmi itu untuk transisinya sendiri. Audit sesi ini menemukan: **`ProsesKenaikanKelasAction` tidak pernah memakai `UpdateStatusSiswaAction` sama sekali** — ia mereplikasi logic serupa lewat mass-update query builder langsung, yang secara diam-diam melewati SELURUH mekanisme Eloquent model event (dan karenanya melewati deaktivasi akun, generate tagihan otomatis, dan activity log sekaligus). Ini dikonfirmasi BUKAN sudah-diperbaiki oleh spec 2026-09-03 — itu gap yang genuinely belum tersentuh.

Selain itu, audit UI/UX + audit ulang atas permintaan user menemukan sejumlah gap tambahan: tidak ada konfirmasi/pratinjau untuk aksi bulk ireversibel, dropdown tahun ajaran ambigu di mode agregat multi-lembaga, tidak ada validasi tahun ajaran sumber vs tujuan di titik masuk, checkbox salin-jadwal gagal senyap, dan beberapa gap wording/style/a11y.

---

## 2. Temuan & Perbaikan

### 2.1 [CRITICAL] Kenaikan/Kelulusan Massal Melewati Seluruh Mekanisme Eloquent Model Event

**Lokasi**: `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php:42-50` (tindakan `lulus`) dan `:66` (tindakan `naik`)

**Akar masalah**: kedua baris memakai `Siswa::where(...)->update([...])` — mass-update lewat Query Builder Eloquent, bukan `$model->save()`/`$model->update()` per instance. Laravel TIDAK memicu model event (`saving`/`updating`/`updated`) untuk mass-update lewat query builder. Konsekuensi berantai (3 mekanisme sekaligus terlewati):

1. **`Siswa::booted()` `static::updated()`** (`app/Models/Siswa.php:157-161`) — hanya terpicu lewat `$model->save()`. Event ini mendengarkan `wasChanged('kelas_id')` dan men-dispatch `StudentUpdatedClass`, yang didengarkan `App\Domains\Keuangan\Listeners\GenerateTagihanForUpdatedClass` untuk membuat tagihan baru otomatis sesuai tingkat/kelas baru. **Tidak pernah terpicu** — sekolah kehilangan potensi pendapatan tanpa ada tanda kesalahan apa pun.
2. **`user.is_active`** — jalur resmi (`UpdateStatusSiswaAction.php:31-33`, ditetapkan spec `2026-09-03-siklus-hidup-kelas-id-siswa.md` §5) menonaktifkan akun user siswa saat status berubah ke non-Aktif. `ProsesKenaikanKelasAction` TIDAK PERNAH memanggil action ini — siswa yang "Lulus" lewat halaman ini **tetap bisa login selamanya**.
3. **Activity log** (`Spatie\Activitylog`, `Siswa.php` `getActivitylogOptions()`) — juga bergantung pada Eloquent model event yang sama. Aksi bulk yang memindahkan/meluluskan ratusan siswa sekaligus **tidak tercatat sama sekali** — tidak ada jejak audit kalau ada sengketa "siapa memproses siswa X, kapan".

**Perbaikan**: ganti mass-update dengan iterasi per-siswa, reuse `UpdateStatusSiswaAction` (jalur resmi yang SUDAH ADA dan SUDAH benar) untuk tindakan `lulus`, dan `$model->update()` biasa untuk tindakan `naik` (supaya event `wasChanged('kelas_id')` terpicu wajar). `ProsesKenaikanKelasAction` menambah 1 dependency baru via constructor injection (pola sudah established — `CreateJadwalPelajaranAction` sudah di-inject serupa):

```php
final class ProsesKenaikanKelasAction
{
    public function __construct(
        private readonly CreateJadwalPelajaranAction $createJadwalPelajaranAction,
        private readonly UpdateStatusSiswaAction $updateStatusSiswaAction,
    ) {}

    /**
     * @return array{jadwalGagal: array<int, string>, siswaNaik: int, siswaLulus: int, kelasDilewati: int}
     *
     * @throws \DomainException kalau kelas tujuan berada di tahun ajaran yang sama dengan kelas asal
     */
    public function execute(KenaikanKelasData $data): array
    {
        $jadwalGagal = [];
        $siswaNaik = 0;
        $siswaLulus = 0;
        $kelasDilewati = 0;

        DB::transaction(function () use ($data, &$jadwalGagal, &$siswaNaik, &$siswaLulus, &$kelasDilewati) {
            foreach ($data->mapping as $kelasLamaId => $aksi) {
                if ($aksi['tindakan'] === 'lewati') {
                    $kelasDilewati++;

                    continue;
                }

                $kelasLama = Kelas::findOrFail($kelasLamaId);

                if ($aksi['tindakan'] === 'lulus') {
                    $siswaList = Siswa::where('kelas_id', $kelasLama->id)->get();
                    foreach ($siswaList as $siswa) {
                        $this->updateStatusSiswaAction->execute($siswa, StatusSiswa::Lulus);
                        $siswaLulus++;
                    }

                    continue;
                }

                $kelasBaru = Kelas::find($aksi['kelas_baru_id']);
                abort_if($kelasBaru === null || $kelasBaru->lembaga_id !== $kelasLama->lembaga_id, 404);

                if ($kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" masih berada di tahun ajaran yang sama dengan kelas asal \"{$kelasLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                $tahunAjaranLama = TahunAjaran::findOrFail($kelasLama->tahun_ajaran_id);
                $tahunAjaranBaru = TahunAjaran::findOrFail($kelasBaru->tahun_ajaran_id);

                if ($tahunAjaranBaru->tanggal_mulai < $tahunAjaranLama->tanggal_mulai) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" berada di tahun ajaran \"{$tahunAjaranBaru->nama}\" yang lebih lama dari tahun ajaran kelas asal \"{$tahunAjaranLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                $siswaList = Siswa::where('kelas_id', $kelasLama->id)->get();
                foreach ($siswaList as $siswa) {
                    $siswa->update(['kelas_id' => $kelasBaru->id]);
                    $siswaNaik++;
                }

                if (($aksi['salin_jadwal'] ?? false) && ! empty($aksi['semester_tujuan_id'])) {
                    $semesterTujuan = Semester::find($aksi['semester_tujuan_id']);
                    abort_if(
                        $semesterTujuan === null
                        || $semesterTujuan->lembaga_id !== $kelasLama->lembaga_id
                        || $semesterTujuan->tahun_ajaran_id !== $kelasBaru->tahun_ajaran_id,
                        404
                    );

                    $gagalDiBaris = $this->salinJadwal($kelasLama, $kelasBaru, $semesterTujuan->id);
                    $jadwalGagal = array_merge($jadwalGagal, $gagalDiBaris);
                }
            }
        });

        return [
            'jadwalGagal' => $jadwalGagal,
            'siswaNaik' => $siswaNaik,
            'siswaLulus' => $siswaLulus,
            'kelasDilewati' => $kelasDilewati,
        ];
    }

    // salinJadwal() TIDAK BERUBAH dari kode existing.
}
```

Tambah import: `use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;`.

**Kenapa BUKAN dispatch event manual / observer tambahan**: sudah ada jalur resmi (`UpdateStatusSiswaAction`) yang benar dan teruji untuk transisi status — memakainya ulang lebih aman (satu sumber kebenaran untuk "apa yang terjadi saat siswa jadi non-aktif") daripada menduplikasi logic `is_active`/snapshot secara terpisah di sini yang berisiko menyimpang seiring waktu (persis pola anti-duplikasi yang sudah ditetapkan spec 2026-09-03 §5 untuk kasus serupa).

**Dampak performa**: N query per siswa alih-alih 1 query bulk — diterima sebagai trade-off (pola yang SAMA sudah dipakai `UpdateStatusSiswaAction` sendiri per-siswa, bukan pola baru yang asing di codebase ini). Skala kelas realistis (puluhan siswa per kelas) membuat ini bukan masalah performa nyata.

### 2.2 [HIGH] Tidak Ada Validasi Tahun Ajaran Sumber vs Tujuan di Titik Masuk

**Lokasi**: `app/Http/Controllers/Admin/KenaikanKelasController.php:20-41` (method `index()`)

**Akar masalah**: TIDAK ADA validasi apa pun yang membandingkan `tahun_ajaran_id` (sumber) dengan `tahun_ajaran_tujuan_id` (tujuan) di titik masuk. Kesalahan baru ketahuan jauh di dalam `ProsesKenaikanKelasAction::execute()`, dan **HANYA untuk tindakan `naik`** — tindakan `lulus` sama sekali tidak pernah mengecek `tahun_ajaran_tujuan_id`, jadi kombinasi sumber=tujuan yang salah untuk baris `lulus` tidak akan pernah ditolak sistem sama sekali.

**Perbaikan**: validasi proaktif di `index()`, SEBELUM merender tabel pemetaan — mengecek 2 kondisi yang sama dengan yang sudah ada di dalam Action (sumber=tujuan sama persis, dan tujuan lebih lama dari sumber), tapi diterapkan di TITIK MASUK supaya berlaku untuk SEMUA tindakan, bukan cuma `naik`:

```php
public function index(Request $request): View
{
    $this->authorize('kenaikan-kelas.kelola');

    $tahunAjaranId = $request->query('tahun_ajaran_id');
    $tahunAjaranTujuanId = $request->query('tahun_ajaran_tujuan_id');

    $errorTahunAjaran = null;
    if ($tahunAjaranId && $tahunAjaranTujuanId) {
        $tahunSumber = TahunAjaran::find($tahunAjaranId);
        $tahunTujuan = TahunAjaran::find($tahunAjaranTujuanId);

        if ($tahunSumber && $tahunTujuan) {
            if ((int) $tahunAjaranId === (int) $tahunAjaranTujuanId) {
                $errorTahunAjaran = 'Tahun Ajaran Sumber dan Tujuan tidak boleh sama. Pilih Tahun Ajaran Tujuan yang berbeda (biasanya tahun ajaran berikutnya).';
            } elseif ($tahunTujuan->tanggal_mulai < $tahunSumber->tanggal_mulai) {
                $errorTahunAjaran = "Tahun Ajaran Tujuan (\"{$tahunTujuan->nama}\") lebih lama dari Tahun Ajaran Sumber (\"{$tahunSumber->nama}\"). Pilih Tahun Ajaran Tujuan yang lebih baru.";
            }
        }
    }

    return view('portals.lembaga.akademik.kenaikan-kelas.index', [
        'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('tanggal_mulai')->get(),
        'kelasLamaList' => ($tahunAjaranId && ! $errorTahunAjaran)
            ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->with('lembaga')->withCount('siswa')->orderBy('nama')->get()
            : collect(),
        'kelasTujuanList' => ($tahunAjaranTujuanId && ! $errorTahunAjaran)
            ? Kelas::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderBy('nama')->get()
            : collect(),
        'semesterList' => ($tahunAjaranTujuanId && ! $errorTahunAjaran)
            ? Semester::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderByDesc('id')->get()
            : collect(),
        'tahunAjaranId' => $tahunAjaranId,
        'tahunAjaranTujuanId' => $tahunAjaranTujuanId,
        'errorTahunAjaran' => $errorTahunAjaran,
    ]);
}
```

**Catatan**: `TahunAjaran::find(...)` otomatis ter-scope `TenantScope` — kalau ID milik lembaga/yayasan lain, `find()` mengembalikan `null` dan blok validasi di atas otomatis dilewati (tidak error palsu), sementara `kelasLamaList`/`kelasTujuanList` tetap kosong karena `Kelas::where('tahun_ajaran_id', ...)` juga otomatis ter-scope — tidak ada kebocoran data lintas tenant dari perubahan ini.

**View**: tambahkan banner error di `index.blade.php`, SEBELUM blok "Source & Target Tahun Ajaran Picker" (lihat §2.7 untuk kode lengkap gabungan perubahan view).

### 2.3 [HIGH] Dropdown Tahun Ajaran Ambigu di Mode Agregat Multi-Lembaga

**Lokasi**: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php:27-29,36-38`

**Akar masalah**: `tahun_ajaran` unique per `(lembaga_id, nama)` — setiap lembaga punya baris "2026/2027" TERPISAH. Dropdown cuma menampilkan `{{ $tahunAjaran->nama }}` — untuk aktor yayasan-scope dengan beberapa lembaga (mode "Semua Lembaga"), dropdown menampilkan nama yang SAMA berkali-kali tanpa bisa dibedakan milik lembaga mana. Admin bisa salah pilih tanpa sadar, dan hasil setelah dipilih HANYA kelas dari 1 lembaga itu (bukan agregat).

**Perbaikan**: tampilkan nama lembaga di setiap opsi, konsisten untuk kedua dropdown (Sumber & Tujuan):

```blade
<div class="flex-1 min-w-[220px]">
    <x-input-label for="tahun_ajaran_id" value="Tahun Ajaran Sumber (kelas lama)" />
    <select id="tahun_ajaran_id" name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">— Pilih —</option>
        @foreach ($tahunAjaranList as $tahunAjaran)
            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }} — {{ $tahunAjaran->lembaga->nama }}</option>
        @endforeach
    </select>
</div>
<div class="flex-1 min-w-[220px]">
    <x-input-label for="tahun_ajaran_tujuan_id" value="Tahun Ajaran Tujuan (kelas baru)" />
    <select id="tahun_ajaran_tujuan_id" name="tahun_ajaran_tujuan_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">— Pilih —</option>
        @foreach ($tahunAjaranList as $tahunAjaran)
            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranTujuanId == $tahunAjaran->id)>{{ $tahunAjaran->nama }} — {{ $tahunAjaran->lembaga->nama }}</option>
        @endforeach
    </select>
</div>
```

(`id="tahun_ajaran_id"`/`id="tahun_ajaran_tujuan_id"` + `<x-input-label for="...">` sekaligus menutup temuan §2.9 a11y — 1 perubahan, 2 gap tertutup.) `$tahunAjaranList` sudah di-eager-load `with('lembaga')` di §2.2, tidak perlu query tambahan.

### 2.4 [MEDIUM] `kelas_baru_id` Tidak Divalidasi `exists:kelas,id`, Checkbox Salin Jadwal Gagal Senyap

**Lokasi**: `app/Http/Controllers/Admin/KenaikanKelasController.php:47-53` (method `store()`)

**Akar masalah 1**: `'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer']` — tidak ada `exists:kelas,id`. ID salah/manipulasi baru ketahuan lewat `abort_if(...404)` di tengah `DB::transaction` di dalam Action — 404 generik, bukan pesan validasi jelas.

**Akar masalah 2**: `ProsesKenaikanKelasAction.php:68` (kode saat ini) — `if (($aksi['salin_jadwal'] ?? false) && ! empty($aksi['semester_tujuan_id']))`. Kalau admin mencentang "Salin Jadwal" TAPI lupa pilih semester tujuan, jadwal **tidak disalin sama sekali TANPA pesan error apa pun** — request tetap dianggap sukses penuh.

**Perbaikan**: tambah `exists:kelas,id` dan `exists:semester,id`, plus `required_if` untuk `semester_tujuan_id` saat `salin_jadwal` dicentang:

```php
$data = $request->validate([
    'mapping' => ['required', 'array'],
    'mapping.*.tindakan' => ['required', 'in:naik,lulus,lewati'],
    'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer', 'exists:kelas,id'],
    'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
    'mapping.*.semester_tujuan_id' => ['required_if:mapping.*.salin_jadwal,1', 'nullable', 'integer', 'exists:semester,id'],
], [
    'mapping.*.kelas_baru_id.exists' => 'Kelas tujuan yang dipilih tidak valid atau sudah tidak tersedia.',
    'mapping.*.semester_tujuan_id.required_if' => 'Anda mencentang "Salin Jadwal" untuk salah satu kelas, tapi belum memilih semester tujuan. Pilih semester tujuan atau batalkan centang tersebut.',
]);
```

(Validasi `exists:kelas,id`/`exists:semester,id` di sini murni memastikan ROW-nya ada secara umum — bukan pengganti guard kepemilikan lembaga/tahun-ajaran yang SUDAH benar dilakukan di dalam `ProsesKenaikanKelasAction` lewat `abort_if(...)`. Kedua lapis tetap dipertahankan, tidak saling menggantikan.)

### 2.5 [MEDIUM] Feedback Hasil Setelah Submit Terlalu Ringkas

**Lokasi**: `app/Http/Controllers/Admin/KenaikanKelasController.php:55-66` (method `store()`)

**Akar masalah**: flash message cuma "Kenaikan kelas berhasil diproses." + info jadwal gagal (kalau ada). Tidak ada ringkasan berapa siswa naik/lulus/kelas dilewati — untuk aksi yang memindahkan puluhan/ratusan siswa, admin tidak punya cara memverifikasi hasil cocok ekspektasi tanpa mengecek manual.

**Perbaikan**: manfaatkan counts baru dari §2.1 (`siswaNaik`, `siswaLulus`, `kelasDilewati`) untuk flash message yang lebih informatif:

```php
public function store(Request $request, ProsesKenaikanKelasAction $action): RedirectResponse
{
    $this->authorize('kenaikan-kelas.kelola');

    $data = $request->validate([
        'mapping' => ['required', 'array'],
        'mapping.*.tindakan' => ['required', 'in:naik,lulus,lewati'],
        'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer', 'exists:kelas,id'],
        'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
        'mapping.*.semester_tujuan_id' => ['required_if:mapping.*.salin_jadwal,1', 'nullable', 'integer', 'exists:semester,id'],
    ], [
        'mapping.*.kelas_baru_id.exists' => 'Kelas tujuan yang dipilih tidak valid atau sudah tidak tersedia.',
        'mapping.*.semester_tujuan_id.required_if' => 'Anda mencentang "Salin Jadwal" untuk salah satu kelas, tapi belum memilih semester tujuan. Pilih semester tujuan atau batalkan centang tersebut.',
    ]);

    try {
        $result = $action->execute(new KenaikanKelasData(mapping: $data['mapping']));
    } catch (\DomainException $e) {
        return back()->withErrors(['mapping' => $e->getMessage()]);
    }

    $status = "Kenaikan kelas berhasil diproses: {$result['siswaNaik']} siswa naik kelas, {$result['siswaLulus']} siswa diluluskan, {$result['kelasDilewati']} kelas dilewati.";
    if (! empty($result['jadwalGagal'])) {
        $status .= ' '.count($result['jadwalGagal']).' jadwal tidak tersalin karena bentrok: '.implode('; ', $result['jadwalGagal']).'.';
    }

    return redirect()->route('admin.kelas.index')->with('status', $status);
}
```

### 2.6 [HIGH+MEDIUM gabungan] Tidak Ada Konfirmasi/Ringkasan Sebelum Submit, Peringatan Kurikulum/Tingkat Murni Kosmetik

**Lokasi**: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php` (form + tabel), file JS baru `resources/js/kenaikan-kelas-form.js`, `resources/js/app.js`

**Akar masalah**: form submit langsung tanpa `confirmDialog()` apa pun — pola yang KONSISTEN dipakai proyek untuk aksi jauh lebih kecil dampaknya (submit 1 form izin cuti, aktivasi 1 tahun ajaran). Peringatan "⚠ Kurikulum berbeda"/"⚠ Tingkat tidak wajar" (Alpine per-baris, SUDAH ADA, TIDAK diubah logic-nya) murni informatif — tidak ada sinyal visual di level baris (`<tr>` tidak berubah warna), gampang terlewat di tabel panjang, dan tidak mem-block atau bahkan MEMINTA admin mengakui sebelum submit.

**Perbaikan**: tambah wrapper `x-data` di level `<form>` yang, saat submit, menghitung ringkasan (jumlah naik/lulus/lewati + jumlah baris berperingatan) dari DOM secara langsung (BUKAN merestrukturisasi arsitektur per-baris `x-data` yang sudah ada — pendekatan minimal-risiko), lalu menampilkannya lewat `confirmDialog()` sebelum benar-benar submit. Baris dengan peringatan kurikulum/tingkat diberi highlight visual (border kiri amber) via binding reaktif `x-data` per-baris yang SUDAH ADA (cukup tambah 1 binding `:class`/`:data-warning`, tidak mengubah getter yang sudah ada).

**File baru `resources/js/kenaikan-kelas-form.js`**:

```js
export function kenaikanKelasForm() {
    return {
        submitting: false,

        async konfirmasiDanKirim(event) {
            const form = event.target;
            const rows = form.querySelectorAll('tbody tr[data-kelas-lama]');
            let naik = 0;
            let lulus = 0;
            let lewati = 0;
            let peringatan = 0;

            rows.forEach((row) => {
                const tindakan = row.querySelector('select[name$="[tindakan]"]')?.value;
                if (tindakan === 'naik') naik++;
                else if (tindakan === 'lulus') lulus++;
                else lewati++;

                if (row.dataset.warning === '1') peringatan++;
            });

            let message = `${naik} kelas akan dinaikkan, ${lulus} kelas akan diluluskan, ${lewati} kelas dilewati.`;
            if (lulus > 0) {
                message += ' Siswa yang diluluskan akan dinonaktifkan akunnya secara otomatis.';
            }
            if (peringatan > 0) {
                message += ` Perhatian: ${peringatan} baris punya peringatan kurikulum/tingkat tidak wajar — periksa kembali kolom "Kelas Tujuan" sebelum lanjut.`;
            }
            message += ' Tindakan ini memindahkan/meluluskan siswa secara langsung dan tidak bisa dibatalkan otomatis.';

            const confirmed = await window.confirmDialog('Proses Kenaikan Kelas?', message, { confirmLabel: 'Ya, Proses Sekarang' });
            if (confirmed) {
                this.submitting = true;
                form.submit();
            }
        },
    };
}
```

**Registrasi di `resources/js/app.js`**: tambah `import { kenaikanKelasForm } from './kenaikan-kelas-form';` di bagian import, dan `Alpine.data('kenaikanKelasForm', kenaikanKelasForm);` di bagian registrasi (ikuti pola baris-baris yang sudah ada persis, taruh berdekatan alfabetis/tematis dengan modul Akademik lain kalau ada pengelompokan, kalau tidak ada pengelompokan taruh di akhir daftar `Alpine.data` yang sudah ada).

**Perubahan di `index.blade.php`** — tag `<form>` (baris 58 saat ini):

```blade
<form method="POST" action="{{ route('admin.kenaikan-kelas.store') }}" x-data="kenaikanKelasForm()" @submit.prevent="konfirmasiDanKirim($event)">
```

Setiap `<tr>` baris kelas (baris 74 saat ini, `x-data` per-baris SUDAH ADA, TIDAK diubah isi getter-nya) — tambah `data-kelas-lama` dan binding `:data-warning`/`:class` baru, SETELAH deklarasi `x-data` yang sudah ada:

```blade
<tr data-kelas-lama="{{ $kelasLama->id }}"
    :data-warning="((kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal) || (selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1)) ? '1' : '0'"
    :class="{ 'border-l-4 border-amber-400 bg-amber-50/30': ((kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal) || (selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1)) }"
    class="transition hover:bg-gray-50/60"
    x-data="{
        kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
        kurikulumTujuan: null,
        tingkatTujuan: null,
        tingkatAsal: {{ Js::from($kelasLama->tingkat) }},
        daftarTingkat: {{ Js::from($kelasLama->lembaga ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->validTingkatValues() : []) }},
        onKelasTujuanChange(event) {
            const opt = event.target.selectedOptions[0];
            this.kurikulumTujuan = opt?.dataset.kurikulum || null;
            this.tingkatTujuan = opt?.dataset.tingkat || null;
        },
        get selisihIndexTingkat() {
            if (this.tingkatTujuan === null || this.tingkatAsal === null) return null;
            const indexAsal = this.daftarTingkat.indexOf(this.tingkatAsal);
            const indexTujuan = this.daftarTingkat.indexOf(this.tingkatTujuan);
            if (indexAsal === -1 || indexTujuan === -1) return null;
            return indexTujuan - indexAsal;
        },
    }">
```

Tombol submit (baris 153 saat ini) — tambah guard double-submit:

```blade
<x-primary-button type="submit" :disabled="false" x-bind:disabled="submitting">Proses Kenaikan Kelas</x-primary-button>
```

### 2.7 [LOW gabungan] Wording, A11y, Penjelasan Fitur, Opsi "Lewati" Selalu Tersedia, Style Tabel

**Lokasi**: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php` (berbagai baris)

**a. Banner error validasi tahun ajaran (§2.2)** — tambah SETELAH blok picker form (baris 43 saat ini), SEBELUM blok "brand-50 info banner" existing:

```blade
@if ($errorTahunAjaran)
    <div class="rounded-2xl border border-error-200 bg-error-50 p-4 text-sm text-error-700">
        {{ $errorTahunAjaran }}
    </div>
@endif
```

**b. Penjelasan fungsi "Salin Jadwal ke Semester"** — akar masalah: kolom tabel (`:69` header, `:135-145` cell) tidak punya teks bantuan sama sekali. Tambah subjudul kecil di bawah judul kolom header:

```blade
<th class="px-4 py-3.5">
    Salin Jadwal ke Semester
    <span class="block text-[10px] font-normal normal-case text-gray-400 mt-0.5">Menyalin struktur jadwal pelajaran kelas lama ke kelas tujuan, di semester yang dipilih</span>
</th>
```

**c. Opsi "Lewati" selalu tersedia, bukan cuma untuk kelas kosong** — akar masalah: `:109-111` (kode saat ini) cuma render opsi `lewati` kalau `siswa_count === 0`, memaksa admin memproses SEMUA kelas berisi siswa dalam 1 batch tanpa bisa menunda kelas tertentu. Backend SUDAH mendukung tindakan `lewati` untuk kelas apa pun (baris pertama loop Action langsung `continue`) — ini murni gap UI. Ubah dari kondisional jadi selalu tampil:

```blade
<select name="mapping[{{ $kelasLama->id }}][tindakan]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
    <option value="lewati" @selected($kelasLama->siswa_count === 0)>Lewati{{ $kelasLama->siswa_count === 0 ? ' (sudah kosong)' : '' }}</option>
    <option value="naik" @selected(! $isTingkatAkhir && $kelasLama->siswa_count > 0)>Naik Kelas</option>
    <option value="lulus" @selected($isTingkatAkhir && $kelasLama->siswa_count > 0)>Lulus</option>
</select>
```

(Logic `@selected(...)` untuk `naik`/`lulus` TIDAK berubah — hanya opsi `lewati` yang sekarang selalu dirender, dengan label kondisional teksnya saja.)

**d. Wording peringatan kurikulum pakai label, bukan raw enum value** — akar masalah: `:127-129` (kode saat ini) render `kurikulumAsal`/`kurikulumTujuan` sebagai raw value (`k13`, `merdeka`) langsung dari `data-kurikulum`. Tambah data attribute label manusiawi di opsi kelas tujuan (baris `:122-124` saat ini):

```blade
@foreach ($kelasTujuanList as $kelasBaru)
    <option value="{{ $kelasBaru->id }}" data-kurikulum="{{ $kelasBaru->kurikulum?->value }}" data-kurikulum-label="{{ $kelasBaru->kurikulum?->label() }}" data-tingkat="{{ $kelasBaru->tingkat }}">{{ $kelasBaru->nama }}</option>
@endforeach
```

Update `onKelasTujuanChange` (bagian `x-data` per-baris di §2.6) tambah field baru `kurikulumTujuanLabel`, dan `kurikulumAsalLabel` dari server:

```js
kurikulumAsalLabel: {{ Js::from($kelasLama->kurikulum?->label()) }},
kurikulumTujuanLabel: null,
onKelasTujuanChange(event) {
    const opt = event.target.selectedOptions[0];
    this.kurikulumTujuan = opt?.dataset.kurikulum || null;
    this.kurikulumTujuanLabel = opt?.dataset.kurikulumLabel || null;
    this.tingkatTujuan = opt?.dataset.tingkat || null;
},
```

Update teks peringatan (`:127-129` saat ini) pakai label:

```blade
<p x-show="kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal"
   class="mt-1 text-xs font-medium text-amber-600"
   x-text="'⚠ Kurikulum berbeda: kelas asal ' + kurikulumAsalLabel + ', kelas tujuan ' + kurikulumTujuanLabel"></p>
```

**e. Style tabel — samakan shade warna & padding dengan pola dominan proyek**: header `:64` saat ini `bg-gray-100`, ubah jadi `bg-gray-50/50` (pola dominan, mis. modul SDM izin-cuti). Sel `<td>` yang pakai `px-6 py-4` (baris `:93,101,107,119,135`) disamakan jadi `px-5 py-3.5` mengikuti pola dominan proyek. `<th>` yang pakai `px-6 py-3.5` (`:65`) disamakan jadi `px-5 py-3`.

---

## 3. Item Sengaja Tidak Masuk Scope

- **Notifikasi WhatsApp/email ke siswa/orang tua/wali kelas baru setelah kenaikan/kelulusan** — proyek SUDAH punya pola notifikasi established (`PresensiNotificationService`), tapi menambahkannya ke Kenaikan Kelas butuh KEPUTUSAN PRODUK (pesan seperti apa, kapan dikirim, apakah semua pihak atau sebagian) — bukan bug teknis dengan arah perbaikan jelas. Tidak masuk spec ini.
- **Tahap pratinjau (preview) terpisah sebelum eksekusi final**, mirip halaman Import Siswa — diganti dengan pendekatan lebih ringan (confirmDialog dengan ringkasan real-time, §2.6) yang menutup kebutuhan inti (admin tahu apa yang akan terjadi sebelum submit) TANPA membangun route/halaman/state management baru yang jauh lebih besar. Kalau di masa depan ternyata ringkasan confirmDialog dianggap tidak cukup, itu peningkatan terpisah.
- **Validasi server keras (hard block) untuk lompatan tingkat/kurikulum tidak wajar** — audit sengaja TIDAK menambah pemblokiran di `ProsesKenaikanKelasAction` untuk kombinasi tingkat/kurikulum aneh, karena bisa jadi kombinasi itu memang disengaja untuk kasus tertentu (siswa tinggal kelas, pindah kurikulum resmi, dll) — test existing (`KenaikanKelasControllerTest.php:286-307`) SECARA EKSPLISIT membuktikan dan mendokumentasikan keputusan ini ("backend TIDAK PERNAH menolak kombinasi tingkat apapun ... warning hanya di frontend"). Spec ini memperkuat visibilitas warning (§2.6) TANPA mengubah keputusan arsitektur yang sudah didokumentasikan itu.
- **Sticky table header / zebra-striping** — peningkatan kosmetik tambahan di luar apa yang sudah jadi pola dominan proyek saat ini (YAGNI), tidak masuk scope.
- **Row lock (`lockForUpdate()`) untuk mencegah race condition 2 admin bersamaan** — audit menyimpulkan dampak race secara praktis rendah (operasi `WHERE kelas_id = X` bersifat idempotent per baris, `salinJadwal()` sudah cek `sudahAda` sebelum insert). Tidak ada bukti konkret ini pernah jadi masalah nyata, YAGNI untuk saat ini.

---

## 4. Dampak & Kompatibilitas

- **§2.1 adalah perubahan perilaku yang DISENGAJA dan MENDASAR** — sebelumnya siswa lulus lewat halaman ini TETAP AKTIF akunnya (bug); sekarang otomatis dinonaktifkan (benar, sesuai jalur resmi `UpdateStatusSiswaAction`). Kalau ada siswa yang SUDAH terlanjur "lulus" lewat halaman ini sebelum perbaikan (akun masih aktif), perbaikan ini TIDAK otomatis memperbaiki data lama — hanya mencegah kasus baru. Perbaikan data lama (kalau diperlukan) adalah tindakan operasional terpisah di luar scope spec ini (query manual: cari `Siswa` berstatus `Lulus` yang `user.is_active` masih `true`).
- **§2.1 mengubah return type `ProsesKenaikanKelasAction::execute()`** — dari `array{jadwalGagal: array}` jadi `array{jadwalGagal: array, siswaNaik: int, siswaLulus: int, kelasDilewati: int}`. Test existing (`ProsesKenaikanKelasActionTest.php`) yang mengakses `$result['jadwalGagal']` TIDAK terpengaruh (key lama tetap ada, cuma nambah key baru).
- **§2.2 mengubah kondisi render `kelasLamaList`/`kelasTujuanList`/`semesterList`** dari `$tahunAjaranId ? ... : collect()` jadi `($tahunAjaranId && ! $errorTahunAjaran) ? ... : collect()` — test existing yang MEMANG tidak pernah kirim `tahun_ajaran_id`/`tahun_ajaran_tujuan_id` yang sama/lebih lama (dikonfirmasi lewat pembacaan `KenaikanKelasControllerTest.php`/`KenaikanKelasControllerUxTest.php` penuh) TIDAK terpengaruh.
- **§2.6 murni tambahan attribute/binding Alpine di elemen yang SUDAH ADA** — getter `selisihIndexTingkat`/state `kurikulumTujuan` dkk TIDAK diubah, cuma dipakai ulang di binding baru. Test existing yang meng-assert isi `x-data` (`KenaikanKelasControllerUxTest.php`) TIDAK terpengaruh karena teks yang di-assert (`get selisihIndexTingkat()`, `kurikulumAsal`, dst) tetap muncul sama persis di HTML — cuma ADA tambahan di sekitarnya.

---

## 5. Pengujian yang Dibutuhkan

- **§2.1** (paling penting): test baru — proses tindakan `lulus` untuk kelas berisi siswa yang punya `user_id`, assert `$siswa->fresh()->status === StatusSiswa::Lulus`, assert `$siswa->user->fresh()->is_active === false`. Test baru — proses tindakan `naik`, assert `StudentUpdatedClass` event ter-dispatch (`Event::fake()` + `Event::assertDispatched(StudentUpdatedClass::class, fn ($e) => $e->siswa->id === $siswa->id)`), ATAU assert efek sampingnya langsung (tagihan baru muncul, kalau ada `JenisTagihan` yang match — pilih pendekatan mana yang lebih murah/stabil sesuai konvensi test event di proyek ini, cek pola test lain yang meng-assert `StudentUpdatedClass` kalau ada). Test baru — activity log tercatat untuk kedua tindakan (`assertDatabaseHas('activity_log', [...])` atau helper Spatie Activitylog yang sudah dipakai test lain di proyek). Regresi: SEMUA test existing di `ProsesKenaikanKelasActionTest.php` dan `KenaikanKelasControllerTest.php` (termasuk yang mengecek `kelas_terakhir_id`, siswa Keluar tidak ikut naik) WAJIB tetap lolos — return value baru tidak mengubah assertion lama.
- **§2.2**: test baru — GET `index()` dengan `tahun_ajaran_id` = `tahun_ajaran_tujuan_id` yang sama, assert `kelasLamaList`/`kelasTujuanList` kosong DAN `errorTahunAjaran` terisi. Test baru — tujuan lebih lama dari sumber, assert sama. Regresi: seluruh test `KenaikanKelasControllerUxTest.php` yang mengirim `tahun_ajaran_id`+`tahun_ajaran_tujuan_id` valid (beda ID, tujuan lebih baru) WAJIB tetap lolos (tidak ada `errorTahunAjaran`).
- **§2.3**: test baru — assert HTML dropdown mengandung nama lembaga (`assertSee($lembaga->nama)` dalam konteks option tahun ajaran).
- **§2.4**: test baru — submit `kelas_baru_id` yang tidak exists di tabel `kelas`, assert `assertSessionHasErrors('mapping.0.kelas_baru_id')` atau sesuai struktur key validasi Laravel utk nested array. Test baru — submit `salin_jadwal=1` tanpa `semester_tujuan_id`, assert `assertSessionHasErrors(...)` dengan pesan yang mengandung "Salin Jadwal".
- **§2.5**: test baru — assert flash message `status` mengandung angka siswa naik/lulus/kelas dilewati yang benar sesuai skenario test.
- **§2.6, §2.7**: TIDAK ADA automated test untuk perilaku JS Alpine murni maupun style CSS (konsisten pola sesi ini) — verifikasi manual dev-server: submit form memicu `confirmDialog` dengan ringkasan benar, baris berperingatan ter-highlight border amber, tombol submit ter-disable selama proses. KECUALI §2.7.c (opsi "Lewati" selalu tersedia) — tambah 1 assertion di test existing/baru yang mengecek opsi `lewati` tetap ada di HTML untuk kelas BERISI siswa (bukan cuma kelas kosong).
- **Regresi wajib**: seluruh `tests/Unit/Domains/Akademik/Actions/KenaikanKelas`, `tests/Feature/Admin/KenaikanKelasControllerTest.php`, `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php`, `tests/Feature/Akademik/KenaikanKelasIndicatorTest.php` dijalankan ulang setelah SEMUA task selesai, plus full suite proyek di task terakhir.

---

## 6. Struktur Task yang Disarankan (untuk fase plan nanti)

1. **Task 1**: §2.1 (root cause fix — per-siswa update, reuse `UpdateStatusSiswaAction`) — PALING KRITIS, TDD di Action layer. WAJIB SELESAI DULUAN karena Task 5 (ringkasan hasil) bergantung pada return value baru dari task ini.
2. **Task 2**: §2.2 (validasi tahun ajaran sumber vs tujuan di `index()`) — independen.
3. **Task 3**: §2.3 (dropdown tampilkan nama lembaga, sekaligus a11y `for`/`id`) — independen, TAPI menyentuh file/baris yang sama dengan Task 2 (blok picker form) — urutkan Task 2 dulu baru Task 3 untuk hindari konflik commit, walau secara logic keduanya independen.
4. **Task 4**: §2.4 (`exists:kelas,id` + `salin_jadwal` validasi kondisional) — independen.
5. **Task 5**: §2.5 (ringkasan hasil flash message) — BUTUH Task 1 selesai duluan (pakai return value `siswaNaik`/`siswaLulus`/`kelasDilewati`).
6. **Task 6**: §2.6 (confirmDialog + ringkasan real-time + highlight baris peringatan) — PALING BESAR, independen dari task lain tapi menyentuh file yang sama dengan Task 3 & Task 7 (semua di `index.blade.php`) — urutkan setelah Task 3.
7. **Task 7**: §2.7 (wording, penjelasan salin jadwal, opsi lewati, label kurikulum, style tabel) — polish gabungan, urutkan PALING TERAKHIR di antara task frontend (menyentuh banyak baris yang sama dengan Task 2/3/6, paling aman dikerjakan setelah semuanya settle).
8. **Task 8**: Penutup — full regression sweep + Pint + full suite proyek.

Urutan WAJIB: Task 1 → Task 5 (dependency return value). Task 2 → Task 3 → Task 6 → Task 7 (dependency file yang sama, `index.blade.php`, dikerjakan berurutan untuk hindari conflict). Task 4 independen kapan saja. Task 8 wajib terakhir.

---

## 7. Self-Review — Putaran 1 (standar: placeholder, konsistensi, cakupan)

- **Placeholder scan**: tidak ada "TBD"/"TODO" — semua kode di §2 lengkap.
- **Konsistensi**: `ProsesKenaikanKelasAction::execute()` return type baru (`array{jadwalGagal, siswaNaik, siswaLulus, kelasDilewati}`) dipakai KONSISTEN di §2.1 (Action) dan §2.5 (Controller) — nama key sama persis.
- **Cakupan vs audit**: 7 dari 7 kelompok temuan (Critical akar-mekanisme-event, validasi sumber/tujuan, dropdown ambigu, exists+salin-jadwal-senyap, feedback ringkas, tanpa-konfirmasi, wording/a11y/style) masuk §2. 4 item (notifikasi, preview terpisah, hard-block tingkat, row-lock) masuk §3 dengan alasan eksplisit. Tidak ada temuan hilang tanpa penjelasan.

## 8. Self-Review — Putaran 2 (verifikasi empiris terhadap kode & spec lama)

- **Ditemukan & dikoreksi SEBELUM draft final**: draft awal spec ini nyaris mengusulkan `is_active`/event-dispatch manual di `ProsesKenaikanKelasAction` sendiri (duplikasi logic) — dikoreksi setelah membaca ulang `.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md` yang SUDAH menetapkan `UpdateStatusSiswaAction` sebagai satu-satunya jalur resmi. Perbaikan final (§2.1) reuse action itu, BUKAN duplikasi.
- **Dikonfirmasi ulang** lewat pembacaan langsung `Siswa.php:157-161`: event `StudentUpdatedClass` HANYA terpicu di `static::updated()`, yang HANYA jalan lewat `$model->save()`/`$model->update()`, BUKAN Query Builder mass-update — bukan asumsi, dibuktikan lewat baca kode Laravel Eloquent yang relevan + kode aplikasi.
- **Dikonfirmasi ulang** test existing `KenaikanKelasControllerTest.php:286-307` MEMANG secara eksplisit mendokumentasikan keputusan "backend tidak pernah validasi tingkat" — dijadikan dasar §3 (item sengaja di luar scope untuk hard-block tingkat), bukan diabaikan.
- **Dikonfirmasi ulang** `tahun_ajaran` unique per `(lembaga_id, nama)` lewat schema SQL langsung (`tahun_ajaran_lembaga_id_nama_unique`) — dasar temuan §2.3, bukan dugaan.

## 9. Self-Review — Putaran 3 (dependency antar-perbaikan & risiko regresi)

- **Task 1 → Task 5**: dikonfirmasi Task 5 SECARA LITERAL butuh key `siswaNaik`/`siswaLulus`/`kelasDilewati` yang baru diperkenalkan Task 1 — kalau dikerjakan terbalik, Task 5 akan merujuk key yang belum ada. Urutan sudah eksplisit di §6.
- **Task 2/3/6/7 semua menyentuh `index.blade.php`** — diverifikasi baris yang disentuh masing-masing TIDAK saling tumpang tindih PERSIS (Task 2: banner error baru + kondisi render list; Task 3: 2 blok `<select>` picker; Task 6: tag `<form>` + `<tr>` binding baru + tombol submit; Task 7: header kolom + opsi dropdown tindakan + opsi kelas tujuan + teks peringatan + shade warna) — TAPI karena saling berdekatan di file yang sama, urutan commit berurutan (bukan paralel) tetap WAJIB untuk hindari conflict merge, dicatat eksplisit di §6.
- **§2.1 dampak ke performa/test timeout**: dikonfirmasi test existing tidak memakai kelas dengan ratusan siswa (semua test pakai 1-2 siswa per kelas) — perubahan ke per-siswa loop TIDAK akan membuat test existing lambat/timeout.

## 10. Self-Review — Putaran 4 (baca ulang dengan mata segar, cek pesan & wording)

- Pesan error §2.2 ("Tahun Ajaran Sumber dan Tujuan tidak boleh sama...") dan §2.4 ("Anda mencentang 'Salin Jadwal'...") sudah actionable — menyebutkan APA yang salah dan APA yang harus dilakukan, konsisten prinsip proyek.
- Dicek ulang §2.6: variabel baru `kurikulumAsalLabel`/`kurikulumTujuanLabel` (§2.7.d) TIDAK bentrok dengan `kurikulumAsal`/`kurikulumTujuan` (raw value, tetap dipakai untuk LOGIC perbandingan `!==`) — dipisah jadi 2 pasang variabel (value untuk logic, label untuk tampilan), sengaja TIDAK menggantikan variabel value yang sudah dipakai `:data-warning`/`:class` binding di §2.6.
- Dicek ulang §2.7.c: mengubah opsi "Lewati" dari kondisional jadi selalu tampil TIDAK mengubah `@selected(...)` logic untuk opsi `naik`/`lulus` — hanya penambahan/pelonggaran opsi, test existing `'pre-selects Lewati for a kelas that is already empty'` tetap valid (opsi lewati tetap ada & tetap ter-pilih untuk kelas kosong, cuma sekarang JUGA ada untuk kelas berisi meski tidak ter-pilih otomatis).

## 11. Self-Review — Putaran 5 (baca ulang final, cek referensi silang antar-bagian)

- Dicek ulang: §4 "Dampak & Kompatibilitas" MENYEBUTKAN eksplisit bahwa data siswa lama (yang sudah terlanjur "lulus" dengan akun masih aktif sebelum fix) TIDAK otomatis diperbaiki — ini keputusan sadar (scope spec ini adalah mencegah kasus BARU, bukan migrasi data lama) dan harus tetap disebutkan ke user di kickoff supaya tidak jadi kejutan.
- Dicek ulang seluruh §2 sekali lagi baris-per-baris terhadap kode ASLI yang dibaca di awal investigasi (bukan dari memori) — SEMUA kode "current" yang dikutip di §2.1-§2.7 cocok persis dengan isi file yang benar-benar dibaca sebelum spec ini ditulis.
- Dicek ulang §6: total 8 task, urutan dependency dicatat 2 kali secara eksplisit (di struktur task DAN paragraf penutup) — tidak ada ambiguitas urutan untuk fase plan berikutnya.
