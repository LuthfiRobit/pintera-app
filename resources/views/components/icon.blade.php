{{-- Ikon SVG inline, pengganti font Material Symbols. `name` = nama Material Symbol lama yang digantikan. --}}
@props(['name'])

@switch($name)
    @case('menu')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" {{ $attributes }}><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        @break

    @case('expand_more')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M6 9l6 6 6-6"/></svg>
        @break

    @case('dashboard')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        @break

    @case('apartment')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M4 21V7l8-4 8 4v14"/><path d="M9 21v-6h6v6"/><path d="M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/></svg>
        @break

    @case('school')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M22 9 12 5 2 9l10 4 10-4Z"/><path d="M6 11v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/></svg>
        @break

    @case('calendar_month')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 3v3M16 3v3"/></svg>
        @break

    @case('waves')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M2 12c1.5-2 3.5-2 5 0s3.5 2 5 0 3.5-2 5 0 3.5 2 5 0"/><path d="M2 18c1.5-2 3.5-2 5 0s3.5 2 5 0 3.5-2 5 0 3.5 2 5 0"/></svg>
        @break

    @case('signpost')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M12 3v4"/><path d="M4 7h9l2 2-2 2H4V7Z"/><path d="M12 12v9"/></svg>
        @break

    @case('quiz')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9a3 3 0 0 1 5.5-1.7c.6.8.5 2-.3 2.7l-1.7 1.5c-.4.3-.5.6-.5 1.2"/><path d="M12 16h.01"/></svg>
        @break

    @case('fact_check')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="6" y="3" width="12" height="4" rx="1"/><path d="M6 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1"/><path d="M9 13l2 2 4-4"/></svg>
        @break

    @case('payments')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="2" y="6" width="14" height="10" rx="2"/><circle cx="9" cy="11" r="2.5"/><path d="M20 8v9a2 2 0 0 1-2 2H6"/></svg>
        @break

    @case('receipt_long')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M6 2h9l3 3v17l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5V2Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
        @break

    @case('group')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M17 8.5a3 3 0 1 1 0 5.8M21.5 20c0-2.8-1.9-4.9-4.5-5.6"/></svg>
        @break

    @case('groups')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="7" cy="8" r="3"/><circle cx="17" cy="8" r="3"/><path d="M1 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M11 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>
        @break

    @case('shield_person')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M12 2 4 5v6c0 5 3.4 8.5 8 11 4.6-2.5 8-6 8-11V5l-8-3Z"/><circle cx="12" cy="10" r="2.3"/><path d="M8.5 16c.7-2 2-3 3.5-3s2.8 1 3.5 3"/></svg>
        @break

    @case('check_circle')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.3 2.3L16 10"/></svg>
        @break

    @case('cancel')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>
        @break

    @case('hourglass_empty')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M7 3h10M7 21h10"/><path d="M7 3c0 4.5 4 5.5 5 6-1 .5-5 1.5-5 6v6M17 3c0 4.5-4 5.5-5 6 1 .5 5 1.5 5 6v6"/></svg>
        @break

    @case('pending_actions')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="4" y="4" width="12" height="17" rx="2"/><path d="M7 8h6M7 12h4"/><circle cx="17.5" cy="16.5" r="4.5"/><path d="M17.5 14.5v2l1.3 1.3"/></svg>
        @break
@endswitch
