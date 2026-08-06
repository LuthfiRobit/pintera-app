{{-- resources/views/mail/tugas-batch-dibuat.blade.php --}}
<p>
    Tugas baru "{{ $judul }}" ({{ ucfirst($frekuensi) }}) telah diberikan — {{ $jumlahBaris }}
    baris tugas, dari {{ $mulaiPada->format('d M Y') }} sampai {{ $batasSelesaiPada->format('d M Y') }}.
</p>
