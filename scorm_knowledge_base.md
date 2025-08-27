# SCORM Knowledge Base

Централизованная база знаний для разработки SCORM модуля в php-eco-support-backend проекте.

---

## Обзор Проекта

**Проект:** PHP SCORM модуль для hyperf-scorm пакета  
**Архитектура:** Clean Architecture + DDD принципы  
**Фреймворк:** Hyperf PHP 8.2+ со Swoole корутинами  
**База данных:** PostgreSQL с JSON полями  
**Стандарты кода:** PSR-12, PHPStan level 0, strict_types=1

---

## Архитектура SCORM

### Подтвержденная Flow архитектура:
**Package → SCOs → Player** 

1. **ScormPackage** - основной контейнер с metadata
2. **ScormSco** - отдельные обучающие объекты (Sharable Content Objects)
3. **ScormPlayer** - интерфейс для запуска конкретного SCO
4. **ScormAttempt** - прогресс пользователя по пакету
5. **ScormTracking** - детальный трекинг CMI данных (опционально)

### Поддерживаемые стандарты:
- **SCORM 1.2** (CAM 1.3) - legacy поддержка
- **SCORM 2004** (3rd/4th Edition) - основной стандарт
- **LOM Metadata** - образовательные метаданные
- **Sequencing & Navigation** - SCORM 2004 управление порядком

---

## Database Schema (Финальная архитектура)

### scorm_packages (основная таблица)
```sql
CREATE TABLE scorm_packages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(500) NOT NULL,
    identifier VARCHAR(255) UNIQUE NOT NULL,
    scorm_version ENUM('1.2', '2004') DEFAULT '2004',
    manifest_path VARCHAR(500) NOT NULL,
    content_path VARCHAR(500) NOT NULL,
    manifest_data JSON NULL,
    -- стандартные поля: created_at, updated_at, deleted_at
);
```

### scorm_scos (ОБЯЗАТЕЛЬНО для SCORM стандарта)
```sql
CREATE TABLE scorm_scos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    package_id BIGINT NOT NULL,
    identifier VARCHAR(255) NOT NULL,        -- "r1", "resource_1"
    title VARCHAR(500) NOT NULL,             -- "Golf Explained"
    launch_url VARCHAR(500) NOT NULL,        -- "shared/launchpage.html"
    mastery_score DECIMAL(5,2) NULL,         -- 0.80 (80%)
    objectives JSON NULL,                    -- ["obj_etiquette", "obj_handicapping"]
    sequencing_data JSON NULL,              -- SCORM 2004 sequencing rules
    FOREIGN KEY (package_id) REFERENCES scorm_packages(id) ON DELETE CASCADE
);
```

### scorm_attempts (пользовательский прогресс)
```sql
CREATE TABLE scorm_attempts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    package_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    status ENUM('not_attempted', 'incomplete', 'completed', 'passed', 'failed', 'browsed'),
    score DECIMAL(5,2) NULL,
    cmi_data JSON NULL,  -- Все CMI данные в одном поле
    -- стандартные поля
);
```

---

## Ключевые Классы и Их Роли

### Service Layer

#### ScormManifestParser
- **Назначение:** Парсинг imsmanifest.xml файлов
- **Поддержка:** SCORM 1.2 и 2004 стандарты
- **Технологии:** SimpleXML (не DOMDocument)
- **Методы детекции версии:**
  1. PRIMARY: `schemaversion` элемент в metadata  
  2. SECONDARY: `version` атрибут манифеста
  3. TERTIARY: XML namespaces анализ

**Примеры версий в манифестах:**
- SCORM 1.2: `<schemaversion>CAM 1.3</schemaversion>`
- SCORM 2004: `<schemaversion>2004 3rd Edition</schemaversion>`

