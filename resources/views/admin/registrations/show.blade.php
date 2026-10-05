@extends('layouts.admin')
@use('App\Enums\RequestStatus')
@use('App\Enums\Tipe')
@section('title', 'Tinjau Pengajuan')

@section('content')
@php($pending = $reg->status === RequestStatus::Pending)
@php($mhs = $reg->tipe === Tipe::Mahasiswa)

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last"><h3>Tinjau Pengajuan</h3></div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.registrations.index') }}">Pendaftaran</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $reg->nama }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    @error('form')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @if ($conflict)
        <div class="alert alert-warning">
            Data (NIM/NIDN, WhatsApp, atau email) sudah ada di data master. Pengajuan ini tidak dapat disetujui.
        </div>
    @endif

    <section class="section">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Data pemohon</h5></div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><th style="width:35%">Nama</th><td>{{ $reg->nama }}</td></tr>
                            <tr><th>Peran</th><td>{{ $reg->tipe->value }}</td></tr>
                            <tr><th>NIM/NIDN</th><td>{{ $reg->nim_nidn }}</td></tr>
                            <tr><th>Email</th><td>{{ $reg->email ?? '-' }}</td></tr>
                            <tr><th>WhatsApp</th><td>{{ $reg->no_wa }}</td></tr>
                            <tr><th>Unit</th><td>{{ $reg->unit ?? '-' }}</td></tr>
                            <tr><th>Diajukan</th><td>{{ $reg->created_at->translatedFormat('d M Y H:i') }} ({{ $reg->ip }})</td></tr>
                            <tr><th>Status</th><td>{{ $reg->status->value }}</td></tr>
                            @unless ($pending)
                                <tr><th>Diproses</th><td>{{ $reg->reviewer?->name ?? '-' }}, {{ $reg->reviewed_at?->translatedFormat('d M Y H:i') }}</td></tr>
                                @if ($reg->alasan_tolak)<tr><th>Alasan</th><td>{{ $reg->alasan_tolak }}</td></tr>@endif
                            @endunless
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                @if ($reg->dokumen_path)
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Foto KTM</h5></div>
                    <div class="card-body">
                        @if (str_ends_with($reg->dokumen_path, '.pdf'))
                            <a href="{{ route('admin.registrations.document', $reg) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Buka PDF</a>
                        @else
                            <img src="{{ route('admin.registrations.document', $reg) }}" alt="KTM {{ $reg->nama }}" class="img-fluid rounded">
                        @endif
                    </div>
                </div>
                @endif

                @if ($pending && ! $conflict)
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Keputusan</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.registrations.approve', $reg) }}" class="mb-4">
                            @csrf
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="verified" value="1" id="verified" required>
                                <label class="form-check-label" for="verified">
                                    {{ $mhs
                                        ? 'KTM sudah saya cocokkan dengan data SIAKAD (nama dan NIM sesuai).'
                                        : 'NIDN/NIY dan nama sudah saya cocokkan dengan data kepegawaian.' }}
                                </label>
                                @error('verified')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-success w-100">Setujui dan kirim akun ke WhatsApp</button>
                        </form>

                        <form method="POST" action="{{ route('admin.registrations.reject', $reg) }}"
                              onsubmit="return confirm('Tolak pengajuan ini?')">
                            @csrf
                            <label class="form-label" for="alasan_tolak">Alasan penolakan</label>
                            <textarea id="alasan_tolak" name="alasan_tolak" rows="2" maxlength="200" required
                                      class="form-control @error('alasan_tolak') is-invalid @enderror"
                                      placeholder="cth. Foto KTM tidak terbaca">{{ old('alasan_tolak') }}</textarea>
                            @error('alasan_tolak')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text mb-2">Alasan ini dilihat pemohon di portal dan dikirim lewat WhatsApp. Jangan menulis data pribadi.</div>
                            <button type="submit" class="btn btn-outline-danger w-100">Tolak pengajuan</button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection