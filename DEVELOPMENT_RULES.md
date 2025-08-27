# SCORM Module - Development Rules & Standards

## 📝 Правила разработки (на основе анализа Course модуля)

### 1. **Структура и naming conventions**
- **Controllers:** `{Entity}Controller.php` (ScormPackageController.php)
- **Services:** `{Action}{Entity}Service.php` (CreateScormPackageService.php)
- **Repositories:** `{Entity}Repository.php` (ScormPackageRepository.php)
- **Models:** `{Entity}.php` (ScormPackage.php)
- **DTOs:** `{Action}{Entity}DTO.php` (CreateScormPackageDTO.php)
- **Requests:** `Request{Action}{Entity}.php` (RequestCreateScormPackage.php)
- **Resources:** `Resource{Entity}.php` (ResourceScormPackage.php)
- **Constants:** `{Context}Types.php` или `{Entity}Statuses.php`

### 2. **Архитектурные паттерны**

#### Controllers (тонкие):
```php
public function create(
    RequestCreateScormPackage $request,      // FormRequest валидация
    CreateScormPackageService $service       // DI сервиса
): ScormPackageResource {                    // Typed return
    return ScormPackageResource::make(
        $service->run(CreateScormPackageDTOFactory::make($request))
    );
}
```

#### Services (один сервис = одно действие):
```php
#[Service]
class CreateScormPackageService 
{
    public function __construct(
        private readonly RepositoryInterface $repository
    ) {}
    
    #[Transactional(attempts: 1)]
    public function run(CreateScormPackageDTO $dto): ScormPackage
    {
        // Валидация, бизнес-логика, создание
    }
}
```

#### Constructors:
```php
public function __construct(
    private readonly ServiceType $service  // ВСЕГДА readonly
) {}
```

### 3. **Типизация - ВЕЗДЕ**
- `declare(strict_types=1);` во всех файлах
- Все методы имеют return types
- Все свойства классов типизированы
- `?Type` для nullable значений

### 4. **Валидация через FormRequest**
```php
class RequestCreateScormPackage extends FormRequest
{
    public function rules(): array { /* ... */ }
    public function messages(): array { /* ... */ }
}
```

### 5. **DTO Pattern**
```php
class CreateScormPackageDTO extends AbstractDTO
{
    public function __construct(
        public string $title,
        public string $identifier,
        // ... строго типизированные свойства
    ) {}
}
```

### 6. **Constants**
```php
final class ScormVersions
{
    public const SCORM_12 = '1.2';
    public const SCORM_2004 = '2004';
    
    public const ALL = [self::SCORM_12, self::SCORM_2004];
    public const LABELS = [...];
}
```

### 7. **Dependency Injection**
- Конструктор injection для всех зависимостей
- `#[Service]` аннотация для автоматической регистрации
- Интерфейсы для слабого связывания

### 8. **Single Responsibility**
- Один сервис = одно действие
- Репозитории только для доступа к данным  
- Контроллеры только для роутинга

### 9. **Resource Classes Pattern**
```php
class ResourceScormPackage extends AbstractResource
{
    /**
     * @method __construct(ScormPackage $resource)
     * @property ScormPackage $resource
     */
    
    // ОБЯЗАТЕЛЬНО полная OpenAPI документация
    #[OA\Schema(
        schema: 'ResourceScormPackage',
        properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'scorm_version', type: 'string', enum: ['1.2', '2004']),
            // ... все поля с типами и енумами
        ],
        type: 'object',
    )]
    
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'version' => $this->resource->version,
            'identifier' => $this->resource->identifier,
            'manifest_path' => $this->resource->manifest_path,
            'content_path' => $this->resource->content_path,
            'manifest_data' => $this->resource->manifest_data,
            'scorm_version' => $this->resource->scorm_version,
            'launch_url' => $this->resource->getLaunchUrl(),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
```

**Ключевые принципы Resource:**
- Наследуется от `AbstractResource`
- PHPDoc с `@method` и `@property` для IDE
- Полная OpenAPI схема через атрибуты
- `toArray()` метод для трансформации
- **Простое возвращение всех полей** без условной логики
- Правильное форматирование дат
- Использование констант в enum для OpenAPI

## 🚨 ВАЖНЫЕ ПРАВИЛА

1. **НЕ создавать "большие" сервисы** - разделять на отдельные действия
2. **ВСЕГДА использовать FormRequest** для HTTP валидации
3. **ВСЕГДА типизировать** параметры и return types
4. **ВСЕГДА использовать readonly** в конструкторах
5. **Следовать naming conventions** строго
6. **Документировать через OpenAPI** аннотации
7. **Транзакции для критичных операций** через `#[Transactional]`
8. **КРИТИЧЕСКИ ВАЖНО: При любом изменении кода - проходить по ВСЕМУ коду и заменять везде!** Если исправляешь константы/классы/интерфейсы - найти все использования и заменить.

## 📁 Структура модуля SCORM
```
OnixSystemsPHP/HyperfScorm/
├── Constants/      # Константы
├── Controller/     # HTTP контроллеры  
├── DTO/           # Data Transfer Objects
│   └── Factory/   # DTO фабрики
├── Request/       # Form Request валидация
├── Resource/      # API Resources
├── Service/       # Бизнес логика (один сервис = одно действие)
├── Repository/    # Доступ к данным
├── Model/         # Eloquent модели
└── Cast/          # Model casters
```

Эти правила ОБЯЗАТЕЛЬНЫ для соблюдения единообразия с проектом!
