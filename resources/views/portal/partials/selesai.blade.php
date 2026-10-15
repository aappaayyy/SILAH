<div class="panel">
  <div class="banner banner-teal">
    @if (($mode ?? 'kirim') === 'ganti')
      Password baru sedang diterapkan dan aktif dalam sekitar 1 menit. Setelah itu login dengan password yang baru Anda buat.
      @if (($wa ?? '-') !== '-') Konfirmasi dikirim ke WhatsApp <strong>{{ $wa }}</strong>. @endif
    @else
      Akun baru sedang dikirim ke WhatsApp <strong>{{ $wa }}</strong>.
      Biasanya tiba dalam 1 menit. Jika belum masuk, tunggu beberapa menit lalu periksa kembali.
    @endif
  </div>
  <form method="POST" action="{{ route('portal.cancel') }}" class="center">
    @csrf
    <button class="btn-text">Kembali ke awal</button>
  </form>
</div>