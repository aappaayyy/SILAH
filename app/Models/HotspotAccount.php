<?php

namespace App\Models;

use App\Enums\WhatsappStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class HotspotAccount extends Model
{
    protected $fillable = [
        'civitas_id','username','mikrotik_id','profile','is_disabled',
        'last_seen_at','last_reset_at','reset_count','expired_at',
    ];

    protected function casts(): array
    {
        return [
            'is_disabled'   => 'boolean',
            'last_seen_at'  => 'datetime',
            'last_reset_at' => 'datetime',
            'expired_at'    => 'datetime',
        ];
    }

    public function civitas(): BelongsTo { return $this->belongsTo(Civitas::class); }
    public function whatsappMessages(): MorphMany { return $this->morphMany(WhatsappMessage::class, 'related'); }

    protected function username(): Attribute
    {
        return Attribute::set(fn (string $v) => strtolower(trim($v)));
    }

    // Pengganti link_status: akun tanpa civitas = belum tertaut
    public function scopeUnlinked(Builder $q): Builder { return $q->whereNull('civitas_id'); }

    public function markResetRequested(): void
    {
        $this->forceFill([
            'last_reset_at' => now(),
            'reset_count'   => $this->reset_count + 1,
        ])->save();
    }

    public function lastWhatsappFailed(): bool
    {
        return $this->whatsappMessages()->latest('id')->value('status') === WhatsappStatus::Failed;
    }
}
