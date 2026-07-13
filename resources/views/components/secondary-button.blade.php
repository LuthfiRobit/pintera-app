<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center gap-2 rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm font-bold text-ink shadow-sm transition hover:bg-paper active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-brass focus:ring-offset-2 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
