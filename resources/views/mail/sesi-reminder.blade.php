{{-- resources/views/mail/sesi-reminder.blade.php --}}
<p>Pengingat: sesi pendampingan Anda dijadwalkan besok, {{ $sesi->dijadwalkan_pada->format('d M Y H:i') }} di {{ $sesi->lokasi_mode }}.</p>
