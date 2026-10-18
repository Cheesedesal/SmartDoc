<?php

namespace App\Enums;

enum RequestStage: string
{
    case RequestReceived = 'Request Received';
    case ForVerification = 'For Verification';
    case ForPreparation = 'For Preparation';
    case ForFinalization = 'For Finalization';
    case ReadyForRelease = 'Ready for Release';
    case Released = 'Released';

    /** Next stage in the automated lifecycle (driven by App\Services\RequestAutomation). */
    public function next(): ?self
    {
        return match ($this) {
            self::ForVerification => self::ForPreparation,
            self::ForPreparation => self::ForFinalization,
            self::ForFinalization => self::ReadyForRelease,
            default => null,
        };
    }

    public function progress(): int
    {
        return match ($this) {
            self::RequestReceived => 0,
            self::ForVerification => 25,
            self::ForPreparation => 50,
            self::ForFinalization => 75,
            self::ReadyForRelease, self::Released => 100,
        };
    }
}
