{{-- allowDynamic sengaja false: pesan exception 500 (uncaught/bug) bisa berisi detail teknis
     internal (query SQL, path file, dst) yang TIDAK BOLEH ditampilkan ke user. --}}
<x-error-page
    code="500"
    icon="server"
    title="Ada Gangguan di Sistem"
    :message="\App\Support\ErrorPageMessage::resolve($exception ?? null, 'Tim kami sedang menangani masalah ini. Silakan coba lagi dalam beberapa saat.', allowDynamic: false)"
/>
