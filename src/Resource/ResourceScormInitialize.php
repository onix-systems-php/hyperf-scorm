<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractResource;
use OnixSystemsPHP\HyperfScorm\Model\ScormSession;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ResourceScormInitialize',
    description: 'SCORM session initialization data',
    properties: [
        new OA\Property(
            property: 'student',
            properties: [
                new OA\Property(property: 'id', description: 'Student ID', type: 'integer', example: 1),
                new OA\Property(
                    property: 'name',
                    description: 'Student name',
                    type: 'string',
                    example: 'John Doe',
                    nullable: true
                ),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'score',
            properties: [
                new OA\Property(
                    property: 'raw',
                    description: 'Raw score',
                    type: 'number',
                    example: 85.5,
                    nullable: true
                ),
                new OA\Property(
                    property: 'scaled',
                    description: 'Scaled score (0-1)',
                    type: 'number',
                    example: 0.855,
                    nullable: true
                ),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'session',
            properties: [
                new OA\Property(property: 'id', description: 'Session ID', type: 'integer', example: 123),
                new OA\Property(
                    property: 'session_time',
                    description: 'Current session time',
                    type: 'string',
                    example: 'PT5M',
                    nullable: true
                ),
                new OA\Property(
                    property: 'total_time',
                    description: 'Total learning time',
                    type: 'string',
                    example: 'PT15M30S',
                    nullable: true
                ),
                new OA\Property(
                    property: 'suspend_data',
                    description: 'Suspend data',
                    type: 'array',
                    example: ['bookmark' => 'page_5'],
                    nullable: true
                ),
                new OA\Property(
                    property: 'session_time_seconds',
                    description: 'Session time in seconds',
                    type: 'integer',
                    example: 300,
                    nullable: true
                ),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'lesson',
            properties: [
                new OA\Property(
                    property: 'status',
                    description: 'Lesson status',
                    type: 'string',
                    enum: ['incomplete', 'completed', 'passed', 'failed', 'browsed', 'not_attempted'],
                    example: 'incomplete',
                    nullable: true
                ),
                new OA\Property(
                    property: 'location',
                    description: 'Current position in lesson',
                    type: 'string',
                    example: 'slide_5',
                    nullable: true
                ),
                new OA\Property(
                    property: 'exit',
                    description: 'Exit mode',
                    type: 'string',
                    enum: ['suspend', 'logout', 'time-out'],
                    example: 'suspend',
                    nullable: true
                ),
                new OA\Property(
                    property: 'mode',
                    description: 'Lesson mode',
                    type: 'string',
                    enum: ['normal', 'browse', 'review'],
                    example: 'normal',
                    nullable: true
                ),
                new OA\Property(
                    property: 'entry',
                    description: 'Entry type',
                    type: 'string',
                    enum: ['ab-initio', 'resume'],
                    example: 'ab-initio',
                    nullable: true
                ),
                new OA\Property(
                    property: 'credit',
                    description: 'Credit type',
                    type: 'string',
                    enum: ['credit', 'no-credit'],
                    example: 'credit',
                    nullable: true
                ),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'interactions',
            description: 'Interactions (currently empty array)',
            type: 'array',
            items: new OA\Items(type: 'object'),
            example: []
        ),
        new OA\Property(
            property: 'completed_at',
            description: 'Lesson completion date',
            type: 'string',
            format: 'date-time',
            example: '2025-08-29T18:26:40.006Z',
            nullable: true
        ),
    ]
)]
/**
 * @method __construct(ScormSession $resource)
 * @property ScormSession $resource
 */
class ResourceScormInitialize extends AbstractResource
{
    public function toArray(): array
    {
        return [
            'student' => [
                'id' => $this->resource->user->id,
                'name' => $this->resource->user->full_name ?? 'Guest',
                'session_token' => $this->resource->session_token,
            ],
            'score' => [
                'raw' => $this->resource->score_raw,
                'scaled' => $this->resource->score_scaled,
            ],
            'session' => [
                'id' => $this->resource->id,
                'session_time' => $this->resource->session_time,
                'total_time' => $this->resource->total_time,
                'suspend_data' => $this->resource->suspend_data ?? [],
                'session_time_seconds' => $this->resource->session_time_seconds,
            ],
            'lesson' => [
                'status' => $this->resource->lesson_status,
                'location' => $this->resource->lesson_location,
                'exit' => $this->resource->exit_mode,
                'mode' => $this->resource->lesson_mode,
                'entry' => $this->resource->lesson_entry,
                'credit' => $this->resource->lesson_credit,
            ],
            'completed_at' => $this->resource->completed_at,
        ];
    }
}
