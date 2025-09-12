<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Request;

use Hyperf\Validation\Request\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RequestUploadScorm',
    required: ['scorm_file'],
    properties: [
        new OA\Property(
            property: 'scorm_file',
            type: 'string',
            format: 'binary',
            description: 'SCORM package ZIP file (max 600MB)'
        ),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            description: 'Optional metadata for the SCORM package',
            additionalProperties: true,
            example: [
                'category' => 'training',
                'department' => 'hr',
                'version' => '1.0'
            ]
        ),
    ]
)]
class RequestUploadScorm extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'scorm_file' => [
                'required',
                'file',
                'mimes:zip',
                'max:614400', // 600MB in KB
            ],
            'metadata' => [
                'sometimes',
                'array',
            ],
            'metadata.*' => [
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'scorm_file.required' => 'SCORM package file is required.',
            'scorm_file.file' => 'SCORM package must be a valid file.',
            'scorm_file.mimes' => 'SCORM package must be a ZIP file.',
            'scorm_file.max' => 'SCORM package must not exceed 600MB.',
            'metadata.array' => 'Metadata must be an object/array.',
            'metadata.*.string' => 'All metadata values must be strings.',
            'metadata.*.max' => 'Metadata values must not exceed 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'scorm_file' => 'SCORM package',
            'metadata' => 'metadata',
        ];
    }
}