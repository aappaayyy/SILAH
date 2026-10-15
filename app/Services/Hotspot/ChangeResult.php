<?php

namespace App\Services\Hotspot;

final class ChangeResult
{
    public function __construct(
        public readonly ChangeOutcome $outcome,
        public readonly ?int $retryAfter = null,
        public readonly ?int $remaining = null,
    ) {}
}