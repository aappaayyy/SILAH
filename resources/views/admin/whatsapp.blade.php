@extends('layouts.admin')
@section('title', 'WhatsApp Gateway')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>WhatsApp Gateway</h3>
                <p class="text-subtitle text-muted">Status sesi dan penautan nomor pengirim.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">WhatsApp</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    @error('wa')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <section class="section">
        <div class="row">
            <div class="col-12 col-lg-5">
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Status</h5></div>
                    <div class="card-body">
                        <h3><span id="wa-badge" class="badge bg-secondary">Memeriksa...</span></h3>
                        <p id="wa-desc" class="text-muted mt-2 mb-3"></p>
                        <table class="table table-borderless mb-0">
                            <tr><th style="width:45%">Pesan dalam antrean</th><td id="wa-pending">-</td></tr>
                            <tr><th>Terhubung sejak</th><td id="wa-since">-</td></tr>
                            <tr><th>Terakhir diperiksa</th><td id="wa-checked">-</td></tr>
                        </table>
                    </div>
                </div>

                @if ($canManage)
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.whatsapp.reset') }}"
                              onsubmit="return confirm('Putuskan sesi WhatsApp dan buat QR baru?\nPengiriman akun terhenti sampai nomor ditautkan kembali.')">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100">Reset sesi / ganti nomor</button>
                        </form>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-12 col-lg-7">
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Tautkan WhatsApp</h5></div>
                    <div class="card-body text-center">
                        <div id="qr-box" style="min-height:340px" class="d-flex align-items-center justify-content-center">
                            <p id="qr-msg" class="text-muted mb-0">Memuat...</p>
                            <img id="qr-img" alt="QR WhatsApp" width="320" height="320" hidden>
                        </div>
                        <p id="qr-help" class="small text-muted mt-3 mb-0" hidden>
                            Di ponsel nomor pengirim: <strong>WhatsApp → Perangkat tertaut → Tautkan perangkat</strong>,
                            lalu pindai kode ini. Kode diperbarui otomatis.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const url = @json(route('admin.whatsapp.status'));
  const $ = id => document.getElementById(id);

  const MAP = {
    connected:    ['bg-success', 'Terhubung',        'Gateway siap mengirim akun ke pengguna.'],
    need_qr:      ['bg-warning', 'Menunggu pindai',  'Pindai QR di samping untuk menautkan nomor pengirim.'],
    starting:     ['bg-info',    'Memulai',          'Gateway sedang menyiapkan sesi.'],
    reconnecting: ['bg-info',    'Menyambung ulang', 'Koneksi terputus, gateway mencoba menyambung kembali.'],
    logged_out:   ['bg-danger',  'Sesi keluar',      'Perangkat dikeluarkan dari ponsel. Reset sesi untuk menautkan lagi.'],
    offline:      ['bg-danger',  'Gateway mati',     'Laravel tidak dapat menghubungi gateway. Periksa proses gateway dan WA_URL.'],
  };

  function render(s) {
    const [cls, label, desc] = MAP[s.state] ?? ['bg-secondary', s.state, ''];
    const badge = $('wa-badge');
    badge.className = 'badge ' + cls;
    badge.textContent = label;
    $('wa-desc').textContent = desc;
    $('wa-pending').textContent = s.pending ?? '-';
    $('wa-since').textContent = s.connectedAt ? new Date(s.connectedAt).toLocaleString('id-ID') : '-';
    $('wa-checked').textContent = new Date().toLocaleTimeString('id-ID');

    const img = $('qr-img'), msg = $('qr-msg'), help = $('qr-help');
    if (s.qr) {
      img.src = s.qr; img.hidden = false; msg.hidden = true; help.hidden = false;
    } else {
      img.hidden = true; img.removeAttribute('src'); help.hidden = true; msg.hidden = false;
      msg.textContent = s.state === 'connected'
        ? 'Nomor sudah tertaut. Tidak perlu memindai QR.'
        : s.state === 'need_qr'
          ? 'QR hanya ditampilkan untuk superadmin.'
          : 'QR belum tersedia.';
    }
  }

  async function tick() {
    let s = { state: 'offline' };
    try {
      const r = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (r.redirected || r.status === 401 || r.status === 419) return location.reload(); // sesi login habis
      s = await r.json();
    } catch { /* tetap tampil sebagai offline */ }

    render(s);
    setTimeout(tick, s.state === 'need_qr' || s.state === 'starting' ? 3000 : 10000);
  }

  // Hemat resource: berhenti saat tab tidak terlihat
  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
  tick();
})();
</script>
@endpush