<?php

namespace App\Enums;

enum WhatsappType: string
{
    case AkunReset = 'akun_reset';
    case AkunBaru = 'akun_baru';
    case PendaftaranDitolak = 'pendaftaran_ditolak';
}
