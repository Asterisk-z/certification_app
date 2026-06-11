<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Revoked = 'revoked';
    case Expired = 'expired';
    case Renewed = 'renewed';
    case Cancelled = 'cancelled';

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Queued, self::Cancelled, self::Revoked],
            self::Queued => [self::Sent, self::Failed, self::Cancelled, self::Revoked],
            self::Sent => [self::Expired, self::Revoked, self::Renewed, self::Cancelled],
            self::Failed => [self::Queued, self::Cancelled, self::Revoked],
            self::Revoked => [self::Sent, self::Cancelled],
            self::Expired => [self::Renewed, self::Revoked, self::Cancelled],
            self::Renewed => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
