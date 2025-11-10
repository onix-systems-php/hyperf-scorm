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

# 4. Publish example in directory storage/assets/scorm
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_example

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
SCORM_MAX_FILE_SIZE=600 #MB
SCORM_DEBUG=true
SCORM_AUTO_COMMIT_INTERVAL=30 #sec
SCORM_CACHE_TTL=3600 #sec
SCORM_API_ENDPOINT=/v1/api/scorm
SCORM_STORAGE_DRIVER=local #s3|local
SCORM_WS_NAME=socket-io
```

### 2. Upload Package

```bash
curl -X POST https://your-api.com/v1/scorm/packages/upload \
  -H "Authorization: Bearer TOKEN" \
  -F "file=@package.zip"

# Returns: {
    "data": {
        "job_id": "31ee577a-3409-4228-950e-82eea8f9c172",
        "status": "queued",
        "progress": 0,
        "stage": "queued",
        "estimated_time": 433,
        "is_async": true
    },
    "status": 200
}
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

| Variable | Default | Unit | Description |
|----------|---------|------|-------------|
| **Storage Configuration** ||||
| `SCORM_STORAGE_DRIVER` | `local` | - | Storage driver: `s3` or `local` |
| `SCORM_MAX_FILE_SIZE` | `600` | MB | Maximum SCORM package upload size |
| **Local Storage (when driver=local)** ||||
| `SCORM_LOCAL_PATH` | `storage/public/scorm` | - | Local filesystem path for SCORM content |
| `SCORM_LOCAL_PUBLIC_URL` | `http://localhost/public/scorm` | - | Public URL for local storage access |
| **S3 Storage (when driver=s3)** ||||
| `SCORM_S3_KEY` | - | - | AWS access key ID (required for S3) |
| `SCORM_S3_SECRET` | - | - | AWS secret access key (required for S3) |
| `SCORM_S3_REGION` | `us-east-1` | - | AWS region for S3 bucket |
| `SCORM_S3_BUCKET` | `scorm-content` | - | S3 bucket name for SCORM content |
| `SCORM_S3_ENDPOINT` | - | - | Custom S3 endpoint (for S3-compatible services) |
| `SCORM_S3_PATH_STYLE` | `false` | - | Use path-style endpoint (true for MinIO/custom S3) |
| `SCORM_S3_DOMAIN` | - | - | Custom domain for S3 public URLs (CDN) |
| **API & Player Configuration** ||||
| `SCORM_API_ENDPOINT` | `/v1/api/scorm` | - | Base API endpoint path for SCORM player |
| `SCORM_API_TIMEOUT` | `30000` | ms | API request timeout in milliseconds |
| `SCORM_DEBUG` | `false` | - | Enable debug mode (verbose logging) |
| `SCORM_AUTO_COMMIT_INTERVAL` | `30` | sec | Auto-commit interval for CMI data |
| **WebSocket Configuration** ||||
| `SCORM_WS_NAME` | `socket-io` | - | WebSocket server name identifier |
| **Cache Configuration** ||||
| `SCORM_CACHE_TTL` | `3600` | sec | Cache time-to-live for SCORM data |
| **Redis TTL Configuration** ||||
| `SCORM_REDIS_TTL_JOB_STATUS` | `3600` | sec | Job status cache TTL (1 hour) |
| `SCORM_REDIS_TTL_JOB_RESULT` | `86400` | sec | Job result cache TTL (24 hours) |
| `SCORM_REDIS_TTL_WEBSOCKET` | `86400` | sec | WebSocket connection data TTL (24 hours) |

## WebSocket Server Setup

WebSocket provides real-time progress updates. Configure in `config/autoload/server.php`:

```php
return [
    'servers' => [
        [
            'name' => 'socket',
            'type' => Server::SERVER_WEBSOCKET,
            'host' => '0.0.0.0',
            'port' => 9502,//NOTICE this port must be open on the server
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

## API Reference

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/v1/scorm/packages/upload` | Upload SCORM ZIP (returns job_id) |
| GET | `/v1/scorm/packages` | List packages (paginated) |
| DELETE | `/v1/scorm/packages/{id}` | Delete package |
| GET | `/v1/api/scorm/player/{id}/launch` | Launch SCORM player |
| GET | `/v1/scorm/jobs/{id}/status` | Get job status |
| POST | `/v1/scorm/jobs/{id}/cancel` | Cancel job |
| WS | `ws://host:9502/scorm-progress` | Real-time progress |

All endpoints require authentication except WebSocket (uses query param validation).

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
