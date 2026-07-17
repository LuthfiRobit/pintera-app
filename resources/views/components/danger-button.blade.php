<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-error-600 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-error-500 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