#### ScormFileProcessor
- **Назначение:** Обработка ZIP пакетов и загрузка в Storage
- **Поддержка:** S3, локальное хранилище через Flysystem
- **Временные файлы:** `sys_get_temp_dir()` + cleanup
- **Валидация:** Структура пакета, наличие imsmanifest.xml

#### ScormValidator
- **Назначение:** Валидация SCORM пакетов и манифестов
- **Проверки:** 
  - Манифест структура и обязательные поля
  - SCO элементы и их корректность
  - Файловая структура пакета
  - Версионная совместимость

### DTO Layer

#### ScormManifestDTO
- **Назначение:** Типизированное представление манифеста
- **Ключевые методы:**
  - `getScoItems()` - извлечение всех SCO из organizations
  - `getLaunchUrl()` - построение URL для запуска SCO
  - `getResource()` - поиск ресурса по identifier
  - `isMultiSco()` - проверка на множественные SCO

#### ScormValidationResultDTO
- **Назначение:** Результат валидации с ошибками/предупреждениями
- **Статусы:** valid, valid_with_warnings, invalid
- **Методы:** форматирование для JSON, подсчет ошибок

---

## Реальные Примеры Манифестов

### SCORM 1.2 Example (CAM 1.3):
```xml
<manifest identifier="LdE29WTAjx5ewKNgBIwZsY1p4Yxxj-UsNhn7Fpa_" version="1.3">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>CAM 1.3</schemaversion>
  </metadata>
  <organizations default="B0">
    <organization identifier="B0">
      <title>Incorporating Trauma-Informed Care Into HIV Practices</title>
      <item identifier="i1" identifierref="r1">
        <title>Incorporating Trauma-Informed Care Into HIV Practices</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="r1" type="webcontent" adlcp:scormType="sco" href="scormdriver/indexAPI.html">
      <!-- files list -->
    </resource>
  </resources>
</manifest>
```

### SCORM 2004 Example (3rd Edition):
```xml
<manifest identifier="com.scorm.golfsamples.runtime.advancedruntime.20043rd" version="1">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>2004 3rd Edition</schemaversion>
  </metadata>
  <organizations default="golf_sample_default_org">
    <organization identifier="golf_sample_default_org">
      <title>Golf Explained - Run-time Advanced Calls</title>
      <item identifier="item_1" identifierref="resource_1">
        <title>Golf Explained</title>
        <imsss:sequencing>
          <imsss:primaryObjective objectiveID="PRIMARYOBJ" satisfiedByMeasure="true">
            <imsss:minNormalizedMeasure>0.8</imsss:minNormalizedMeasure>
          </imsss:primaryObjective>
          <imsss:objective objectiveID="obj_etiquette"></imsss:objective>
          <imsss:objective objectiveID="obj_handicapping"></imsss:objective>
        </imsss:sequencing>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="resource_1" type="webcontent" adlcp:scormType="sco" href="shared/launchpage.html">
      <!-- files list -->
    </resource>
  </resources>
</manifest>
```

---

## Конфигурация (scorm.php)

### Текущая конфигурация:
```php
return [
    'storage' => [
        'default' => env('SCORM_STORAGE_DRIVER', 's3'),
        'base_path' => env('SCORM_STORAGE_BASE_PATH', 'scorm-packages'),
    ],
    'upload' => [
        'max_file_size' => env('SCORM_MAX_FILE_SIZE', 100 * 1024 * 1024), // 100MB
        'allowed_extensions' => ['zip'],
        'temp_disk' => env('SCORM_TEMP_DISK', 'scorm_temp'),
    ],
    'manifest' => [
        'required_files' => ['imsmanifest.xml'],
        'max_scos' => env('SCORM_MAX_SCOS', 50),
    ],
    'player' => [
        'api_endpoint' => env('SCORM_API_ENDPOINT', '/api/v1/scorm/api'),
        'timeout' => env('SCORM_API_TIMEOUT', 30000),
    ],
    'tracking' => [
        'store_detailed_logs' => env('SCORM_DETAILED_LOGS', true),
        'max_suspend_data_length' => [
            '1.2' => 4096,   // SCORM 1.2 limit
            '2004' => 64000, // SCORM 2004 limit
        ],
    ],
];
```

