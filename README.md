# Hyperf SCORM Package

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net)
[![Hyperf Version](https://img.shields.io/badge/hyperf-%5E3.1-brightgreen.svg)](https://hyperf.io)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Enterprise-grade SCORM package for Hyperf with Gateway architecture, async processing, and real-time WebSocket progress tracking.

## Features

- ✅ **SCORM 1.2 & 2004** - Full compliance, auto-detect version
- ✅ **Gateway Architecture** - Clean separation of concerns, stable public API
- ✅ **Async Processing** - Queue-based handling with configurable threshold
- ✅ **Real-time Progress** - WebSocket notifications with fallback
- ✅ **Customizable Auth** - Published controllers for flexible authentication integration
- ✅ **Flexible Storage** - S3, local storage, or custom drivers

## Requirements

- PHP 8.2+ (8.1 supported), Hyperf 3.1.42+
- Redis, PostgreSQL 13+ or MySQL 8.0+
- Swoole 5.0+

## Installation

### Step 1: Install Package

```bash
composer require onix-systems-php/hyperf-scorm
```

### Step 2: Publish Configuration

```bash
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_config
```

### Step 3: Publish Migrations

```bash
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_migrations
```

### Step 4: Publish Controller (REQUIRED)

```bash
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_controller
```

This publishes `ScormController` to `app/Scorm/Controller/ScormController.php` where you'll integrate your authentication system and configure authorization (ACL).

⚠️ **Important:** You MUST customize the published controller to integrate your authentication before using in production.

### Step 5: Publish Example Files (Optional)

```bash
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-scorm --id=scorm_example
```

### Step 6: Run Migrations

```bash
php bin/hyperf.php migrate
```

### Step 7: Register Routes

Add to `config/routes.php`:

```php
// Package-provided API routes (SCORM player, job status, WebSocket)
require_once './vendor/onix-systems-php/hyperf-scorm/publish/routes.php';

// Your published controller routes are auto-registered via Controller annotation
```

## Quick Start

### 1. Configure Environment

```env
# Essential settings
SCORM_STORAGE_DRIVER=local           # s3|local
SCORM_LOCAL_PATH=storage/public/scorm
SCORM_LOCAL_PUBLIC_URL=http://localhost/public/scorm
SCORM_MAX_FILE_SIZE=600              # MB
SCORM_API_ENDPOINT=/v1/api/scorm
SCORM_WS_NAME=socket-io
SCORM_DEBUG=false

# Processing
SCORM_AUTO_COMMIT_INTERVAL=30        # seconds
SCORM_CACHE_TTL=3600                 # seconds
```

### 2. Integrate Authentication

Edit published `app/Scorm/Controller/ScormController.php`:

```php
use OnixSystemsPHP\HyperfAuth\AuthManager;
use OnixSystemsPHP\HyperfAuth\Annotation\Acl;
use App\User\Constants\UserRoles;

#[Controller(prefix: 'v1/scorm')]
class ScormController extends AbstractController
{
    public function __construct(
        private readonly ScormGatewayInterface $scormGateway,
        private readonly AuthManager $authManager  // Add your auth
    ) {}

    #[PostMapping(path: 'packages/upload')]
    #[Acl(roles: [UserRoles::GROUP_ADMINS])]  // Your ACL rules
    public function upload(RequestUploadScormPackage $request): ResourceScormAsyncJob
    {
        // Extract userId from your auth system
        $userId = $this->authManager->user()->id;

        // Use gateway - business logic abstracted
        $jobDTO = $this->scormGateway->upload(
            ScormUploadDTO::make($request),
            $userId
        );

        return ResourceScormAsyncJob::make($jobDTO);
    }
}
```

### 3. Upload Package

```bash
curl -X POST https://your-api.com/v1/scorm/packages/upload \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@package.zip" \
  -F "title=Introduction Course" \
  -F "description=Basic training module"
```

**Response:**
```json
{
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

### 4. Track Progress (WebSocket)

```javascript
const jobId = response.data.job_id;
const ws = new WebSocket(
  `ws://your-domain:9502/scorm-progress?job_id=${jobId}`
);

ws.onmessage = (event) => {
  const { stage, progress, status, package_id } = JSON.parse(event.data).data;
  console.log(`${stage}: ${progress}%`);

  if (status === 'completed') {
    console.log(`Package ready: ${package_id}`);
    ws.close();
  }
};
```

### 5. Launch SCORM Player

```bash
curl -X GET https://your-api.com/v1/scorm/player/123/launch \
  -H "Authorization: Bearer YOUR_TOKEN"

# Returns HTML player page with embedded SCORM content
```

## Gateway API

The package uses Gateway pattern to provide a clean, stable public API. The `ScormGatewayInterface` extends three specialized interfaces for focused responsibilities:

```php
interface ScormGatewayInterface extends
    ScormPlayerGatewayInterface,      // Launch player
    ScormProgressGatewayInterface,     // Job tracking
    ScormPackageGatewayInterface       // Package management
{
}
```

### ScormPackageGatewayInterface

**Upload SCORM package (async):**
```php
upload(ScormUploadDTO $dto, int $userId): ScormAsyncJobDTO
```

**List packages with pagination:**
```php
index(array $filters, PaginationRequestDTO $pagination): PaginationResultDTO
```

**Delete package and all related data:**
```php
destroy(int $packageId): ScormPackage
```

### ScormPlayerGatewayInterface

**Launch SCORM player:**
```php
launch(int $packageId): ScormPlayerDTO
```

Returns `ScormPlayerDTO`:
- `package` (ScormPackageDTO): Package information
- `playerHtml` (string): Complete HTML player page

### ScormProgressGatewayInterface

**Get job status:**
```php
statusJob(string $jobId): ResourceScormJobStatus
```

**Cancel queued job:**
```php
cancelJob(string $jobId): array
```

### Usage Example

```php
use OnixSystemsPHP\HyperfScorm\Contract\Gateway\ScormGatewayInterface;

class YourController
{
    public function __construct(
        private readonly ScormGatewayInterface $scormGateway
    ) {}

    public function someAction()
    {
        // Upload package
        $jobDTO = $this->scormGateway->upload($dto, $userId);

        // List packages
        $result = $this->scormGateway->index($filters, $pagination);

        // Launch player
        $playerDTO = $this->scormGateway->launch($packageId);

        // Track job
        $status = $this->scormGateway->statusJob($jobId);
    }
}
```

## Configuration

All configuration is in `config/autoload/scorm.php` (published in installation step 2).

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
            'port' => 9502,  // NOTICE: This port must be open on the server
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

## API Endpoints

### Published Controller Endpoints

Managed by your published `app/Scorm/Controller/ScormController.php`:

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/v1/scorm/packages/upload` | Upload SCORM package | Required |
| GET | `/v1/scorm/packages` | List packages (paginated) | Required |
| GET | `/v1/scorm/player/{id}/launch` | Launch SCORM player | Required |
| DELETE | `/v1/scorm/packages/{id}` | Delete package | Required |
| GET | `/v1/scorm/jobs/{jobId}/status` | Get job status | Required |
| POST | `/v1/scorm/jobs/{jobId}/cancel` | Cancel job | Required |

**Note:** You control auth/ACL in your published controller.

### Package-Provided Endpoints

Internal API routes (auto-registered via `publish/routes.php`):

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/v1/api/scorm/{packageId}/users/{userId}/initialize` | Initialize SCORM session | None (internal) |
| POST | `/v1/api/scorm/{packageId}/commit/{sessionToken}` | Save SCORM data | None (token-based) |
| WS | `ws://host:9502/scorm-progress?job_id={id}` | Real-time progress | None (jobId-based) |

**Note:** Internal endpoints use session tokens/job IDs for security, not user auth.

## SCORM Support

| Feature | SCORM 1.2 | SCORM 2004 |
|---------|:---------:|:----------:|
| Manifest Parsing | ✅ | ✅ |
| Auto Version Detection | ✅ | ✅ |
| Multiple SCOs | ✅ | ✅ |
| CMI Data Model | ✅ | ✅ |
| Sequencing Rules | ❌ | ✅ |
| Interactions | ✅ | ✅ |

**Supported schemaversion:**
- **1.2**: `1.2`, `CAM 1.2`
- **2004**: `CAM 1.3`, `2004`, `2004 3rd Edition`, `2004 4th Edition`

## Troubleshooting

### Updating from hyperf-core 1.2 to 1.3

If you have an older version of the `hyperf-core` package (1.2) installed, you need to update it along with the `plain-to-class` library:

```bash
composer update onix-systems-php/hyperf-core yzen.dev/plain-to-class
```

This ensures compatibility between the core package and the SCORM package dependencies.

### UploadedFile Type Conversion Issue (plain-to-class v3.0+)

If you're using `yzen.dev/plain-to-class` v3.0+ and encounter a class validation error with `UploadedFile` type (e.g., `Hyperf\HttpMessage\Upload\UploadedFile`), you need to add a custom setter in your DTO.

**Solution:**

Add a custom setter for the `UploadedFile` field in your DTO class:

```php
use Hyperf\HttpMessage\Upload\UploadedFile;

class YourDTO
{
    public UploadedFile $file;

    public function setFileAttribute(UploadedFile $value): void
    {
        $this->file = $value;
    }
}
```

For more details, see the [plain-to-class custom setter documentation](https://github.com/yzen-dev/plain-to-class/tree/feature/v3.0.0-dev?tab=readme-ov-file#custom-setter).

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
