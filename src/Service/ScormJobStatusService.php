<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Redis\Redis;
use OnixSystemsPHP\HyperfCore\Service\Service;

use function Hyperf\Config\config;

/**
 * Service for managing SCORM job statuses in Redis
 * Provides centralized status tracking for async SCORM processing jobs.
 */
#[Service]
class ScormJobStatusService
{
    private const JOB_STATUS_PREFIX = 'scorm:job:';

    private const PROGRESS_PREFIX = 'scorm_progress:';

    private const RESULT_PREFIX = 'scorm_result:';

    public function __construct(
        private readonly Redis $redis,
    ) {}

    /**
     * Initialize job status when job is queued.
     */
    public function initializeJob(string $jobId, array $data): void // notice same methods initializeJob and  updateProgress refactor to method progress
    {
        $key = self::JOB_STATUS_PREFIX . $jobId;
        $ttl = config('scorm.redis.ttl.job_status', 3600);
        $this->redis->setex($key, $ttl, json_encode($data));
    }

    /**
     * Update job progress during processing.
     */
    public function updateProgress(string $jobId, array $data): void
    {
        $key = self::PROGRESS_PREFIX . $jobId;
        $ttl = config('scorm.redis.ttl.job_status', 3600);
        $this->redis->setex($key, $ttl, json_encode($data));
    }

    /**
     * Set final job result.
     */
    public function setResult(string $jobId, array $data, ?int $ttl = null): void
    {
        $key = self::RESULT_PREFIX . $jobId;
        $defaultTtl = config('scorm.redis.ttl.job_result', 86400);
        $this->redis->setex($key, $ttl ?? $defaultTtl, json_encode($data));
    }

    /**
     * Get job status.
     */
    public function getStatus(string $jobId): ?array
    {
        $key = self::JOB_STATUS_PREFIX . $jobId;
        $data = $this->redis->get($key);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Get job progress.
     */
    public function getProgress(string $jobId): ?array
    {
        $key = self::PROGRESS_PREFIX . $jobId;
        $data = $this->redis->get($key);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Get job result.
     */
    public function getResult(string $jobId): ?array
    {
        $key = self::RESULT_PREFIX . $jobId;
        $data = $this->redis->get($key);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Delete job status and progress data.
     */
    public function deleteJob(string $jobId): void
    {
        $this->redis->del(self::JOB_STATUS_PREFIX . $jobId);
        $this->redis->del(self::PROGRESS_PREFIX . $jobId);
        $this->redis->del(self::RESULT_PREFIX . $jobId);
    }
}
