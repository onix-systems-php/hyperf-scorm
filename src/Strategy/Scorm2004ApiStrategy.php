<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Strategy;

/**
 * SCORM 2004 API Strategy - handles SCORM 2004 specific tracking and API
 */
class Scorm2004ApiStrategy implements ScormApiStrategyInterface
{
    public function getApiConfiguration(): array
    {
        return [
            'apiObjectName' => 'API_1484_11',
            'initializeFunction' => 'Initialize',
            'terminateFunction' => 'Terminate',
            'getValueFunction' => 'GetValue',
            'setValueFunction' => 'SetValue',
            'commitFunction' => 'Commit',
            'getLastErrorFunction' => 'GetLastError',
            'getErrorStringFunction' => 'GetErrorString',
            'getDiagnosticFunction' => 'GetDiagnostic',
            'elementMapping' => [
                'cmi.completion_status' => 'completionStatus',
                'cmi.success_status' => 'successStatus',
                'cmi.score.raw' => 'score',
                'cmi.location' => 'lessonLocation',
                'cmi.session_time' => 'sessionTime',
                'cmi.suspend_data' => 'suspendData',
                'cmi.learner_id' => 'studentId',
                'cmi.learner_name' => 'studentName',
                'cmi.progress_measure' => 'progressMeasure'
            ]
        ];
    }

    public function getCmiDataModel(): array
    {
        return [
            // SCORM 2004 separated completion and success status
            'cmi.completion_status' => [
                'type' => 'string',
                'values' => ['completed', 'incomplete', 'not_attempted', 'unknown'],
                'writable' => true
            ],
            'cmi.success_status' => [
                'type' => 'string',
                'values' => ['passed', 'failed', 'unknown'],
                'writable' => true
            ],

            // Score elements
            'cmi.score.raw' => [
                'type' => 'decimal',
                'writable' => true
            ],
            'cmi.score.max' => [
                'type' => 'decimal',
                'writable' => true
            ],
            'cmi.score.min' => [
                'type' => 'decimal',
                'writable' => true
            ],
            'cmi.score.scaled' => [
                'type' => 'decimal',
                'range' => [-1, 1],
                'writable' => true
            ],

            // Location and time
            'cmi.location' => [
                'type' => 'string',
                'max_length' => 1000,
                'writable' => true
            ],
            'cmi.session_time' => [
                'type' => 'timeinterval',
                'writable' => true
            ],

            // Suspend data - SCORM 2004 increased limit: 64000 characters
            'cmi.suspend_data' => [
                'type' => 'string',
                'max_length' => 64000,
                'writable' => true
            ],

            // Progress measure - new in SCORM 2004
            'cmi.progress_measure' => [
                'type' => 'decimal',
                'range' => [0, 1],
                'writable' => true
            ],

            // Learner information (read-only)
            'cmi.learner_id' => [
                'type' => 'string',
                'writable' => false
            ],
            'cmi.learner_name' => [
                'type' => 'string',
                'writable' => false
            ],

            // Enhanced interactions support in SCORM 2004
            'cmi.interactions._count' => [
                'type' => 'integer',
                'writable' => false
            ],
            'cmi.interactions.n.id' => [
                'type' => 'string',
                'writable' => true
            ],
            'cmi.interactions.n.type' => [
                'type' => 'string',
                'values' => ['true-false', 'choice', 'fill-in', 'long-fill-in', 'matching', 'performance', 'sequencing', 'likert', 'numeric', 'other'],
                'writable' => true
            ],
            'cmi.interactions.n.result' => [
                'type' => 'string',
                'values' => ['correct', 'incorrect', 'unanticipated', 'neutral'],
                'writable' => true
            ],
            'cmi.interactions.n.timestamp' => [
                'type' => 'datetime',
                'writable' => true
            ],
            'cmi.interactions.n.description' => [
                'type' => 'string',
                'max_length' => 250,
                'writable' => true
            ],

            // Objectives support - enhanced in SCORM 2004
            'cmi.objectives._count' => [
                'type' => 'integer',
                'writable' => false
            ],
            'cmi.objectives.n.id' => [
                'type' => 'string',
                'writable' => true
            ],
            'cmi.objectives.n.success_status' => [
                'type' => 'string',
                'values' => ['passed', 'failed', 'unknown'],
                'writable' => true
            ],
            'cmi.objectives.n.completion_status' => [
                'type' => 'string',
                'values' => ['completed', 'incomplete', 'not_attempted', 'unknown'],
                'writable' => true
            ],
            'cmi.objectives.n.progress_measure' => [
                'type' => 'decimal',
                'range' => [0, 1],
                'writable' => true
            ]
        ];
    }

    public function mapTrackingElement(string $element, string $value): array
    {
        $mappings = [
            'cmi.completion_status' => ['element_name' => 'completion_status', 'element_value' => $value],
            'cmi.success_status' => ['element_name' => 'success_status', 'element_value' => $value],
            'cmi.score.raw' => ['element_name' => 'score_raw', 'element_value' => $value],
            'cmi.score.scaled' => ['element_name' => 'score_scaled', 'element_value' => $value],
            'cmi.location' => ['element_name' => 'location', 'element_value' => $value],
            'cmi.session_time' => ['element_name' => 'session_time', 'element_value' => $value],
            'cmi.suspend_data' => ['element_name' => 'suspend_data', 'element_value' => $value],
            'cmi.progress_measure' => ['element_name' => 'progress_measure', 'element_value' => $value]
        ];

        return $mappings[$element] ?? ['element_name' => $element, 'element_value' => $value];
    }

    public function validateElement(string $element, string $value): bool
    {
        $dataModel = $this->getCmiDataModel();

        if (!isset($dataModel[$element])) {
            return false;
        }

        $elementDef = $dataModel[$element];

        // Check if element is writable
        if (isset($elementDef['writable']) && !$elementDef['writable']) {
            return false;
        }

        // Validate based on type
        switch ($elementDef['type']) {
            case 'string':
                if (isset($elementDef['max_length']) && strlen($value) > $elementDef['max_length']) {
                    return false;
                }
                if (isset($elementDef['values']) && !in_array($value, $elementDef['values'])) {
                    return false;
                }
                break;

            case 'decimal':
                if (!is_numeric($value)) {
                    return false;
                }
                $numValue = (float)$value;
                if (isset($elementDef['range'])) {
                    [$min, $max] = $elementDef['range'];
                    if ($numValue < $min || $numValue > $max) {
                        return false;
                    }
                }
                break;

            case 'integer':
                if (!ctype_digit($value)) {
                    return false;
                }
                break;

            case 'timeinterval':
                // SCORM 2004 time format: PT[n]H[n]M[n]S or PT[n].[n]S
                if (!preg_match('/^PT(\d+H)?(\d+M)?(\d+(\.\d+)?S)?$/', $value)) {
                    return false;
                }
                break;

            case 'datetime':
                // ISO 8601 format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{3})?Z?$/', $value)) {
                    return false;
                }
                break;
        }

        return true;
    }

    public function getSupportedVersion(): string
    {
        return '2004';
    }
}
