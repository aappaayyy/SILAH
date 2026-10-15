<div class="panel">
    <div class="found-card">
        <div class="av">{{ mb_strtoupper(mb_substr($nama, 0, 1)) }}</div>
        <div>
            <div class="name">{{ $nama }}</div>
            <div class="sub">{{ $tipe }} · WhatsApp {{ $wa }}</div>
        </div>
    </div>

    <p class="hint" style="margin:0 0 14px">
        Akun ditemukan. Password baru dibuat otomatis dan dikirim ke nomor WhatsApp di atas.
        Password lama tidak berlaku lagi setelah Anda menekan tombol berikut.
    </p>

    <form method="POST" action="{{ route('reset.store') }}">
        @csrf
        <button class="btn btn-primary">Kirim akun baru ke WhatsApp</button>
    </form>

    @if ($bisaGanti)
        <details style="margin-top:18px" @if ($errors->has('password_lama') || $errors->has('password')) open @endif>
            <summary style="cursor:pointer;font-size:13px">Saya ingat password lama, ganti sendiri</summary>

            <form method="POST" action="{{ route('password.change') }}" style="margin-top:12px">
                @csrf
                <div class="field">
                    <label>Password lama</label>
                    <input type="password" name="password_lama" maxlength="64" autocomplete="current-password" required>
                    @error('password_lama')
                        <div class="err">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label>Password baru</label>
                    <input type="password" name="password" minlength="8" maxlength="64" autocomplete="new-password"
                        required placeholder="Minimal 8 karakter, huruf dan angka">
                    @error('password')
                        <div class="err">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label>Ulangi password baru</label>
                    <input type="password" name="password_confirmation" maxlength="64" autocomplete="new-password"
                        required>
                </div>
                <button class="btn btn-primary">Ganti password</button>
            </form>
        </details>
    @endif

    <form method="POST" action="{{ route('portal.cancel') }}" class="center">
        @csrf
        <button class="btn-text">Bukan Anda? Cek akun lain</button>
    </form>
</div>
