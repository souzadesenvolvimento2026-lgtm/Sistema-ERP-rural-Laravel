<?php

namespace App\Services;

use Illuminate\Http\Request;

final class SystemWriteUnlockService
{
    private const DURATION_SECONDS = 300;
    private const SESSION_EXPIRES_AT = 'system_write_unlocked_until';
    private const SESSION_PROPERTY_ID = 'system_write_unlocked_property_id';

    public function unlock(Request $request, int $propertyId): int
    {
        $expiresAt = time() + self::DURATION_SECONDS;

        $request->session()->put(self::SESSION_EXPIRES_AT, $expiresAt);
        $request->session()->put(self::SESSION_PROPERTY_ID, $propertyId);

        return $expiresAt;
    }

    public function refresh(Request $request, int $propertyId): ?int
    {
        if (! $this->isActiveFor($propertyId)) {
            $this->forget($request);

            return null;
        }

        return $this->unlock($request, $propertyId);
    }

    public function forget(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_EXPIRES_AT,
            self::SESSION_PROPERTY_ID,
        ]);
    }

    public function isActiveFor(?int $propertyId): bool
    {
        if ($propertyId === null || $propertyId <= 0) {
            return false;
        }

        return $this->currentPropertyId() === $propertyId
            && ($this->expiresAt() ?? 0) > time();
    }

    public function currentPropertyId(): ?int
    {
        $propertyId = session(self::SESSION_PROPERTY_ID);

        return $propertyId === null ? null : (int) $propertyId;
    }

    public function expiresAt(): ?int
    {
        $expiresAt = session(self::SESSION_EXPIRES_AT);

        return $expiresAt === null ? null : (int) $expiresAt;
    }
}
