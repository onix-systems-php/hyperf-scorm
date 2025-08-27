<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Request;


use Hyperf\Validation\Request\FormRequest;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;

/**
 * Request validation for creating SCORM package
 */
class RequestCreateScormPackage extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'version' => ['sometimes', 'string', 'max:50'],
            'identifier' => ['required', 'string', 'max:255', 'unique:scorm_packages,identifier'],
            'manifest_path' => ['required', 'string', 'max:500'],
            'content_path' => ['required', 'string', 'max:500'],
            'manifest_data' => ['sometimes', 'array'],
            'scorm_version' => ['sometimes', 'string', 'in:' . implode(',', ScormVersionEnum::values())],
        ];
    }
}
