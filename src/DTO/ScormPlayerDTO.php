<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * SCORM Player Data Transfer Object
 */
class ScormPlayerDTO extends AbstractDTO
{

/**
     * @param array $data
     *   - packageId: int
     *   - sessionId: string
     *   - contentUrl: string
     *   - apiConfiguration: array
     *   - sessionData: array
     *   - playerHtml: string
     */

    public int $packageId;
    public string $sessionId;
    public string $contentUrl;
    public array $apiConfiguration;
    public array $sessionData;
    public string $playerHtml;
}
