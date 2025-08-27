<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO\Factory;

use OnixSystemsPHP\HyperfScorm\DTO\CreateScormPackageDTO;
use OnixSystemsPHP\HyperfScorm\Request\RequestCreateScormPackage;

/**
 * Factory for creating CreateScormPackageDTO from request
 */
class CreateScormPackageDTOFactory
{
    public static function make(RequestCreateScormPackage $request): CreateScormPackageDTO
    {
        return new CreateScormPackageDTO(
            title: $request->input('title'),
            identifier: $request->input('identifier'),
            manifestPath: $request->input('manifest_path'),
            contentPath: $request->input('content_path'),
            version: $request->input('version'),
            manifestData: $request->input('manifest_data'),
            scormVersion: $request->input('scorm_version'),
        );
    }
}
