<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tahun Ajaran &amp; Semester</h2>
            </div>
            <x-link-button href="{{ route('admin.tahun-ajaran.create') }}">
                <span class="text-base leading-none">+</span> Tambah Tahun Ajaran
            </x-link-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif
        @error('semester')
            <div class="rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">{{ $message }}</div>
        @enderror

        @foreach ($tahunAjaranList as $tahunAjaran)
            <x-panel>
                <div class="flex items-center justify-between border-b border-ink/10 px-6 py-4">
                    <h3 class="flex items-center gap-2 font-display text-lg font-semibold text-ink">
                        {{ $tahunAjaran->nama }}
                        @if ($tahunAjaran->status_aktif)
                            <x-badge tone="brass">Aktif</x-badge>
                        @endif
                    </h3>
                    @unless ($tahunAjaran->status_aktif)
                        <form method="POST" action="{{ route('admin.tahun-ajaran.activate', $tahunAjaran) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-sm font-medium text-ink hover:text-brass">Aktifkan</button>
                        </form>
                    @endunless
                </div>

                <ul class="divide-y divide-ink/10 px-6">
                    @foreach ($tahunAjaran->semester as $semester)
                        <li class="flex items-center justify-between py-3">
                            <span class="flex items-center gap-2 text-sm text-ink">
                                {{ $semester->nama }}
                                @if ($semester->status_aktif)
                                    <x-badge tone="brass">Aktif</x-badge>
                                @endif
                            </span>
                            @unless ($semester->status_aktif)
                                <form method="POST" action="{{ route('admin.semester.activate', $semester) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-sm font-medium text-ink hover:text-brass">Aktifkan</button>
                                </form>
                            @endunless
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('admin.semester.store') }}" class="flex flex-wrap items-end gap-2 border-t border-ink/10 bg-paper/50 px-6 py-4">
                    @csrf
                    <input type="hidden" name="tahun_ajaran_id" value="{{ $tahunAjaran->id }}">
                    <select name="nama" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                    <input type="number" name="urutan" placeholder="Urutan (1/2)" class="w-32 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    <input type="text" name="kode_dapodik" placeholder="Kode Dapodik" class="w-32 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    <input type="date" name="tanggal_mulai" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    <input type="date" name="tanggal_selesai" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    <button type="submit" class="rounded-xl bg-ink px-3 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Tambah Semester</button>
                </form>
            </x-panel>
        @endforeach
    </div>
</x-app-layout>
