<x-app-layout>
    <div class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <x-dynamic-component :component="'lucide-hammer'" class="h-12 w-12 text-gray-300" />
        <h1 class="font-display text-xl font-bold text-gray-900">{{ $fitur }} sedang dalam pengembangan</h1>
        <p class="text-sm text-gray-500">Fitur ini akan segera tersedia. Terima kasih atas kesabaran Anda.</p>
    </div>
</x-app-layout>
