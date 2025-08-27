<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO\Factory;

use OnixSystemsPHP\HyperfScorm\DTO\StartScormAttemptDTO;
use OnixSystemsPHP\HyperfScorm\Request\RequestStartScormAttempt;

/**
 * Factory for creating StartScormAttemptDTO from request
 */
class StartScormAttemptDTOFactory
{
    public static function make(RequestStartScormAttempt $request): StartScormAttemptDTO
    {
        return new StartScormAttemptDTO(
            packageId: $request->input('package_id'),
            userId: $request->input('user_id'),
        );
    }
}
