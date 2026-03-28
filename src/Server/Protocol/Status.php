<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol;

enum Status: string
{
    /*
     * accepted
     * pending
     * success
     * error
     * ok
     */
    case accepted = 'accepted';
    case rejected = 'rejected';
    case pending = 'pending';
    case success = 'success';
    case error = 'error';
    case ok = 'ok';
    case unknown = 'unknown';
    case undefined = 'undefined';

    public function isValid(): bool
    {
        return $this !== self::unknown && $this !== self::undefined;
    }

    public static function fromString(string $status): self
    {
        return match ($status) {
            'accepted' => self::accepted,
            'rejected' => self::rejected,
            'pending' => self::pending,
            'success' => self::success,
            'error', 'failure' => self::error,
            'ok' => self::ok,
            'undefined' => self::undefined,
            default => self::unknown,
        };
    }

    public static function list(): array
    {
        $currentStatus = self::cases();
        return array_filter($currentStatus, static fn($status) => $status->isValid());
    }

    public static function successValues(): array
    {
        return [
            self::accepted,
            self::success,
            self::ok
        ];
    }

    public function isSuccess(): bool
    {
        return in_array($this, self::successValues(), true);
    }

}
