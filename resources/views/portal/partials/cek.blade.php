<div class="page-head">
  <h2>Cek akun hotspot</h2>
  <p class="lede">Masukkan NIM, NIDN, NIY, email, atau nomor HP untuk mendaftar akun baru atau mengatur ulang akun Anda.</p>
</div>
@if (session('info'))
  <div class="banner banner-teal">{{ session('info') }}</div>
@endif
<form method="POST" action="{{ route('check') }}" class="panel">
  @csrf
  <x-honeypot />
  <div class="field">
    <label for="identifier">NIM / NIDN / NIY / Email / No. HP</label>
    <input id="identifier" name="identifier" value="{{ old('identifier') }}" required autofocus
           autocomplete="off" placeholder="cth. 2210513021 atau nama@kampus.ac.id">
  </div>
  @error('identifier')<div class="err" style="margin-bottom:10px">{{ $message }}</div>@enderror
  <button class="btn btn-primary">Cek akun</button>
</form>