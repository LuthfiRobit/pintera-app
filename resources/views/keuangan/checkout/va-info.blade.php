<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Informasi Virtual Account') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900">Nomor Virtual Account Anda</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Gunakan nomor VA berikut untuk melakukan top-up saldo wallet Anda. Saldo wallet akan otomatis memotong tagihan bulanan.
                    </p>

                    <div class="mt-6 bg-gray-50 px-4 py-5 sm:p-6 rounded-lg border border-gray-200 text-center">
                        <div class="text-3xl font-bold tracking-widest text-indigo-600">
                            {{ $wallet->va_number }}
                        </div>
                        <p class="mt-2 text-sm text-gray-500">A.n. {{ $activeSiswa->nama_lengkap }}</p>
                    </div>

                    <div class="mt-8">
                        <h4 class="text-md font-medium text-gray-900">Instruksi Pembayaran</h4>
                        <ul class="mt-4 list-disc list-inside text-sm text-gray-600 space-y-2">
                            <li>Buka aplikasi BRImo atau ATM BRI.</li>
                            <li>Pilih menu <strong>BRIVA</strong>.</li>
                            <li>Masukkan nomor VA di atas.</li>
                            <li>Masukkan nominal top-up sesuai dengan total tagihan Anda atau lebih.</li>
                            <li>Selesaikan pembayaran. Saldo wallet akan bertambah seketika dan otomatis digunakan untuk membayar tagihan.</li>
                        </ul>
                    </div>
                    
                    <div class="mt-8 flex justify-end">
                        <a href="{{ route('keuangan.tagihan.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Kembali ke Tagihan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
