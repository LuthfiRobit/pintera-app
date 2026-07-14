@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-xl border-slate/25 text-sm text-ink shadow-sm focus:border-spmb-accent focus:ring-spmb-accent disabled:bg-spmb-bg disabled:text-slate']) }}>
