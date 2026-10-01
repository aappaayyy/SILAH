<?php

return [
    // Profil untuk akun baru yang dibuat lewat portal
    'default_profile' => env('ROUTEROS_DEFAULT_PROFILE', 'Mahasiswa'),

    // Username bawaan/uji coba yang tidak perlu diimpor
    'ignore_users' => ['default-trial'],
];