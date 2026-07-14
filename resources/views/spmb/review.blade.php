<x-spmb-public-layout :lembaga="$lembaga" title="Review">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Review Data</h2>
        <p class="mt-1 text-sm text-slate">Periksa kembali sebelum mengirim.</p>

        <dl class="mt-5 divide-y divide-ink/10 text-sm">
            <div class="flex justify-between py-2"><dt class="text-slate">Nama Lengkap</dt><dd class="font-medium text-ink">{{ $session['data_pribadi']['nama_lengkap'] ?? '-' }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">NIK</dt><dd class="font-mono text-ink">{{ $session['nik'] ?? '-' }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Email</dt><dd class="text-ink">{{ $session['email_pendaftaran'] ?? '-' }}</dd></div>
        </dl>

        <form method="POST" action="{{ route('spmb.submit', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-6">
            @csrf
            <x-primary-button>Kirim Pendaftaran</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
