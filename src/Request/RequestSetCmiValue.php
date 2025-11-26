<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Request;

use Hyperf\Validation\Request\FormRequest;

class RequestSetCmiValue extends FormRequest
{
    public function rules(): array
    {
        return [
            'value' => ['required'],
        ];
    }
}
