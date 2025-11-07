<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * SCORM Player Data Transfer Object.
 */
class ScormPlayerDTO extends AbstractDTO
{
    public int $packageId;

    public string $sessionId;

    public string $sessionToken;

    public string $launchUrl;

    public array $apiConfiguration;

    //    public array $sessionData;

    public string $playerHtml;
}
