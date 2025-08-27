<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Request;


use Hyperf\Validation\Request\FormRequest;

/**
 * Request validation for setting CMI value
 */
class RequestSetCmiValue extends FormRequest
{
    public function rules(): array
    {
        return [
            'value' => ['required'],
        ];
    }
}
