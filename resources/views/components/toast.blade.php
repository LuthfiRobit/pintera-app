<div
    x-data
    class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2 sm:right-6 sm:top-6"
>
    @if(session()->has('status'))
        <div x-init="$store.toast.push('success', @js(session('status')))"></div>
    @endif
    @if(session()->has('error'))
        <div x-init="$store.toast.push('error', @js(session('error')))"></div>
    @endif

    <template x-for="toast in $store.toast.items" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-x-4 opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-4 opacity-0"
            class="pointer-events-auto flex items-start gap-3 rounded-2xl border bg-white p-4 shadow-elevated"
            :class="toast.type === 'success' ? 'border-success-500/20' : 'border-error-500/20'"
        >
            <span
                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                :class="toast.type === 'success' ? 'bg-success-50 text-success-700' : 'bg-error-50 text-error-700'"
                x-text="toast.type === 'success' ? '✓' : '✕'"
            ></span>
            <p class="flex-1 text-sm text-gray-900" x-text="toast.message"></p>
            <button type="button" class="text-gray-400 hover:text-gray-700" @click="$store.toast.remove(toast.id)">
                <span class="text-sm">✕</span>
            </button>
        </div>
    </template>
</div>
