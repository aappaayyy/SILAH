{{-- resources/views/portal/partials/menunggu.blade.php --}}
@php
    $isPending  = $reg->status === \App\Enums\RequestStatus::Pending;
    $isRejected = $reg->status === \App\Enums\RequestStatus::Rejected;
@endphp

<div class="panel">
  @if ($isPending)
    <div class="banner banner-amber">
      Pendaftaran Anda sudah kami terima dan <strong>menunggu verifikasi admin</strong>.
      Akun dikirim ke WhatsApp setelah disetujui.
    </div>
  @elseif ($isRejected)
    <div class="banner banner-amber">
      Pendaftaran Anda <strong>ditolak</strong>.
      @if ($reg->alasan_tolak)<br>Alasan: {{ $reg->alasan_tolak }}@endif
    </div>
  @endif

  <div class="found-card">
    <div class="av">{{ mb_strtoupper(mb_substr($reg->nama, 0, 1)) }}</div>
    <div>
      <div class="name">{{ $reg->nama_tersamar }}</div>
      <div class="sub">{{ $reg->tipe->value }} · WhatsApp {{ $reg->wa_tersamar }}</div>
    </div>
  </div>

  <table style="width:100%;font-size:13px;margin-bottom:6px">
    <tr>
      <td style="color:var(--ink-soft);padding:3px 0">Diajukan</td>
      <td style="text-align:right">{{ $reg->created_at->translatedFormat('d M Y, H:i') }}</td>
    </tr>
    <tr>
      <td style="color:var(--ink-soft);padding:3px 0">Status</td>
      <td style="text-align:right">{{ $isPending ? 'Menunggu verifikasi' : 'Ditolak' }}</td>
    </tr>
    @if ($isPending && !empty($antrean))
    <tr>
      <td style="color:var(--ink-soft);padding:3px 0">Antrean</td>
      <td style="text-align:right">ke-{{ $antrean }}</td>
    </tr>
    @endif
  </table>

  @if ($isRejected)
    <form method="POST" action="{{ route('portal.reapply') }}" style="margin-top:14px">
      @csrf
      <button class="btn btn-primary">Ajukan ulang</button>
    </form>
  @endif

  <form method="POST" action="{{ route('portal.cancel') }}" class="center">
    @csrf
    <button class="btn-text">Cek akun lain</button>
  </form>
</div>