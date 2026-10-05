<div class="panel">
  <div class="found-card">
    <div class="av">{{ mb_strtoupper(mb_substr($nama, 0, 1)) }}</div>
    <div>
      <div class="name">{{ $nama }}</div>
      <div class="sub">{{ $tipe }} · WhatsApp {{ $wa }}</div>
    </div>
  </div>

  <p class="hint" style="margin:0 0 14px">
    Akun ditemukan. Password baru dibuat otomatis dan dikirim ke nomor WhatsApp di atas.
    Password lama tidak berlaku lagi setelah Anda menekan tombol berikut.
  </p>

  <form method="POST" action="{{ route('reset.store') }}">
    @csrf
    <button class="btn btn-primary">Kirim akun baru ke WhatsApp</button>
  </form>

  <form method="POST" action="{{ route('portal.cancel') }}" class="center">
    @csrf
    <button class="btn-text">Bukan Anda? Cek akun lain</button>
  </form>
</div>