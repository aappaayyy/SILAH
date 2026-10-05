<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\Tipe;
use App\Support\Mask;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    protected $fillable = [
        'nim_nidn','nama','email','no_wa','tipe','unit','dokumen_path',
        'status','alasan_tolak','reviewed_by','reviewed_at','civitas_id','ip','user_agent',
    ];

    protected function casts(): array
    {
        return ['tipe' => Tipe::class, 'status' => RequestStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function civitas(): BelongsTo  { return $this->belongsTo(Civitas::class); }

    protected function noWa(): Attribute
    {
        return Attribute::set(fn (string $v) => Phone::normalize($v));
    }

    public function scopePending(Builder $q): Builder { return $q->where('status', RequestStatus::Pending); }

    protected function email(): Attribute
{
    return Attribute::set(fn (?string $v) => $v ? mb_strtolower(trim($v)) : null);
}

protected function namaTersamar(): Attribute
{
    return Attribute::get(fn () => Mask::name($this->nama));
}

protected function waTersamar(): Attribute
{
    return Attribute::get(fn () => Phone::mask($this->no_wa));
}

/** Pengajuan terbaru yang cocok dengan input pengguna (NIM/NIDN, email, atau HP). */
public static function latestFor(string $identifier): ?self
{
    $identifier = trim($identifier);

    return static::query()
        ->where(function ($q) use ($identifier) {
            $q->where('nim_nidn', $identifier)
              ->orWhere('email', mb_strtolower($identifier))
              ->orWhere('no_wa', Phone::normalize($identifier));
        })
        ->latest('id')
        ->first();
}
}
