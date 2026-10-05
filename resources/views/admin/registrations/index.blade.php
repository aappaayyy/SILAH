@extends('layouts.admin')
@use('App\Enums\RequestStatus')
@section('title', 'Pendaftaran')

@push('styles')
<link rel="stylesheet" href="{{ asset('mazer/assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" crossorigin href="{{ asset('mazer/assets/compiled/css/table-datatable-jquery.css') }}">
@endpush

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Pendaftaran Akun</h3>
                <p class="text-subtitle text-muted">Verifikasi pengajuan sebelum akun dikirim ke WhatsApp.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Pendaftaran</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-pills">
                    @foreach (RequestStatus::cases() as $s)
                        <li class="nav-item">
                            <a class="nav-link {{ $s === $status ? 'active' : '' }}"
                               href="{{ route('admin.registrations.index', ['status' => $s->value]) }}">
                                {{ ['Pending' => 'Menunggu', 'Approved' => 'Disetujui', 'Rejected' => 'Ditolak'][$s->value] }}
                                <span class="badge bg-secondary">{{ $counts[$s->value] ?? 0 }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" id="table1">
                        <thead>
                            <tr>
                                <th>Diajukan</th>
                                <th>Nama</th>
                                <th>NIM/NIDN</th>
                                <th>Peran</th>
                                <th>WhatsApp</th>
                                <th>KTM</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                            <tr>
                                <td data-order="{{ $r->created_at->timestamp }}">{{ $r->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ $r->nama }}</td>
                                <td>{{ $r->nim_nidn }}</td>
                                <td>{{ $r->tipe->value }}</td>
                                <td>{{ $r->no_wa }}</td>
                                <td>
                                    @if ($r->dokumen_path)
                                        <span class="badge bg-info">Ada</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ match ($r->status) {
                                        RequestStatus::Pending  => 'bg-warning',
                                        RequestStatus::Approved => 'bg-success',
                                        RequestStatus::Rejected => 'bg-danger',
                                    } }}">{{ $r->status->value }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.registrations.show', $r) }}" class="btn btn-sm btn-primary">Tinjau</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('mazer/assets/extensions/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('mazer/assets/extensions/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('mazer/assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js') }}"></script>
<script>
$(function () {
    $('#table1').DataTable({
        order: [[0, 'asc']],               // yang paling lama diajukan di atas
        pageLength: 25,
        columnDefs: [{ orderable: false, searchable: false, targets: [5, 7] }],
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            zeroRecords: 'Data tidak ditemukan',
            emptyTable: 'Belum ada pengajuan',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
        }
    });
});
</script>
@endpush