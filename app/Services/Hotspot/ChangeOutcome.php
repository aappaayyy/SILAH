<?php

namespace App\Services\Hotspot;

enum ChangeOutcome
{
    case Queued;
    case Wrong;         // password lama salah
    case Locked;        // terlalu banyak salah
    case Unavailable;   // akun belum punya hash (hasil impor MikroTik)
    case Blocked;       // dinonaktifkan / kedaluwarsa
    case Busy;
}