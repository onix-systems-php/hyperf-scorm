<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Request;

use Hyperf\Validation\Request\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RequestScormCompactCommit',
    required: ['studentId', 'lessonStatus', 'interactions'],
    properties: [
        new OA\Property(property: 'studentId', type: 'string', example: 'Guest'),
        new OA\Property(property: 'lessonStatus', type: 'string', enum: ['incomplete', 'completed', 'passed', 'failed', 'browsed', 'not_attempted'], example: 'incomplete'),
        new OA\Property(property: 'score', type: 'integer', minimum: 0, example: 0),
        new OA\Property(property: 'scorePercentage', type: 'integer', minimum: 0, maximum: 100, example: 0),
        new OA\Property(property: 'sessionTime', type: 'integer', minimum: 0, example: 0),
        new OA\Property(
            property: 'interactions',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'string', example: 'Scene1_Slide31_MultiResponse_0_0'),
                    new OA\Property(property: 'type', type: 'string', example: 'choice'),
                    new OA\Property(property: 'description', type: 'string', example: 'What test RESULTS are required?'),
                    new OA\Property(
                        property: 'learnerResponse',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['Creatinine_test_for_renal_function', 'Bone_mineral_density_(DEXA)_test']
                    ),
                    new OA\Property(
                        property: 'correctResponse',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['HIV_laboratory-based_antigen_antibody_(Ag_Ab)_test_or_an_HIV-1_RNA_test']
                    ),
                    new OA\Property(property: 'result', type: 'string', enum: ['correct', 'incorrect', 'unanticipated', 'neutral'], example: 'incorrect'),
                    new OA\Property(property: 'weighting', type: 'integer', minimum: 0, example: 10),
                    new OA\Property(property: 'latency', type: 'integer', minimum: 0, example: 51),
                    new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2025-08-29T21:26:27.0+03'),
                    new OA\Property(
                        property: 'objectives',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'string', example: '2023_PrEP_Journey'),
                            ],
                            type: 'object'
                        )
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Property(property: 'completedAt', type: 'string', format: 'date-time', example: '2025-08-29T18:26:40.006Z'),
    ]
)]
class RequestScormCompactCommit extends FormRequest
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
            'student_id' => ['required', 'integer'],
            'student_name' => ['required', 'string', 'max:255'],

            'session' => ['array'],
//            'session.id' => ['required', 'string', 'max:255'],
            'session.total_time' => ['nullable', 'integer', 'min:0'],
            'session.suspend_data' => ['nullable', 'string'],
            'session.launch_data' => ['nullable', 'string'],
            'session.comments' => ['nullable', 'string'],
            'session.comments_from_lms' => ['nullable', 'string'],

            'lesson' => ['array'],
            'lesson.status' => ['required', 'string', 'in:incomplete,completed,passed,failed,browsed,not_attempted'],
            'lesson.mode' => ['nullable', 'string', 'in:normal,browse,review'],
            'lesson.exit' => ['nullable', 'string', 'in:time-out,suspend,logout,close'],
            'lesson.location' => ['nullable', 'string', 'max:255'],

            'score' => ['nullable', 'integer', 'min:0'],
            'scorePercentage' => ['nullable', 'integer', 'min:0', 'max:100'],
//            'sessionTime' => ['nullable', 'integer', 'min:0'],
            'interactions' => ['array'],
            'interactions.*.id' => ['required', 'string', 'max:255'],
            'interactions.*.type' => ['required', 'string', 'max:50'],
            'interactions.*.description' => ['nullable', 'string', 'max:1000'],
            'interactions.*.learner_response' => ['nullable', 'array'],
            'interactions.*.learner_response.*' => ['string', 'max:500'],
            'interactions.*.correct_response' => ['nullable', 'array'],
            'interactions.*.correct_response.*' => ['string', 'max:500'],
            'interactions.*.result' => ['required', 'string', 'in:correct,incorrect,unanticipated,neutral'],
            'interactions.*.weighting' => ['nullable', 'integer', 'min:0'],
            'interactions.*.latency' => ['nullable', 'integer', 'min:0'],
            'interactions.*.interaction_timestamp' => ['required', 'string', 'date'],
            'interactions.*.objectives' => ['nullable', 'array'],
            'interactions.*.objectives.*.id' => ['required', 'string', 'max:255'],
            'completedAt' => ['nullable', 'string', 'date'],
        ];
    }
}
