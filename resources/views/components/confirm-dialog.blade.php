<div
    x-data
    x-show="$store.confirmDialog.show"
    x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center px-4"
    style="display: none;"
>
    <div
        x-show="$store.confirmDialog.show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900/60"
        @click="$store.confirmDialog.cancel()"
    ></div>

    <div
        x-show="$store.confirmDialog.show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        @keydown.escape.window="$store.confirmDialog.cancel()"
        class="relative w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-elevated"
    >
        <button
            type="button"
            @click="$store.confirmDialog.cancel()"
            class="absolute right-4 top-4 text-gray-400 hover:text-gray-700"
        >
            <span class="text-sm">✕</span>
        </button>

        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border-2 border-gray-200">
            <x-icon name="warning" class="h-7 w-7 text-gray-400" />
        </div>

        <p class="mt-4 font-display text-base font-bold text-gray-900" x-text="$store.confirmDialog.title"></p>
        <p class="mt-1.5 text-sm text-gray-500" x-text="$store.confirmDialog.message"></p>

        <div class="mt-6 flex items-center justify-center gap-3">
            <button
                type="button"
                @click="$store.confirmDialog.confirm()"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-error-600 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-error-500 focus:ring-offset-2"
                x-text="$store.confirmDialog.confirmLabel"
            ></button>
            <button
                type="button"
                @click="$store.confirmDialog.cancel()"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                x-text="$store.confirmDialog.cancelLabel"
            ></button>
        </div>
    </div>
</div>
