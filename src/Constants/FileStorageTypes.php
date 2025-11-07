<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

#[Constants]
class FileStorageTypes extends AbstractConstants
{
    public const LOCAL = 'local';

    public const S3 = 's3';

    public const SCORM_S3 = 'scormS3';

    public const TMP = 'tmp';

    public const ALL = [
        self::LOCAL,
        self::S3,
        self::SCORM_S3,
        self::TMP,
    ];
}
