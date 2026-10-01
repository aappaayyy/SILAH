<?php

namespace App\Models;

use App\Enums\WhatsappStatus;
use App\Enums\WhatsappType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class WhatsappMessage extends Model
{
    protected $fillable = ['uuid','to','type','status','attempts','error','related_type','related_id','sent_at'];

    protected function casts(): array
    {
        return ['type' => WhatsappType::class, 'status' => WhatsappStatus::class, 'sent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function related(): MorphTo { return $this->morphTo(); }
}
