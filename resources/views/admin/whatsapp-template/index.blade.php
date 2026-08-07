{{-- resources/views/admin/whatsapp-template/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Template WhatsApp</h1>
                <p class="text-xs text-gray-500 mt-0.5">Edit isi pesan WhatsApp untuk notifikasi otomatis. Kode template tidak dapat diubah.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Template WhatsApp</b>
            </p>
        </div>

        {{-- Compact Statistic Card --}}
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated sm:max-w-xs">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                    <x-icon name="chat" class="h-5 w-5" />
                </span>
                <div>
                    <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Template</p>
                    <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $templateList->count() }}</p>
                </div>
            </div>
            <span class="text-[11px] font-medium text-gray-400">Notifikasi Aktif</span>
        </div>

        <div class="space-y-4">
            @foreach ($templateList as $template)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-icon name="chat" class="h-4 w-4 text-gray-400" />
                            <x-badge tone="brass">{{ $template->kode }}</x-badge>
                        </div>
                    </div>
                    @if ($template->deskripsi)
                        <p class="text-xs text-gray-500">{{ $template->deskripsi }}</p>
                    @endif
                    <form method="POST" action="{{ route('admin.whatsapp-template.update', $template) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <x-input-label value="Isi Template *" />
                            <textarea name="isi_template" rows="4" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('isi_template', $template->isi_template) }}</textarea>
                        </div>
                        <div>
                            <x-input-label value="Deskripsi" />
                            <input type="text" name="deskripsi" value="{{ old('deskripsi', $template->deskripsi) }}" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
