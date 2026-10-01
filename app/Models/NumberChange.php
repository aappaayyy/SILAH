<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberChange extends Model
{
    protected $fillable = ['civitas_id','no_wa_lama','no_wa_baru','dokumen_path','status','reviewed_by','reviewed_at','ip'];

    protected function casts(): array
    {
        return ['status' => RequestStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function civitas(): BelongsTo  { return $this->belongsTo(Civitas::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
