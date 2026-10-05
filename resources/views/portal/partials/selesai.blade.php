<div class="panel">
  <div class="banner banner-teal">
    Akun baru sedang dikirim ke WhatsApp <strong>{{ $wa }}</strong>.
    Biasanya tiba dalam 1 menit. Jika belum masuk, tunggu beberapa menit lalu periksa kembali.
  </div>
  <form method="POST" action="{{ route('portal.cancel') }}" class="center">
    @csrf
    <button class="btn-text">Kembali ke awal</button>
  </form>
</div>