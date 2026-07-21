<x-layouts.portal-public title="{{ $lembaga->nama }}" active="lembaga" :yayasan="$lembaga->yayasan">
    <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 pt-4 text-[12.5px] text-gray-400 sm:px-6 lg:px-10">
        <a href="{{ route('spmb.welcome') }}">Beranda</a>
        <x-icon name="chevron_right" class="h-2.5 w-2.5" />
        <span class="font-semibold text-portal-500">{{ $lembaga->nama }}</span>
    </div>

    <div class="mx-auto mt-5 grid max-w-7xl gap-5 rounded-[18px] bg-[linear-gradient(160deg,#16324F_0%,#1E3A5F_70%)] p-6 text-white sm:grid-cols-[auto,1fr,auto] sm:items-center sm:p-7 mx-4 sm:mx-6 lg:mx-10">
        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/[.12] text-[16px] font-extrabold">
            {{ $lembaga->bentuk_pendidikan }}
        </span>
        <div>
            <h1 class="text-[21px] font-bold">{{ $lembaga->nama }}</h1>
            <div class="mt-1.5 flex flex-wrap gap-4 text-[12.5px] text-white/70">
                <span class="flex items-center gap-1.5">
                    <x-icon name="location_on" class="h-3.5 w-3.5 shrink-0" />
                    {{ $lembaga->kecamatan }}, {{ $lembaga->kabupaten_kota }}
                </span>
                @if ($lembaga->akreditasi)
                    <span class="flex items-center gap-1.5">
                        <x-icon name="school" class="h-3.5 w-3.5 shrink-0" />
                        Akreditasi {{ $lembaga->akreditasi }}
                    </span>
                @endif
                @if ($tahunAjaranAktif)
                    <span class="flex items-center gap-1.5">
                        <x-icon name="schedule" class="h-3.5 w-3.5 shrink-0" />
                        Tahun Ajaran {{ $tahunAjaranAktif->nama }}
                    </span>
                @endif
            </div>
        </div>
        <div class="text-left sm:text-right">
            @if ($gelombang)
                <span class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11.5px] font-bold">
                    <x-icon name="check_circle" class="h-2.5 w-2.5" /> {{ $gelombang->nama }} Dibuka
                </span>
                <p class="text-[11.5px] text-white/70">Tutup {{ $gelombang->tanggal_tutup->translatedFormat('d F Y') }}</p>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11.5px] font-bold">
                    <x-icon name="hourglass_empty" class="h-2.5 w-2.5" /> Ditutup
                </span>
            @endif
        </div>
    </div>

    <p class="mx-4 mt-3 max-w-7xl text-center text-[12px] sm:mx-6 lg:mx-10">
        <a href="{{ route('spmb.status.form', ['lembagaSlug' => $lembaga->slug]) }}" class="text-gray-500 underline">Cek status pendaftaran di lembaga ini</a>
    </p>

    <div class="mx-4 mt-6 flex gap-3 rounded-[14px] bg-portal-50 p-4 text-[12.5px] leading-[1.6] text-portal-500 sm:mx-6 lg:mx-10">
        <x-icon name="info" class="mt-0.5 h-[15px] w-[15px] shrink-0" />
        <span>Pilih salah satu jalur di bawah untuk mulai mendaftar. Kamu akan diminta membuat akun terlebih dulu — jalur yang kamu pilih di sini otomatis tersimpan sampai pendaftaran pertamamu berhasil dikirim.</span>
    </div>

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-10">
        <div class="mx-auto mb-8 max-w-xl text-center">
            <p class="mb-2.5 text-[11.5px] font-bold uppercase tracking-wide text-portal-500">Jalur Pendaftaran</p>
            <h2 class="text-balance text-[clamp(21px,2.6vw,26px)] font-bold text-gray-900">Pilih Jalur yang Sesuai</h2>
            <p class="mt-2 text-[13.5px] text-gray-500">Setiap jalur punya syarat tes dan biaya pendaftaran yang berbeda.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($jalurList as $item)
                @php $jalur = $item['jalur']; @endphp
                <div class="relative flex flex-col gap-4 rounded-[18px] border p-6 transition hover:-translate-y-[3px] hover:shadow-elevated {{ $item['featured'] ? 'border-portal-500 shadow-elevated' : 'border-gray-200 shadow-card hover:border-[#C9D6E4]' }}">
                    @if ($item['featured'])
                        <span class="absolute -top-2.5 left-6 rounded-full bg-portal-500 px-3 py-1 text-[10.5px] font-bold uppercase tracking-wide text-white">Paling Umum</span>
                    @endif

                    <div>
                        <h3 class="text-[17px] font-bold text-gray-900">{{ $jalur->nama }}</h3>
                        @if ($jalur->deskripsi)
                            <p class="mt-1.5 text-[12.5px] leading-relaxed text-gray-500">{{ $jalur->deskripsi }}</p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between border-t border-dashed border-gray-200 pt-2.5 text-[12.5px]">
                        <span class="flex items-center gap-1.5 text-gray-400">
                            <x-icon name="group" class="h-3.5 w-3.5 shrink-0" /> Kuota
                        </span>
                        <span class="font-bold text-gray-900">{{ $item['kuota'] !== null ? $item['kuota'].' siswa' : 'Belum ada gelombang buka' }}</span>
                    </div>

                    <div class="flex items-center justify-between border-t border-dashed border-gray-200 pt-2.5 text-[12.5px]">
                        <span class="flex items-center gap-1.5 text-gray-400">
                            <x-icon name="fact_check" class="h-3.5 w-3.5 shrink-0" /> Tahap Seleksi
                        </span>
                        @if ($item['tesList']->isNotEmpty())
                            <span class="flex flex-wrap justify-end gap-1.5">
                                @foreach ($item['tesList'] as $seleksi)
                                    <span class="rounded-full bg-portal-50 px-2.5 py-1 text-[11px] font-semibold text-portal-500">{{ $seleksi->jenisTesMaster->nama }}</span>
                                @endforeach
                            </span>
                        @else
                            <span class="font-semibold text-gray-400">Tanpa tes tambahan</span>
                        @endif
                    </div>

                    @php $nominal = $item['nominal']; @endphp
                    <div class="rounded-xl bg-gray-50 p-3.5 text-center">
                        <p class="mb-1 text-[10.5px] uppercase tracking-wide text-gray-400">Biaya Pendaftaran</p>
                        @if ($nominal === null)
                            <p class="flex items-center justify-center gap-1.5 text-[13px] font-bold text-warning-700">
                                <x-icon name="warning" class="h-3.5 w-3.5" /> Menunggu Konfirmasi Admin
                            </p>
                        @elseif ((float) $nominal->nominal === 0.0)
                            <p class="flex items-center justify-center gap-1.5 text-[20px] font-bold text-success-700">
                                <x-icon name="check_circle" class="h-4 w-4" /> Gratis
                            </p>
                        @else
                            <p class="text-[20px] font-bold tabular-nums text-portal-500">Rp{{ number_format($nominal->nominal, 0, ',', '.') }}</p>
                        @endif
                    </div>

                    @if ($gelombang)
                        <form method="POST" action="{{ route('spmb.jalur.daftar', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 px-4 py-3 text-[13px] font-bold text-white transition hover:bg-portal-600">
                                Daftar Jalur Ini
                                <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                            </button>
                        </form>
                    @else
                        <button type="button" disabled class="flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-[10px] bg-gray-200 px-4 py-3 text-[13px] font-bold text-gray-500">
                            Belum Dibuka
                        </button>
                    @endif
                </div>
            @empty
                <p class="col-span-full text-center text-[13px] text-gray-400">Belum ada jalur pendaftaran untuk lembaga ini.</p>
            @endforelse
        </div>
    </section>
</x-layouts.portal-public>
