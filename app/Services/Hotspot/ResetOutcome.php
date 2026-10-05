<?php
// app/Services/Hotspot/ResetOutcome.php
namespace App\Services\Hotspot;

enum ResetOutcome
{
    case Queued;
    case Cooldown;     // reset baru saja dilakukan
    case TooMany;      // batas harian
    case NoWhatsapp;   // data master tidak punya nomor WA
    case Blocked;      // akun dinonaktifkan/kedaluwarsa/konflik username
    case Busy;         // gagal mendapat lock
}