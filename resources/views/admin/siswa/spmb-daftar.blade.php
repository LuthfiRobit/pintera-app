<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Daftarkan Siswa dari SPMB</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        @if ($errors->any())
            <div class="rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-panel>
            <form method="POST" action="{{ route('admin.siswa.spmb-daftar.store') }}" class="p-6">
                @csrf

                <div class="mb-4 flex items-center gap-3">
                    <label class="text-sm font-medium text-ink">Kelas Tujuan</label>
                    <select name="kelas_id" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" required>
                        <option value="">— Pilih Kelas —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 text-left text-xs uppercase tracking-wide text-ink/60">
                            <th class="py-2 pr-2"></th>
                            <th class="py-2 pr-2">Nama</th>
                            <th class="py-2 pr-2">Jalur / Gelombang</th>
                            <th class="py-2 pr-2">NIS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendaftaranList as $pendaftaran)
                            <tr class="border-b border-ink/10">
                                <td class="py-2 pr-2">
                                    <input type="checkbox" name="pendaftaran_ids[]" value="{{ $pendaftaran->id }}">
                                </td>
                                <td class="py-2 pr-2 text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }}</td>
                                <td class="py-2 pr-2 text-ink/70">{{ $pendaftaran->jalurPpdb->nama }} / {{ $pendaftaran->gelombangPpdb->nama }}</td>
                                <td class="py-2 pr-2">
                                    <input type="text" name="nis[{{ $pendaftaran->id }}]" value="{{ $nisSaran }}" class="w-32 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-ink/60">Tidak ada pendaftaran yang siap didaftarkan sebagai siswa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($pendaftaranList->isNotEmpty())
                    <button type="submit" class="mt-4 rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Daftarkan sebagai Siswa</button>
                @endif
            </form>
        </x-panel>
    </div>
</x-app-layout>
