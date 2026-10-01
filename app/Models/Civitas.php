<?php

namespace App\Models;

use App\Enums\Tipe;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Civitas extends Model
{
    protected $table = 'civitas';
    protected $fillable = ['nim_nidn','nama','email','no_wa','tipe','unit','is_active'];

    protected function casts(): array
    {
        return ['tipe' => Tipe::class, 'is_active' => 'boolean'];
    }

    public function hotspotAccount(): HasOne { return $this->hasOne(HotspotAccount::class); }
    public function numberChanges(): HasMany { return $this->hasMany(NumberChange::class); }

    protected function noWa(): Attribute
    {
        return Attribute::set(fn (?string $v) => $v ? Phone::normalize($v) : null);
    }

    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $v) => $v ? mb_strtolower(trim($v)) : null);
    }

    public function scopeAktif(Builder $q): Builder { return $q->where('is_active', true); }

    // accessor namaTersamar & waTersamar sama seperti sebelumnya

    public static function findByIdentifier(string $input): ?self
    {
        $input = trim($input);

        if (str_contains($input, '@')) {
            return static::aktif()->where('email', mb_strtolower($input))->first();
        }

        return static::aktif()->where('nim_nidn', $input)->first()
            ?? static::aktif()->where('no_wa', Phone::normalize($input))->first();
    }
}
