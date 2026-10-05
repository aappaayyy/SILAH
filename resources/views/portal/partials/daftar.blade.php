<div class="panel">
    <div class="banner banner-amber">
        Akun tidak ditemukan. Lengkapi data di bawah ini untuk mendaftar. Admin akan memeriksa sebelum akun
        aktif, lalu username dan password dikirim ke WhatsApp yang Anda isi.
    </div>

    <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data">
        @csrf
        <x-honeypot />
        @error('form')
            <div class="banner banner-amber">{{ $message }}</div>
        @enderror

        <div class="field-row">
            <div class="field">
                <label>Nama lengkap</label>
                <input name="nama" value="{{ old('nama') }}" required maxlength="255" placeholder="cth. Siti Aminah">
                @error('nama')
                    <div class="err">{{ $message }}</div>
                @enderror
            </div>
            <div class="field">
                <label>Peran</label>
                <select name="tipe" id="tipe" required>
                    @foreach (\App\Enums\Tipe::cases() as $t)
                        <option value="{{ $t->value }}" @selected(old('tipe') === $t->value)>
                            {{ $t === \App\Enums\Tipe::Tendik ? 'Tenaga Kependidikan' : $t->value }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label>NIM / NIDN / NIY</label>
                <input name="nim_nidn" value="{{ old('nim_nidn', $prefill['nim_nidn'] ?? '') }}" required
                    maxlength="30">
                @error('nim_nidn')
                    <div class="err">{{ $message }}</div>
                @enderror
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $prefill['email'] ?? '') }}"
                    placeholder="nama@kampus.ac.id">
                @error('email')
                    <div class="err">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label>No. WhatsApp (untuk menerima akun)</label>
                <input name="no_wa" inputmode="tel" value="{{ old('no_wa', $prefill['no_wa'] ?? '') }}" required
                    placeholder="08xxxxxxxxxx">
                @error('no_wa')
                    <div class="err">{{ $message }}</div>
                @enderror
            </div>
            <div class="field">
                <label>Unit / Program studi</label>
                <input name="unit" value="{{ old('unit') }}" maxlength="100">
            </div>
        </div>

        <div class="field" id="dokumen-box"
            style="{{ old('tipe', 'Mahasiswa') === 'Mahasiswa' ? '' : 'display:none' }}">
            <label>Foto KTM (JPG, PNG, atau PDF, maks 2 MB)</label>
            <input type="file" id="dokumen" name="dokumen" accept=".jpg,.jpeg,.png,.pdf">
            @error('dokumen')
                <div class="err">{{ $message }}</div>
            @enderror
        </div>

        <button class="btn btn-primary">Kirim pendaftaran</button>
    </form>

    <form method="POST" action="{{ route('portal.cancel') }}" class="center">
        @csrf
        <button class="btn-text">Cek akun lain</button>
    </form>
</div>
<script>
    (() => {
        const tipe = document.getElementById('tipe');
        const box = document.getElementById('dokumen-box');
        const file = document.getElementById('dokumen');
        const sync = () => {
            const mhs = tipe.value === 'Mahasiswa';
            box.style.display = mhs ? '' : 'none';
            file.required = mhs;
            if (!mhs) file.value = '';
        };
        tipe.addEventListener('change', sync);
        sync();
    })();
</script>
