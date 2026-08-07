<x-guest-layout>
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Verifikasi Email</p>
    <h1 class="mt-1 font-display text-2xl font-semibold text-ink">Satu langkah lagi</h1>
    <p class="mt-2 text-sm text-slate">
        Terima kasih telah mendaftar! Sebelum memulai, bisakah Anda memverifikasi alamat email dengan mengklik tautan yang baru saja kami kirimkan? Jika tidak menerimanya, kami akan dengan senang hati mengirimkan tautan baru.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-4 rounded-lg bg-success-50 p-4 text-sm font-medium text-success-700">
            Tautan verifikasi baru telah dikirim ke alamat email yang Anda berikan saat pendaftaran.
        </div>
    @endif

    <div class="mt-8 flex flex-col gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button class="w-full justify-center py-3 text-base shadow-elevated">
                Kirim Ulang Email Verifikasi
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm font-semibold text-brand-600 hover:text-brand-500 transition-colors">
                Keluar dari Akun (Logout)
            </button>
        </form>
    </div>
</x-guest-layout>
