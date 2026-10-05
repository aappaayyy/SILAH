@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<div class="page-heading">
    <div class="page-title"><h3>Dashboard</h3></div>
    <section class="section">
        <div class="row">
            <div class="col-12 col-md-4">
                <div class="card"><div class="card-body">
                    <h6 class="text-muted">Pendaftaran menunggu</h6>
                    <h3>{{ $pending }}</h3>
                    <a href="{{ route('admin.registrations.index') }}">Tinjau</a>
                </div></div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card"><div class="card-body">
                    <h6 class="text-muted">Akun hotspot belum tertaut</h6>
                    <h3>{{ $unlinked }}</h3>
                </div></div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card"><div class="card-body">
                    <h6 class="text-muted">WhatsApp gagal (24 jam)</h6>
                    <h3 class="{{ $waFailed ? 'text-danger' : '' }}">{{ $waFailed }}</h3>
                </div></div>
            </div>
        </div>
    </section>
</div>
@endsection