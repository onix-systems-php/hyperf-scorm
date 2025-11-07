# Hyperf SCORM Package

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%5E3.1-brightgreen.svg)](https://hyperf.io)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Enterprise-grade SCORM package for Hyperf with async processing and real-time WebSocket progress tracking.

## What is SCORM?

SCORM (Sharable Content Object Reference Model) is the e-learning standard for creating and tracking online learning content. This package provides:

- SCORM 1.2 & 2004 support with automatic version detection
- Async queue processing for large packages
- Real-time WebSocket progress notifications
- Complete CMI data model implementation
- Multi-SCO support with full Run-Time Environment

## Features

- ✅ **SCORM 1.2 & 2004** - Full compliance, auto-detect version
- ✅ **Async Processing** - Queue-based handling with configurable threshold
- ✅ **Real-time Progress** - WebSocket notifications with fallback
- ✅ **Production Ready** - Battle-tested, memory efficient, comprehensive error handling
- ✅ **Flexible Storage** - S3, local storage, or custom drivers

## Requirements

- PHP 8.2+ (8.1 supported), Hyperf 3.1.42+
- Redis, PostgreSQL 13+ or MySQL 8.0+
- Swoole 5.0+

## Installation

```bash
# 1. Install package
composer require onix-systems-php/hyperf-scorm

# 2. Publish configuration
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_config

# 3. Publish migrations
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_migrations

# 4. Publish assets
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_public_data

# 5. Run migrations
php bin/hyperf.php migrate

# 6. Register routes in config/routes.php
require_once './vendor/onix-systems-php/hyperf-scorm/publish/routes.php';

# 7. Configure queue & WebSocket (see sections below)
```

## Quick Start

### 1. Configure Environment

```env
# Essential settings
SCORM_STORAGE_DRIVER=s3
SCORM_MAX_FILE_SIZE=100
SCORM_ASYNC_THRESHOLD=25

# S3 Storage
SCORM_S3_BUCKET=your-bucket
SCORM_S3_REGION=us-east-1
SCORM_S3_PUBLIC_URL=https://your-cdn.com
```

### 2. Upload Package

```bash
curl -X POST https://your-api.com/v1/scorm/packages/upload \
  -H "Authorization: Bearer TOKEN" \
  -F "file=@package.zip"

# Returns: {"job_id": "uuid", "status": "queued", "estimated_time": 50}
```

### 3. Track Progress (WebSocket)

```javascript
const ws = new WebSocket(`ws://your-domain:9502/scorm-progress?job_id=${jobId}`);
ws.onmessage = (e) => {
  const {stage, progress, status} = JSON.parse(e.data).data;
  console.log(`${stage}: ${progress}%`);
};
```

## Configuration

All settings in `config/autoload/scorm.php` can be overridden via environment variables.

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| **Storage** |||
| `SCORM_STORAGE_DRIVER` | `s3` | Storage driver: `s3` or `local` |
| `SCORM_STORAGE_BASE_PATH` | `scorm-packages` | Base path for content |
| `SCORM_S3_BUCKET` | `scorm-content` | S3 bucket name |
| `SCORM_S3_REGION` | `us-east-1` | AWS region |
| `SCORM_S3_PUBLIC_URL` | - | CDN URL for S3 content |
| `SCORM_S3_STREAMING_ENABLED` | `true` | Enable streaming for large files |
| `SCORM_LOCAL_PATH` | `storage/public/scorm` | Local filesystem path |
| `SCORM_LOCAL_PUBLIC_URL` | `http://localhost/public/scorm` | Public URL for local storage |
| **Upload & Processing** |||
| `SCORM_MAX_FILE_SIZE` | `100` | Max upload size (MB) |
| `SCORM_ASYNC_THRESHOLD` | `25` | Async threshold (MB) |
| `SCORM_MAX_MEMORY_USAGE` | `512` | Max memory (MB) |
| `SCORM_PARALLEL_LIMIT` | `3` | Parallel processing limit |
| `SCORM_TEMP_CLEANUP_TTL` | `86400` | Temp cleanup TTL (seconds) |
| **Player** |||
| `SCORM_API_ENDPOINT` | `/v1/api/scorm` | API endpoint path |
| `SCORM_API_TIMEOUT` | `30000` | API timeout (ms) |
| `SCORM_DEBUG` | `false` | Debug mode |
| `SCORM_AUTO_COMMIT_INTERVAL` | `30` | Auto-commit interval (seconds) |
| `SCORM_DETAILED_LOGS` | `true` | Detailed tracking logs |
| **Redis** |||
| `SCORM_REDIS_TTL_JOB_STATUS` | `3600` | Job status TTL (seconds) |
| `SCORM_REDIS_TTL_JOB_RESULT` | `86400` | Job result TTL (seconds) |
| `SCORM_REDIS_TTL_WEBSOCKET` | `86400` | WebSocket TTL (seconds) |
| **Queue** |||
| `SCORM_QUEUE_MAX_ATTEMPTS` | `3` | Max retry attempts |
| `SCORM_QUEUE_RETRY_DELAY` | `0` | Retry delay (seconds) |
| **Cache** |||
| `SCORM_CACHE_TTL` | `3600` | Cache TTL (seconds) |

### Complete .env Example

```env
# Storage
SCORM_STORAGE_DRIVER=s3
SCORM_S3_BUCKET=scorm-content
SCORM_S3_REGION=us-east-1
SCORM_S3_PUBLIC_URL=https://cdn.example.com

# Processing
SCORM_MAX_FILE_SIZE=100
SCORM_ASYNC_THRESHOLD=25
SCORM_MAX_MEMORY_USAGE=512

# Player
SCORM_API_ENDPOINT=/v1/api/scorm
SCORM_DEBUG=false

# Redis TTL
SCORM_REDIS_TTL_JOB_STATUS=3600
SCORM_REDIS_TTL_WEBSOCKET=86400

# Queue
SCORM_QUEUE_MAX_ATTEMPTS=3
```

## WebSocket Server Setup

WebSocket provides real-time progress updates. Configure in `config/autoload/server.php`:

```php
return [
    'servers' => [
        ['name' => 'http', 'type' => Server::SERVER_HTTP, 'host' => '0.0.0.0', 'port' => 9501],
        [
            'name' => 'socket',
            'type' => Server::SERVER_WEBSOCKET,
            'host' => '0.0.0.0',
            'port' => 9502,
            'callbacks' => [
                Event::ON_HAND_SHAKE => [Hyperf\WebSocketServer\Server::class, 'onHandShake'],
                Event::ON_MESSAGE => [Hyperf\WebSocketServer\Server::class, 'onMessage'],
                Event::ON_CLOSE => [Hyperf\WebSocketServer\Server::class, 'onClose'],
            ],
            'settings' => [
                Constant::OPTION_HEARTBEAT_CHECK_INTERVAL => 60,
                Constant::OPTION_HEARTBEAT_IDLE_TIME => 600,
            ],
        ],
    ],
];
```

### WebSocket Routes

```php
// In publish/routes.php
Router::addServer('socket-io', function () {
    Router::get('/scorm-progress', \OnixSystemsPHP\HyperfScorm\Controller\WebSocket\ScormProgressWebSocketController::class);
});
```

### Connection Flow

1. Connect: `ws://host:9502/scorm-progress?job_id={uuid}`
2. Receive progress: `{"type":"progress","data":{"stage":"extracting","progress":45}}`
3. Heartbeat: Server pings every 30s, client responds with pong
4. Complete: `{"type":"progress","data":{"status":"completed","package_id":123}}`

### Message Format

```json
{
  "type": "progress",
  "data": {
    "job_id": "uuid",
    "status": "processing",
    "stage": "extracting",
    "progress": 45,
    "package_id": null,
    "error": null
  }
}
```

### Configuration

Configure in `config/autoload/async_queue.php`:

```php
return [
    'scorm-processing' => [
        'driver' => Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'channel' => 'scorm-jobs',
        'timeout' => 2,
        'handle_timeout' => 1800, // 30 minutes
        'processes' => 2,
        'max_attempts' => 3,
    ],
];
```

## API Reference

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/v1/scorm/packages/upload` | Upload SCORM ZIP (returns job_id) |
| GET | `/v1/scorm/packages` | List packages (paginated) |
| GET | `/v1/scorm/packages/{id}` | Get package details |
| DELETE | `/v1/scorm/packages/{id}` | Delete package |
| GET | `/v1/api/scorm/player/{id}/launch` | Launch SCORM player |
| GET | `/v1/scorm/jobs/{id}/status` | Get job status |
| POST | `/v1/scorm/jobs/{id}/cancel` | Cancel job |
| WS | `ws://host:9502/scorm-progress` | Real-time progress |

All endpoints require authentication except WebSocket (uses query param validation).

## Database Schema

### Tables Created

| Table | Purpose |
|-------|---------|
| `scorm_packages` | Package metadata (title, version, storage path) |
| `scorm_user_sessions` | User progress tracking |

### Key Fields

**scorm_packages:** id, title, version (1.2/2004), storage_path, manifest_data (JSON), sco_count
**scorm_user_sessions:** id, user_id, sco_id, session_token, cmi_core_lesson_status, scores, times, suspend_data

### SCORM Support

| Feature | SCORM 1.2 | SCORM 2004 |
|---------|:---------:|:----------:|
| Manifest Parsing | ✅ | ✅ |
| Auto Version Detection | ✅ | ✅ |
| Multiple SCOs | ✅ | ✅ |
| CMI Data Model | ✅ | ✅ |
| Sequencing Rules | ❌ | ✅ |
| Interactions | ✅ | ✅ |

**Supported schemaversion:**
1.2: `1.2`, `CAM 1.2`
2004: `CAM 1.3`, `2004`, `2004 3rd Edition`, `2004 4th Edition`

## Production Deployment

### Checklist

- [ ] Configure Redis (queue, cache, WebSocket)
- [ ] Set up WebSocket server (port 9502)
- [ ] Configure S3 storage with CDN
- [ ] Set appropriate `SCORM_MAX_FILE_SIZE`
- [ ] Start 2-3 queue workers (Supervisor/systemd)
- [ ] Configure Nginx/Apache reverse proxy
- [ ] Enable SSL/TLS for WebSocket
- [ ] Configure monitoring and logging
- [ ] Test WebSocket through firewall
- [ ] Set up backup strategy
- [ ] Review security settings

### Debug Mode

```env
SCORM_DEBUG=true
SCORM_DETAILED_LOGS=true
```
