<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Request;

use Hyperf\Validation\Request\FormRequest;

/**
 * Request validation for uploading SCORM package.
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
            ],
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['string', 'max:1000'],
        ];
    }
}
