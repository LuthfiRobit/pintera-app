<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl bg-spmb-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-spmb-primary/90 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-spmb-accent focus:ring-offset-2 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
