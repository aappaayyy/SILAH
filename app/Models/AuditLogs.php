<?php

namespace App\Models;

use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogs extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['actor_type','actor_id','action','subject_type','subject_id','ip','user_agent','meta'];

    protected function casts(): array
    {
        return ['actor_type' => ActorType::class, 'meta' => 'array'];
    }

    public function subject(): MorphTo { return $this->morphTo(); }

    public static function record(string $action, ?Model $subject = null, ?Request $req = null, array $meta = []): void
    {
        $user = auth()->user();

        static::create([
            'actor_type'   => $user ? ActorType::Admin : ($req ? ActorType::Guest : ActorType::System),
            'actor_id'     => $user?->id,
            'action'       => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'ip'           => $req?->ip(),
            'user_agent'   => $req ? Str::limit($req->userAgent() ?? '', 250, '') : null,
            'meta'         => $meta ?: null,
        ]);
    }
}
