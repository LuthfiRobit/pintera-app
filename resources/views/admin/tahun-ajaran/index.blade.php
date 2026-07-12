<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Tahun Ajaran &amp; Semester</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 p-4 bg-signal-green/10 text-signal-green rounded">{{ session('status') }}</div>
        @endif
        @error('semester')
            <div class="mb-4 p-4 bg-signal-red/10 text-signal-red rounded">{{ $message }}</div>
        @enderror

        <a href="{{ route('admin.tahun-ajaran.create') }}" class="inline-block mb-4 px-4 py-2 bg-ink text-white rounded">
            Tambah Tahun Ajaran
        </a>

        @foreach ($tahunAjaranList as $tahunAjaran)
            <div class="bg-white shadow rounded p-4 mb-4">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-ink">
                        {{ $tahunAjaran->nama }}
                        @if ($tahunAjaran->status_aktif)
                            <span class="text-brass font-medium text-sm">(Aktif)</span>
                        @endif
                    </h3>
                    @unless ($tahunAjaran->status_aktif)
                        <form method="POST" action="{{ route('admin.tahun-ajaran.activate', $tahunAjaran) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-ink underline">Aktifkan</button>
                        </form>
                    @endunless
                </div>

                <ul class="mt-2 ml-4 list-disc">
                    @foreach ($tahunAjaran->semester as $semester)
                        <li class="flex justify-between items-center max-w-sm">
                            <span class="text-ink">{{ $semester->nama }} @if ($semester->status_aktif) <span class="text-brass font-medium">(Aktif)</span> @endif</span>
                            @unless ($semester->status_aktif)
                                <form method="POST" action="{{ route('admin.semester.activate', $semester) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-ink underline text-sm">Aktifkan</button>
                                </form>
                            @endunless
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('admin.semester.store') }}" class="mt-3 flex gap-2 items-end">
                    @csrf
                    <input type="hidden" name="tahun_ajaran_id" value="{{ $tahunAjaran->id }}">
                    <select name="nama" class="border border-slate/30 rounded p-1">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                    <input type="number" name="urutan" placeholder="Urutan (1/2)" class="border border-slate/30 rounded p-1 w-32">
                    <input type="text" name="kode_dapodik" placeholder="Kode Dapodik" class="border border-slate/30 rounded p-1 w-32">
                    <input type="date" name="tanggal_mulai" class="border border-slate/30 rounded p-1">
                    <input type="date" name="tanggal_selesai" class="border border-slate/30 rounded p-1">
                    <button type="submit" class="px-3 py-1 bg-ink text-white rounded text-sm">Tambah Semester</button>
                </form>
            </div>
        @endforeach
    </div>
</x-app-layout>
