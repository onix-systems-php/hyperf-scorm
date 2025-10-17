<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfCore\Service\Service;

/**
 * Service for generating unique job identifiers
 * Generates RFC 4122 compliant UUID v4
 */
#[Service]
class ScormJobIdGenerator
{
    /**
     * Generate UUID v4 (RFC 4122 compliant)
     */
    public function generate(): string
    {
        $data = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set variant to 10xx
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
