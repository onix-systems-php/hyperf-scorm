# Hyperf SCORM Package

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%5E3.1-brightgreen.svg)](https://hyperf.io)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Enterprise-grade SCORM (Sharable Content Object Reference Model) package for Hyperf framework with real-time WebSocket progress tracking and asynchronous processing.

## Features

- ✅ **SCORM 1.2 & 2004** - Full compliance with SCORM 1.2 and SCORM 2004 (CAM 1.3, 3rd Edition)
- ✅ **Async Processing** - Queue-based handling for large SCORM packages
- ✅ **Real-time Progress** - WebSocket notifications during upload and processing
- ✅ **CMI Data Model** - Complete SCORM Run-Time Environment implementation
- ✅ **Multi-SCO Support** - Handle complex course structures with multiple SCOs
- ✅ **Production Ready** - Battle-tested architecture with comprehensive error handling
- ✅ **SOLID Architecture** - Service layer pattern, Repository pattern, DTOs
- ✅ **Automatic Version Detection** - Intelligently detects SCORM version from manifest

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [WebSocket Real-Time Progress](#websocket-real-time-progress)
- [API Reference](#api-reference)
- [Architecture](#architecture)
- [SCORM Version Support](#scorm-version-support)
- [Production Deployment](#production-deployment)

## Requirements

- **PHP** 8.1 or higher
- **Hyperf** 3.1.42 or higher
- **Redis** - For async queue and WebSocket support
- **Database** - PostgreSQL or MySQL
- **Swoole** - PHP extension (installed with Hyperf)

### Recommended for Production

- PHP 8.2+ for optimal performance
- Redis cluster for high availability
- Separate queue workers for SCORM processing
- CDN for SCORM content delivery

## Installation

### 1. Install via Composer

```bash
composer require onix-systems-php/hyperf-scorm
```

### 2. Publish Configuration

```bash
# Publish configuration file
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_config
```

### 3. Publish Migrations

```bash
# Publish database migrations
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_migrations
```

### 4. Publish Public Assets

```bash
# Publish JavaScript API and SCORM player
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_public_data
```

### 5. Run Migrations

```bash
php bin/hyperf.php migrate
```

### 6. Register Routes

Add to your `config/routes.php`:

```php
<?php

require_once './vendor/onix-systems-php/hyperf-scorm/publish/routes.php';
```

## Configuration

After publishing, configure the package in `config/autoload/scorm.php`:

### Storage Configuration

```php
'storage' => [
    'disk' => env('SCORM_STORAGE_DISK', 'local'),
    'path' => env('SCORM_STORAGE_PATH', 'scorm-packages'),
    'temp_path' => env('SCORM_TEMP_PATH', 'temp/scorm'),
    'max_file_size' => env('SCORM_MAX_FILE_SIZE', 100 * 1024 * 1024), // 100MB
],
```

### Tracking Configuration

```php
'tracking' => [
    'auto_commit_interval' => env('SCORM_AUTO_COMMIT_INTERVAL', 30), // seconds
    'enable_debug_logging' => env('SCORM_DEBUG_LOGGING', false),
],
```

### Player Configuration

```php
'player' => [
    'timeout' => env('SCORM_PLAYER_TIMEOUT', 30000), // milliseconds
    'debug' => env('SCORM_PLAYER_DEBUG', false),
    'api_endpoint' => env('SCORM_API_ENDPOINT', '/api/v1/scorm/api'),
],
```

### API Configuration

```php
'api' => [
    'version' => '1.2', // Default SCORM version
    'strict_mode' => env('SCORM_STRICT_MODE', false),
    'error_reporting' => env('SCORM_ERROR_REPORTING', true),
],
```

### Security Configuration

```php
'security' => [
    'allowed_domains' => env('SCORM_ALLOWED_DOMAINS', '*'),
    'iframe_sandbox' => env('SCORM_IFRAME_SANDBOX', 'allow-scripts allow-same-origin allow-forms'),
    'csrf_protection' => env('SCORM_CSRF_PROTECTION', true),
],
```
## WebSocket Real-Time Progress

### Why WebSocket?

Processing SCORM packages, especially large ones with complex structures, can take significant time. This package uses **WebSocket to provide real-time feedback** during the entire processing pipeline.

### Processing Stages

The WebSocket connection provides updates for these stages:

1. **Initializing** - File upload started, job created
2. **Extracting** - Unzipping SCORM package with progress percentage
3. **Parsing** - Reading and validating imsmanifest.xml
4. **Processing** - Creating database records and organizing files
5. **Completed** - Package ready for use
6. **Failed** - Error occurred with detailed error message

### WebSocket Endpoint

```
ws://your-domain/scorm-progress?job_id={jobId}
```

### Client Example (JavaScript)

```javascript
const jobId = 'your-job-id-from-upload-response';
const ws = new WebSocket(`ws://your-domain/scorm-progress?job_id=${jobId}`);

ws.onopen = () => {
  console.log('WebSocket connection established');
};

ws.onmessage = (event) => {
  const data = JSON.parse(event.data);

  switch(data.status) {
    case 'initializing':
      console.log('Starting upload...');
      break;
    case 'extracting':
      console.log(`Extracting: ${data.progress}%`);
      updateProgressBar(data.progress);
      break;
    case 'parsing':
      console.log('Parsing manifest...');
      break;
    case 'completed':
      console.log('Package ready!', data.package);
      redirectToPlayer(data.package.id);
      break;
    case 'failed':
      console.error('Upload failed:', data.error);
      showError(data.error);
      break;
  }
};

ws.onerror = (error) => {
  console.error('WebSocket error:', error);
};

ws.onclose = () => {
  console.log('WebSocket connection closed');
};
```

## API Reference

### Package Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/v1/scorm/packages/upload` | Upload SCORM .zip file |
| POST | `/v1/scorm/packages` | Create package from uploaded file |
| GET | `/v1/scorm/packages` | List all packages |
| GET | `/v1/scorm/packages/{id}` | Get package details |
| DELETE | `/v1/scorm/packages/{id}` | Delete package |

### Player

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/api/scorm/player/{packageId}/launch` | Launch SCORM player |

### Job Monitoring

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/scorm/jobs/{jobId}/status` | Get processing status |
| POST | `/v1/scorm/jobs/batch-status` | Batch status check |
| POST | `/v1/scorm/jobs/{jobId}/cancel` | Cancel processing job |

### WebSocket

| Protocol | Endpoint | Description |
|----------|----------|-------------|
| WS | `/scorm-progress?job_id={jobId}` | Real-time progress updates |

## Architecture

### Service Layer Components

- **ScormPackageService** - Package management and CRUD operations
- **ScormManifestParser** - XML manifest parsing with version detection
- **ScormProgressTrackerService** - Learning progress tracking and CMI data management
- **ScormWebSocketNotificationService** - Real-time WebSocket notifications
- **ScormPackageProcessor** - Async processing of uploaded packages

### SCORM Standards Compliance

- **SCORM 1.2** - Full Run-Time Environment implementation
- **SCORM 2004** - CAM 1.3 and 4th Edition support
- **Automatic Version Detection** - From `<schemaversion>` in manifest
- **CMI Data Model** - Complete implementation for both versions

## SCORM Version Support

| Feature | SCORM 1.2 | SCORM 2004 |
|---------|:---------:|:----------:|
| Manifest Parsing | ✅ | ✅ |
| Auto Version Detection | ✅ | ✅ |
| Multiple SCOs | ✅ | ✅ |
| Launch URL Construction | ✅ | ✅ |
| Parameters Support | ✅ | ✅ |
| xml:base Support | ✅ | ✅ |
| CMI Data Model | SCORM 1.2 | SCORM 2004 |
| Sequencing Rules | ❌ | ✅ |
| Navigation Controls | Basic | Advanced |
| Objectives Tracking | Basic | Advanced |
| Interactions | ✅ | ✅ |
| Suspend Data | ✅ | ✅ |

### Supported schemaversion Values

**SCORM 1.2:**
- `1.2`
- `CAM 1.2`
- Any version containing "1.2"

**SCORM 2004:**
- `CAM 1.3`
- `2004`
- `2004 3rd Edition`
- `2004 4th Edition`
- Any version containing "2004" or "CAM 1.3"

## Production Deployment

### Pre-Deployment Checklist

- [ ] Configure Redis for queue and cache
- [ ] Set up WebSocket server on separate port (default: 9502)
- [ ] Configure file storage (local/S3/NFS)
- [ ] Set `SCORM_MAX_FILE_SIZE` appropriate for your needs
- [ ] Enable CORS if frontend is on different domain
- [ ] Configure async queue workers (minimum 2-3 workers)
- [ ] Set up monitoring and logging
- [ ] Review security settings
- [ ] Configure backup strategy for SCORM packages
- [ ] Test WebSocket connectivity

### Environment Variables

```bash
# Storage
SCORM_STORAGE_DISK=local
SCORM_STORAGE_PATH=scorm-packages
SCORM_TEMP_PATH=temp/scorm
SCORM_MAX_FILE_SIZE=104857600  # 100MB in bytes

# Tracking
SCORM_AUTO_COMMIT_INTERVAL=30
SCORM_DEBUG_LOGGING=false

# Player
SCORM_PLAYER_TIMEOUT=30000
SCORM_PLAYER_DEBUG=false

# API
SCORM_STRICT_MODE=false
SCORM_ERROR_REPORTING=true

# Security
SCORM_ALLOWED_DOMAINS=*
SCORM_CSRF_PROTECTION=true

# Cache
SCORM_CACHE_TTL=3600
```

### Queue Workers

Start queue workers for SCORM processing:

```bash
# Production: Run 2-3 workers
php bin/hyperf.php queue:work scorm-processing --daemon

# Development: Single worker
php bin/hyperf.php queue:work scorm-processing
```

### WebSocket Server

Configure WebSocket server in `config/autoload/server.php`:

```php
'servers' => [
    [
        'name' => 'socket-io',
        'type' => Server::SERVER_WEBSOCKET,
        'host' => '0.0.0.0',
        'port' => 9502,
        'sock_type' => SWOOLE_SOCK_TCP,
        'callbacks' => [
            Event::ON_HAND_SHAKE => [Hyperf\WebSocketServer\Server::class, 'onHandShake'],
            Event::ON_MESSAGE => [Hyperf\WebSocketServer\Server::class, 'onMessage'],
            Event::ON_CLOSE => [Hyperf\WebSocketServer\Server::class, 'onClose'],
        ],
    ],
],
```

### Performance Recommendations

- **Redis Cluster** - For high availability and scalability
- **Queue Workers** - Minimum 2-3 dedicated workers for SCORM processing
- **File Storage** - Use S3 or similar for scalable storage
