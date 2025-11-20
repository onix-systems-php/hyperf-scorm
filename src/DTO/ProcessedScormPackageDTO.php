<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ProcessedScormPackageDTO extends AbstractDTO
{
    public ScormManifestDTO $manifestData;

    public string $contentPath;

    public string $launcher_path;

    public string $domain;
}
