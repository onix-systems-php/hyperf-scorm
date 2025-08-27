<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Request;


use Hyperf\Validation\Request\FormRequest;

/**
 * Request validation for starting SCORM attempt
 */
class RequestStartScormAttempt extends FormRequest
{
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', 'exists:scorm_packages,id'],
            'user_id' => ['required', 'integer'],
        ];
    }

}