---

## Development Plans Archive

### План 1: Database Schema (✅ Завершен)
- Создание 3-4 миграций для SCORM
- scorm_packages, scorm_scos, scorm_attempts, scorm_tracking
- Подтверждение архитектуры Package → SCOs → Player

### План 2: SCO Creation Fix (✅ Завершен)
- Исправление UploadScormPackageService - раскомментирование SCO создания
- Repository метод `createScos()` с использованием `createMany()`
- Добавление `getScoItems()` в ScormManifestDTO

### План 3: Upload Optimization (🔄 В процессе)
- ScormStorageUploadService с Hyperf coroutines
- Параллельная загрузка файлов батчами
- Конфигурируемые лимиты вместо жестких (500MB, 2000 files)
- Расширение scorm.php конфигурации

### План 4: Manifest Parser Improvements (📋 Новый план)
**Статус:** В планировании  
**Приоритет:** Средний

---

## Анализ Текущего Парсера Манифестов

### Сильные стороны:
1. ✅ **Правильная архитектура:** SimpleXML вместо DOMDocument
2. ✅ **Многоуровневая детекция версий:** schema → version → namespaces
3. ✅ **Полная поддержка стандартов:** SCORM 1.2 и 2004
4. ✅ **Безопасность:** libxml error handling
5. ✅ **Рекурсивный парсинг:** поддержка вложенных items
6. ✅ **Namespace handling:** корректная работа с SCORM namespace'ами

### Возможности для улучшения:

#### 1. **Более точная детекция версий**
Текущая логика может неправильно определить гибридные манифесты:
```php
// Проблема: version="1.3" определяется как SCORM 2004, хотя это может быть SCORM 1.2 CAM 1.3
if (version_compare($manifestVersion, '1.3', '>=')) {
    return ScormVersionEnum::SCORM_2004; // Неточно!
}
```

#### 2. **Отсутствует кэширование парсинга**
Большие манифесты парсятся каждый раз заново без кэширования.

#### 3. **Неполная поддержка SCORM 2004 sequencing**
- Отсутствует парсинг `<imsss:sequencing>` правил
- Нет обработки `<imsss:objectives>` структур
- Отсутствует навигационная информация `<adlnav>`

#### 4. **Ограниченная обработка LOM метаданных**
Парсится только базовая LOM информация, нет полной поддержки.

#### 5. **Нет поддержки многоязычности**
Отсутствует обработка `<langstring>` элементов для i18n.

#### 6. **Отсутствует валидация при парсинге**
Парсер не валидирует обязательные поля по ходу работы.

---

## План 4: Улучшения Парсера Манифестов

### Цели:
1. **Улучшить точность детекции версий** SCORM
2. **Добавить поддержку SCORM 2004 sequencing** правил
3. **Расширить LOM метаданные** обработку
4. **Добавить кэширование** для больших манифестов
5. **Улучшить многоязычную** поддержку
6. **Интегрировать валидацию** в процесс парсинга

### Предлагаемые изменения:

#### 1. Улучшенная детекция версий
```php
private function detectScormVersionAdvanced(\SimpleXMLElement $xml): ScormVersionEnum
{
    // 1. PRIORITY: Анализ namespace + schemaversion комбинации
    $namespaces = $xml->getNamespaces(true);
    $schemaVersion = $this->getSchemaVersion($xml);
    
    // Если есть SCORM 2004 namespaces + соответствующая версия
    if ($this->hasScorm2004Namespaces($namespaces) && 
        str_contains(strtolower($schemaVersion), '2004')) {
        return ScormVersionEnum::SCORM_2004;
    }
    
    // CAM 1.3 это SCORM 1.2, не 2004!
    if (str_contains(strtolower($schemaVersion), 'cam')) {
        return ScormVersionEnum::SCORM_12;
    }
    
    // 2. SECONDARY: Детальный анализ features
    return $this->detectByFeatures($xml);
}
```

