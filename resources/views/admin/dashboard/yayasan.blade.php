<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Dashboard Yayasan</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded p-6">
            <h3 class="font-display font-semibold text-ink mb-3">Lihat sebagai lembaga tertentu</h3>

            <ul class="flex flex-wrap gap-3">
                <li>
                    <a
                        href="{{ route('dashboard', ['switch_lembaga' => 'all']) }}"
                        class="inline-block px-4 py-2 rounded border border-slate/30 text-slate hover:bg-paper transition"
                    >
                        Semua Lembaga
                    </a>
                </li>
                @foreach ($lembagaList as $lembaga)
                    <li>
                        <a
                            href="{{ route('dashboard', ['switch_lembaga' => $lembaga->id]) }}"
                            class="inline-block px-4 py-2 rounded bg-brass/10 hover:bg-brass/20 text-ink transition"
                        >
                            <span class="font-display">{{ $lembaga->nama }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>
