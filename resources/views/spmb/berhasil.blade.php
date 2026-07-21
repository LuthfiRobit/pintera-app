{{-- resources/views/spmb/berhasil.blade.php --}}
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Pendaftaran Berhasil — Pintera</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased">
        <x-portal-authenticated-navbar active="dashboard" />

        <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-10">
            <div class="flex items-center gap-3 rounded-2xl bg-success-50 p-4 text-success-700">
                <x-icon name="check_circle" class="h-8 w-8 shrink-0" />
                <div>
                    <p class="font-bold">Pendaftaran Berhasil</p>
                    <p class="text-[13px]">Data {{ $pendaftaran->calonMurid->nama_lengkap }} sudah kami terima.</p>
                </div>
            </div>

            <div class="mt-4 rounded-2xl bg-portal-500 p-6 text-center text-white">
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">Kode Pendaftaran Anda</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-widest">{{ $pendaftaran->kode_pendaftaran }}</p>
            </div>

            <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-6 text-center">
                <p class="text-[13px] text-gray-500">Kode ini juga sudah dikirim ke <span class="font-semibold text-gray-900">{{ $pendaftaran->email_pendaftaran }}</span>. Simpan untuk cek status pendaftaran nanti.</p>
            </div>

            <div class="mt-6 text-center">
                <a href="{{ route('portal.dashboard') }}" class="inline-flex items-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Lihat Dashboard
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </a>
            </div>
        </div>

        <x-portal-footer :yayasan="$pendaftaran->lembaga->yayasan ?? null" />
    </body>
</html>