#### 2. Расширенный парсинг SCORM 2004 sequencing
```php
private function parseScorm2004Sequencing(\SimpleXMLElement $item): array
{
    $sequencing = [];
    
    if (isset($item->{'imsss:sequencing'})) {
        $seq = $item->{'imsss:sequencing'};
        
        // Control Mode
        if (isset($seq->{'imsss:controlMode'})) {
            $sequencing['controlMode'] = [
                'choice' => (string) $seq->{'imsss:controlMode'}['choice'] === 'true',
                'choiceExit' => (string) $seq->{'imsss:controlMode'}['choiceExit'] === 'true',
                'flow' => (string) $seq->{'imsss:controlMode'}['flow'] === 'true',
                'forwardOnly' => (string) $seq->{'imsss:controlMode'}['forwardOnly'] === 'true'
            ];
        }
        
        // Objectives
        if (isset($seq->{'imsss:objectives'})) {
            $sequencing['objectives'] = $this->parseSequencingObjectives($seq->{'imsss:objectives'});
        }
        
        // Delivery Controls
        if (isset($seq->{'imsss:deliveryControls'})) {
            $sequencing['deliveryControls'] = $this->parseDeliveryControls($seq->{'imsss:deliveryControls'});
        }
    }
    
    return $sequencing;
}
```

#### 3. Кэширование парсинга
```php
public function parseWithCache(string $manifestPath): ScormManifestDTO
{
    $cacheKey = 'scorm_manifest:' . md5($manifestPath . filemtime($manifestPath));
    
    if ($cached = $this->cache->get($cacheKey)) {
        return $cached;
    }
    
    $manifest = $this->parse($manifestPath);
    $this->cache->set($cacheKey, $manifest, 3600); // 1 hour cache
    
    return $manifest;
}
```

#### 4. Многоязычная поддержка
```php
private function parseLangString(\SimpleXMLElement $element): array
{
    $langStrings = [];
    
    if (isset($element->langstring)) {
        foreach ($element->langstring as $langstring) {
            $lang = (string) $langstring['lang'] ?: 'und';
            $langStrings[$lang] = (string) $langstring;
        }
    }
    
    return $langStrings ?: ['und' => (string) $element];
}
```

#### 5. Интегрированная валидация
```php
private function parseAndValidateResource(\SimpleXMLElement $resource): array
{
    $resourceData = [
        'identifier' => (string) $resource['identifier'] ?? '',
        'type' => (string) $resource['type'] ?? '',
        'href' => (string) $resource['href'] ?? '',
        // ... другие поля
    ];
    
    // Валидация на месте
    if (empty($resourceData['identifier'])) {
        throw new ScormParsingException('Resource identifier is required');
    }
    
    if (empty($resourceData['href'])) {
        throw new ScormParsingException("Resource '{$resourceData['identifier']}' must have href");
    }
    
    return $resourceData;
}
```

### Файлы для изменения:
1. **ScormManifestParser.php** - основные улучшения парсинга
2. **ScormManifestDTO.php** - дополнительные методы для sequencing
3. **ScormValidator.php** - интеграция с парсером
4. **scorm.php** - настройки кэширования

### Ожидаемые результаты:
- **🎯 Точность детекции:** 99% правильное определение SCORM версий
- **⚡ Производительность:** кэширование больших манифестов
- **🌐 i18n поддержка:** корректная обработка многоязычных пакетов
- **📋 SCORM 2004:** полная поддержка sequencing и navigation
- **🔍 Валидация:** раннее обнаружение ошибок в процессе парсинга

---

## Критические Найденные Проблемы

