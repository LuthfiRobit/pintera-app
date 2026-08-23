<x-error-page
    code="403"
    icon="lock"
    title="Akses Dibatasi"
    :message="\App\Support\ErrorPageMessage::resolve($exception ?? null, 'Halaman ini khusus untuk peran tertentu. Kalau menurut Anda ini keliru, hubungi admin sekolah Anda.')"
/>
