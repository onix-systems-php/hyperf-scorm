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
                new OA\Property(property: 'id', type: 'integer', description: 'Student ID', example: 1),
                new OA\Property(property: 'name', type: 'string', nullable: true, description: 'Student name', example: 'John Doe'),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'score',
            properties: [
                new OA\Property(property: 'raw', type: 'number', nullable: true, description: 'Raw score', example: 85.5),
                new OA\Property(property: 'scaled', type: 'number', nullable: true, description: 'Scaled score (0-1)', example: 0.855),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'session',
            properties: [
                new OA\Property(property: 'id', type: 'integer', description: 'Session ID', example: 123),
                new OA\Property(property: 'session_time', type: 'string', nullable: true, description: 'Current session time', example: 'PT5M'),
                new OA\Property(property: 'total_time', type: 'string', nullable: true, description: 'Total learning time', example: 'PT15M30S'),
                new OA\Property(property: 'suspend_data', type: 'array', nullable: true, description: 'Suspend data', example: ['bookmark' => 'page_5']),
                new OA\Property(property: 'session_time_seconds', type: 'integer', nullable: true, description: 'Session time in seconds', example: 300),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'lesson',
            properties: [
                new OA\Property(property: 'status', type: 'string', nullable: true, description: 'Lesson status', enum: ['incomplete', 'completed', 'passed', 'failed', 'browsed', 'not_attempted'], example: 'incomplete'),
                new OA\Property(property: 'location', type: 'string', nullable: true, description: 'Current position in lesson', example: 'slide_5'),
                new OA\Property(property: 'exit', type: 'string', nullable: true, description: 'Exit mode', enum: ['suspend', 'logout', 'time-out'], example: 'suspend'),
                new OA\Property(property: 'mode', type: 'string', nullable: true, description: 'Lesson mode', enum: ['normal', 'browse', 'review'], example: 'normal'),
                new OA\Property(property: 'entry', type: 'string', nullable: true, description: 'Entry type', enum: ['ab-initio', 'resume'], example: 'ab-initio'),
                new OA\Property(property: 'credit', type: 'string', nullable: true, description: 'Credit type', enum: ['credit', 'no-credit'], example: 'credit'),
            ],
            type: 'object'
        ),
        new OA\Property(
            property: 'interactions',
            type: 'array',
            description: 'Interactions (currently empty array)',
            items: new OA\Items(type: 'object'),
            example: []
        ),
        new OA\Property(property: 'completed_at', type: 'string', nullable: true, format: 'date-time', description: 'Lesson completion date', example: '2025-08-29T18:26:40.006Z'),
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
            //           'score_percentage' =>  $this->resource->score_percentage ?? 0, //todo calculate if null
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
            //            'interactions' =>  $this->resource->interactions ?? [],//todo need this data or not? check can you go back in scorm
            'interactions' => [],
            'completed_at' => $this->resource->completed_at,
        ];
    }
}
