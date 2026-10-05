<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Admin') - {{ config('app.name') }}</title>
    <link rel="stylesheet" crossorigin href="{{ asset('mazer/assets/compiled/css/app.css') }}">
    <link rel="stylesheet" crossorigin href="{{ asset('mazer/assets/compiled/css/app-dark.css') }}">
    @stack('styles')
</head>

<body>
    <script src="{{ asset('mazer/assets/static/js/initTheme.js') }}"></script>
    <div id="app">
        <div id="sidebar">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header position-relative">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="logo">
                            <a href="{{ route('admin.dashboard') }}" class="fw-bold fs-4">SILAH</a>
                        </div>
                        <div class="theme-toggle d-flex gap-2 align-items-center mt-2">
                            <div class="form-check form-switch fs-6">
                                <input class="form-check-input me-0" type="checkbox" id="toggle-dark"
                                    style="cursor:pointer">
                                <label class="form-check-label"></label>
                            </div>
                        </div>
                        <div class="sidebar-toggler x">
                            <a href="#" class="sidebar-hide d-xl-none d-block"><i
                                    class="bi bi-x bi-middle"></i></a>
                        </div>
                    </div>
                </div>

                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-title">Menu</li>

                        <li class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <a href="{{ route('admin.dashboard') }}" class="sidebar-link">
                                <i class="bi bi-grid-fill"></i><span>Dashboard</span>
                            </a>
                        </li>

                        <li class="sidebar-item {{ request()->routeIs('admin.registrations.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.registrations.index') }}" class="sidebar-link">
                                <i class="bi bi-person-plus-fill"></i><span>Pendaftaran</span>
                            </a>
                        </li>

                        <li class="sidebar-title">Akun</li>
                        <li class="sidebar-item">
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="sidebar-link btn btn-link w-100 text-start border-0">
                                    <i class="bi bi-box-arrow-left"></i><span>Keluar
                                        ({{ auth()->user()->name }})</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
            </header>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @yield('content')

            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start">
                        <p>{{ date('Y') }} &copy; {{ config('app.name') }}</p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="{{ asset('mazer/assets/static/js/components/dark.js') }}"></script>
    <script src="{{ asset('mazer/assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('mazer/assets/compiled/js/app.js') }}"></script>
    @stack('scripts')

    <script>
        // Cegah klik ganda pada tombol aksi (setujui/tolak/keluar)
        document.addEventListener('submit', e => {
            if (e.defaultPrevented) return; // mis. dibatalkan oleh confirm()
            const b = e.target.querySelector('button[type=submit]');
            if (b) setTimeout(() => b.disabled = true, 0);
        });
    </script>
</body>

</html>
