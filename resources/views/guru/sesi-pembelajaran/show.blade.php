<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">{{ $sesi->kelas->nama }} &middot; {{ $sesi->mataPelajaran?->nama ?? '(tanpa mapel)' }}</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <x-panel>
            <form method="POST" action="{{ route('guru.sesi.update', $sesi) }}" class="space-y-6 p-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-ink">Materi / Jurnal Mengajar</label>
                    <textarea name="materi" rows="3" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">{{ old('materi', $sesi->materi) }}</textarea>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-ink">Presensi Siswa</p>
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($presensiList as $presensi)
                                <tr class="border-b border-ink/10">
                                    <td class="py-2 pr-2 text-ink">{{ $presensi->siswa->nama_lengkap }}</td>
                                    <td class="py-2">
                                        <select name="presensi[{{ $presensi->siswa_id }}]" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                            @foreach (\App\Enums\StatusPresensi::cases() as $status)
                                                <option value="{{ $status->value }}" @selected($presensi->status === $status)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
