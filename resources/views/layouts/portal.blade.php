<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body>
<div class="navbar">
  <span class="dot-pulse"></span>
  <div>
    <h1>{{ config('app.name') }}</h1>
    <div class="sub">{{ config('portal.institusi', 'Politeknik / Universitas') }}</div>
  </div>
</div>

<main>
  @yield('content')
</main>

{{-- Cegah klik ganda: tombol dinonaktifkan setelah form dikirim --}}
<script>
document.addEventListener('submit', e => {
  const b = e.target.querySelector('button.btn-primary');
  if (b) setTimeout(() => { b.disabled = true; b.textContent = 'Memproses...'; }, 0);
});
</script>
</body>
</html>