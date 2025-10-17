<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Process;

use Hyperf\AsyncQueue\Process\ConsumerProcess;
use Hyperf\Process\Annotation\Process;

#[Process(name: 'scorm-upload-queue-consumer')]
class UploadQueueConsumer extends ConsumerProcess
{
    protected string $queue = 'scorm-processing';
}
