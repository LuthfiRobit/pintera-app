<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 rounded-xl bg-ink px-4 py-2.5 text-sm font-bold text-paper shadow-sm transition hover:bg-ink/90 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-brass focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
