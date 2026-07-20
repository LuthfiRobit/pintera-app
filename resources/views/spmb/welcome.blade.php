<x-layouts.portal-public title="Selamat Datang" active="beranda" :yayasan="$yayasan">
    <header class="border-b border-gray-200 bg-gradient-to-br from-gray-50 via-white to-portal-50/40">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-2 lg:px-10 lg:py-16">
            <div>
                <span class="mb-5 inline-flex items-center gap-2 rounded-full bg-portal-50 px-3.5 py-1.5 text-[11.5px] font-bold uppercase tracking-wide text-portal-500">
                    <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                    Penerimaan Siswa Baru {{ now()->year }}/{{ now()->year + 1 }}
                </span>
                <h1 class="text-balance text-3xl font-bold leading-tight text-gray-900 sm:text-4xl">
                    Satu Akun untuk <span class="text-portal-500">Semua Pendaftaran</span> di {{ $yayasan?->nama ?? 'Yayasan' }}
                </h1>
                <p class="mt-4 max-w-md text-[14.5px] leading-relaxed text-gray-500">
                    Pilih lembaga, buat akun, dan ikuti seluruh proses pendaftaran — data diri, dokumen, hingga pembayaran — dalam satu portal yang bisa dipantau kapan saja.
                </p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="#lembaga" class="rounded-lg bg-portal-500 px-6 py-3.5 text-[13.5px] font-semibold text-white">Lihat Lembaga &amp; Jalur</a>
                    <a href="#lembaga" class="rounded-lg border border-gray-300 bg-white px-6 py-3.5 text-[13.5px] font-semibold text-portal-500">Cek Status Pendaftaran</a>
                </div>
            </div>

            <div class="rounded-[20px] bg-gradient-to-br from-portal-600 to-portal-500 p-7 text-white shadow-elevated">
                <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-portal-100">Ringkasan Penerimaan</p>
                <p class="mb-5 text-lg font-bold">Tahun Ajaran {{ now()->year }}/{{ now()->year + 1 }}</p>
                <div class="mb-5 grid grid-cols-3 gap-2.5">
                    <div class="rounded-xl border border-white/15 bg-white/10 p-3.5 text-center">
                        <p class="text-[22px] font-bold tabular-nums">{{ $jumlahLembaga }}</p>
                        <p class="text-[10.5px] uppercase text-portal-100">Lembaga</p>
                    </div>
                    <div class="rounded-xl border border-white/15 bg-white/10 p-3.5 text-center">
                        <p class="text-[22px] font-bold tabular-nums">{{ $jumlahSedangBuka }}</p>
                        <p class="text-[10.5px] uppercase text-portal-100">Sedang Buka</p>
                    </div>
                    <div class="rounded-xl border border-white/15 bg-white/10 p-3.5 text-center">
                        <p class="text-[22px] font-bold tabular-nums">{{ $jumlahJalurAktif }}</p>
                        <p class="text-[10.5px] uppercase text-portal-100">Jalur Aktif</p>
                    </div>
                </div>
                @if ($gelombangTerdekat)
                    <div class="mb-5 rounded-xl border border-white/15 bg-white/10 p-3.5 text-[11.5px] leading-relaxed text-portal-100">
                        <span class="font-bold text-white">{{ $gelombangTerdekat->lembaga->nama }}</span> — {{ $gelombangTerdekat->nama }} tutup {{ $gelombangTerdekat->tanggal_tutup->translatedFormat('d F Y') }}
                    </div>
                @endif
                <a href="#lembaga" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-white px-4 py-3.5 text-[13.5px] font-bold text-portal-500">
                    Mulai Pendaftaran
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </a>
            </div>
        </div>
    </header>

    <section id="lembaga" x-data="{ jenjang: 'semua' }" class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-10">
        <div class="mx-auto mb-8 max-w-xl text-center">
            <p class="mb-2.5 text-[11.5px] font-bold uppercase tracking-wide text-portal-500">Lembaga Pendidikan</p>
            <h2 class="text-2xl font-bold text-gray-900">Pilih Lembaga Tujuanmu</h2>
            <p class="mt-2 text-[13.5px] text-gray-500">Setiap lembaga punya jalur, biaya, dan jadwal seleksi masing-masing.</p>
        </div>

        <div class="mb-7 flex flex-wrap justify-center gap-2">
            <button
                type="button"
                @click="jenjang = 'semua'"
                :class="jenjang === 'semua' ? 'bg-portal-500 text-white' : 'border border-gray-200 bg-white text-gray-500'"
                class="rounded-full px-4 py-2 text-[12.5px] font-semibold transition"
            >Semua</button>
            @foreach ($jenjangList as $jenjangItem)
                <button
                    type="button"
                    @click="jenjang = '{{ $jenjangItem }}'"
                    :class="jenjang === '{{ $jenjangItem }}' ? 'bg-portal-500 text-white' : 'border border-gray-200 bg-white text-gray-500'"
                    class="rounded-full px-4 py-2 text-[12.5px] font-semibold transition"
                >{{ $jenjangItem }}</button>
            @endforeach
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($lembagaList as $item)
                @php $lembaga = $item['lembaga']; @endphp
                <a
                    x-show="jenjang === 'semua' || jenjang === '{{ $lembaga->bentuk_pendidikan }}'"
                    href="{{ route('spmb.index', ['lembagaSlug' => $lembaga->slug]) }}"
                    class="flex flex-col gap-3.5 rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition hover:-translate-y-0.5 hover:shadow-elevated {{ $item['gelombang'] ? '' : 'opacity-70' }}"
                >
                    <div class="flex items-start justify-between gap-2.5">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-portal-50 text-[13px] font-extrabold text-portal-500">
                            {{ $lembaga->bentuk_pendidikan }}
                        </span>
                        @if ($item['gelombang'])
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-[11.5px] font-bold text-success-700">
                                <x-icon name="check_circle" class="h-2.5 w-2.5" /> Dibuka
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-[11.5px] font-bold text-gray-500">
                                <x-icon name="hourglass_empty" class="h-2.5 w-2.5" /> Ditutup
                            </span>
                        @endif
                    </div>
                    <div>
                        <p class="text-[16px] font-bold text-gray-900">{{ $lembaga->nama }}</p>
                        <p class="text-[12px] text-gray-400">{{ $lembaga->kecamatan }}, {{ $lembaga->kabupaten_kota }}</p>
                    </div>
                    <div class="flex gap-4 border-y border-dashed border-gray-200 py-3">
                        <div class="flex-1">
                            <p class="text-[13px] font-bold text-portal-500">{{ $item['jalurAktifCount'] }}</p>
                            <p class="text-[10.5px] uppercase text-gray-400">Jalur</p>
                        </div>
                        <div class="flex-1">
                            <p class="text-[13px] font-bold text-portal-500">
                                {{ $item['biayaTermurah'] !== null ? 'Rp'.number_format($item['biayaTermurah'], 0, ',', '.') : '—' }}
                            </p>
                            <p class="text-[10.5px] uppercase text-gray-400">Biaya Daftar</p>
                        </div>
                        <div class="flex-1">
                            <p class="text-[13px] font-bold text-portal-500">{{ $item['gelombang']?->tanggal_tutup->translatedFormat('d M') ?? '—' }}</p>
                            <p class="text-[10.5px] uppercase text-gray-400">Tutup</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-[13px] font-bold text-portal-500">
                        Lihat Jalur <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section id="alur" class="bg-gray-100 px-4 py-12 sm:px-6 lg:px-10">
        <div class="mx-auto mb-8 max-w-xl text-center">
            <p class="mb-2.5 text-[11.5px] font-bold uppercase tracking-wide text-portal-500">Cara Kerja</p>
            <h2 class="text-2xl font-bold text-gray-900">Alur Pendaftaran</h2>
            <p class="mt-2 text-[13.5px] text-gray-500">Empat langkah dari daftar akun sampai menunggu hasil seleksi.</p>
        </div>
        <div class="mx-auto grid max-w-7xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Daftar Akun', 'Buat akun dengan email & password, verifikasi lewat kode yang dikirim.'],
                ['Isi Data & Dokumen', 'Lengkapi data diri, formulir jalur, dan unggah berkas yang disyaratkan.'],
                ['Bayar Biaya Daftar', 'Bayar biaya pendaftaran dan unggah bukti transfer.'],
                ['Ikuti Seleksi', 'Pantau jadwal tes dan hasil seleksi langsung dari dashboard.'],
            ] as $index => [$judul, $deskripsi])
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <span class="mb-3.5 flex h-8 w-8 items-center justify-center rounded-lg bg-portal-500 text-[13px] font-bold text-white">{{ $index + 1 }}</span>
                    <h4 class="mb-1.5 text-[14px] font-bold text-gray-900">{{ $judul }}</h4>
                    <p class="text-[12px] leading-relaxed text-gray-400">{{ $deskripsi }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.portal-public>
