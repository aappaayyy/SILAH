<?php
// app/Services/Hotspot/ResetResult.php
namespace App\Services\Hotspot;

final class ResetResult
{
    public function __construct(
        public readonly ResetOutcome $outcome,
        public readonly ?int $retryAfter = null,   // detik
    ) {}

    public static function of(ResetOutcome $o, ?int $retryAfter = null): self
    {
        return new self($o, $retryAfter);
    }

    public function queued(): bool
    {
        return $this->outcome === ResetOutcome::Queued;
    }
}