### 1. SCO Creation Bug (✅ Исправлен в План 2)
**Файл:** `UploadScormPackageService.php:92-117`  
**Проблема:** SCO создание закомментировано, нарушает Package → SCOs → Player flow

### 2. Missing getScoItems() method (✅ Исправлен в План 2)
**Файл:** `ScormManifestDTO.php`  
**Проблема:** Метод `getScoItems()` существует, но нуждается в улучшениях для Plan 4

### 3. Hard-coded limits (🔄 Исправляется в План 3)
**Файлы:** `ScormValidator.php`, конфигурация  
**Проблема:** 500MB, 2000 files лимиты жестко прописаны

---

## Development Rules Compliance

### Обязательные стандарты:
- **PHP 8.2+** с `declare(strict_types=1)`
- **Readonly constructors** для DTOs
- **FormRequest validation** для всех API входов
- **Resource classes** с OpenAPI документацией
- **Single Responsibility** для всех сервисов
- **Repository pattern** для database access

### Naming Conventions:
- Controllers: `{Entity}Controller`
- Services: `{Action}{Entity}Service`
- Repositories: `{Entity}Repository`
- Models: `{Entity}`
- DTOs: `{Entity}DTO`
- Requests: `Request{Action}{Entity}`
- Resources: `Resource{Entity}`

---

## Environment Variables

### Storage Configuration:
```bash
SCORM_STORAGE_DRIVER=s3
SCORM_S3_BUCKET=my-scorm-bucket
SCORM_STORAGE_BASE_PATH=scorm-packages
```

### Upload Configuration:
```bash
SCORM_MAX_FILE_SIZE=104857600  # 100MB
SCORM_MAX_SCOS=50
SCORM_TEMP_DISK=scorm_temp
```

### API Configuration:
```bash
SCORM_API_ENDPOINT=/api/v1/scorm/api
SCORM_API_TIMEOUT=30000
SCORM_DEBUG=false
```

### Tracking Configuration:
```bash
SCORM_DETAILED_LOGS=true
SCORM_AUTO_COMMIT_INTERVAL=30
```

---

## Testing Strategy

### Unit Tests:
- **ScormManifestParser** - различные типы манифестов
- **ScormValidator** - валидация правил
- **ScormFileProcessor** - ZIP обработка
- **DTOs** - типизация и методы

### Integration Tests:
- **Complete upload flow** - ZIP → Parse → Store → SCO create
- **Player generation** - Package → SCOs → Player URLs
- **Storage integration** - S3 и local filesystem

### Test Data:
- Реальные SCORM пакеты из `examples/manifest/`
- Mock factories для стандартных данных
- Corrupted packages для negative testing

---

## Контрольные Точки (Checkpoints)

### ✅ Завершенные задачи:
1. **Database Schema** - 3 миграции созданы и протестированы
2. **SCO Creation Fix** - Repository метод `createScos()` реализован
3. **Plans Documentation** - Центральная система планов в `scorm_plans.md`

### 🔄 Текущая работа:
1. **Upload Optimization** - Coroutines и конфигурируемые лимиты
2. **Code Compliance Review** - Проверка всех файлов на соответствие правилам

### 📋 Запланированные задачи:
1. **Manifest Parser Improvements** - План 4 готов к выполнению
2. **API Endpoints** - SCORM player и tracking API
3. **Frontend Integration** - JavaScript SCORM API client

---

## Заключение

Этот knowledge base содержит все критические знания для продолжения разработки SCORM модуля. Использовать как справочник для:

1. **Архитектурных решений** - подтвержденные паттерны и flow
2. **Технических деталей** - конфигурация, база данных, API
3. **Реальных примеров** - манифесты и их структуры  
4. **Планов развития** - детальные планы с временными метками
5. **Стандартов качества** - development rules и compliance

**Дата создания:** 31.07.2025  
**Последнее обновление:** 31.07.2025  
**Статус:** Актуален, готов к использованию
