<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractResource;
use OnixSystemsPHP\HyperfScorm\Model\ScormUserSession;
use OpenApi\Attributes as OA;

/**
 * @method __construct(ScormUserSession $resource)
 * @property ScormUserSession $resource
 */
class ResourceScormInitialize extends AbstractResource
{
    public function toArray(): array
    {
        return [
            'student' => [
                'id' => $this->resource->user_id,
                'name' => $this->resource->student_name,
            ],
            'score' => [
                'raw' => $this->resource->score_raw,
                'scaled' =>  $this->resource->score_scaled,
            ],
            //           'score_percentage' =>  $this->resource->score_percentage ?? 0, //todo calculate if null
            'session' => [
               'id' => $this->resource->id,
               'session_time' =>  $this->resource->session_time,
               'total_time' =>  $this->resource->total_time,
               'suspend_data' =>  $this->resource->suspend_data,
               'session_time_seconds' =>  $this->resource->session_time_seconds,
            ],
            'lesson' => [
               'status' =>  $this->resource->lesson_status,
               'location' =>  $this->resource->current_location,
               'exit' =>  $this->resource->exit_mode,
            ],
            //            'interactions' =>  $this->resource->interactions ?? [],//todo need this data or not? check can you go back in scorm
            'interactions' => [],
            'completed_at' =>  $this->resource->completed_at,

        ];
//        return [
//            "id" => $this->resource->id,
//            "package_id" => $this->resource->package_id,
//            "user_id" => $this->resource->user_id,
//            "status" => $this->resource->status,
//            "lesson_location" => $this->resource->lesson_location,
//            "lesson_status" => $this->resource->lesson_status,
//            "suspend_data" => $this->resource->suspend_data,
//            "score" => $this->resource->score,
//            "time_spent" => $this->resource->time_spent,
//            "cmi_data" => $this->resource->cmi_data,
//
//            "started_at" => $this->resource->started_at,
//            "completed_at" => $this->resource->completed_at,
//            "created_at" => $this->resource->created_at,
//            "updated_at" => $this->resource->updated_at,
//        ];
    }
}
