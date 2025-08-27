<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Request;

use Hyperf\Validation\Request\FormRequest;

/**
 * Request validation for uploading SCORM package
 */
class RequestUploadScormPackage extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
//                'file',
//                'mimetypes:application/zip,application/x-zip-compressed',
//                'max:102400', // 100MB in KB
            ],
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['string', 'max:1000'],
        ];
    }

}
