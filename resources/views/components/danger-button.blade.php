<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 rounded-xl bg-signal-red px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-signal-red/90 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-signal-red focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
