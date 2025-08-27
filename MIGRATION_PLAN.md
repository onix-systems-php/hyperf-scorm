# План миграции сервисов SCORM

## Цель
Разделить сервисы по их назначению для улучшения архитектуры и читаемости кода.

## Структура после миграции

### 1. Action/ - Сервисы для контроллеров
```
src/Action/
├── UploadScormPackageAction.php      # Загрузка пакета
├── CreateScormPackageAction.php      # Создание пакета
├── StartScormAttemptAction.php       # Запуск попытки
├── DeleteScormPackageAction.php      # Удаление пакета
├── GetScormPackagesAction.php        # Получение списка пакетов
└── GetScormPackageAction.php         # Получение одного пакета
```

### 2. Service/ - Доменные сервисы (бизнес-логика)
```
src/Service/
├── ScormPlayerService.php            # Плеер SCORM
├── ScormManifestParser.php           # Парсинг манифеста
├── ScormFileProcessor.php            # Обработка файлов
├── ScormValidator.php                # Валидация
├── ScormTrackingService.php          # Отслеживание
├── ScormActivityService.php          # Активность
├── ScormAttemptService.php           # Попытки
├── ScormScoService.php               # SCO
├── ScormDataEnricher.php             # Обогащение данных
└── ScormManifestParserSimple.php     # Простой парсер
```

### 3. Infrastructure/ - Инфраструктурные сервисы
```
src/Infrastructure/
├── ScormLaunchService.php            # Запуск SCORM
├── ScormParserService.php            # Парсинг
└── ScormPackageService.php           # Работа с пакетами
```

### 4. Strategy/ - Стратегии (оставить как есть)
```
src/Strategy/
└── ScormStrategy.php
```

## Шаги миграции

### Шаг 1: Создать новые директории
```bash
mkdir -p src/Action
mkdir -p src/Infrastructure
```

### Шаг 2: Переместить файлы
```bash
# Переместить Action-сервисы
mv src/Service/UploadScormPackageService.php src/Action/UploadScormPackageAction.php
mv src/Service/CreateScormPackageService.php src/Action/CreateScormPackageAction.php
mv src/Service/StartScormAttemptService.php src/Action/StartScormAttemptAction.php
mv src/Service/ScormPackageService.php src/Action/ScormPackageAction.php

# Переместить Infrastructure-сервисы
mv src/Service/ScormLaunchService.php src/Infrastructure/
mv src/Service/ScormParserService.php src/Infrastructure/
```

### Шаг 3: Обновить namespace и классы
- Изменить namespace в перемещенных файлах
- Переименовать классы (Service -> Action для Action-классов)
- Обновить импорты в контроллерах

### Шаг 4: Обновить контроллеры
- Заменить использование Service на Action
- Обновить метод вызова (run() -> execute())

## Преимущества новой архитектуры

1. **Четкое разделение ответственности**
   - Action: одно действие для контроллера
   - Service: бизнес-логика
   - Infrastructure: внешние зависимости

2. **Улучшенная читаемость**
   - Легко понять назначение каждого класса
   - Простая навигация по коду

3. **Соответствие принципам SOLID**
   - Single Responsibility Principle
   - Dependency Inversion Principle

4. **Легкость тестирования**
   - Каждый слой можно тестировать отдельно
   - Простое мокирование зависимостей

## Примеры использования

### В контроллере:
```php
public function upload(RequestUploadScormPackage $request, UploadScormPackageAction $action): ResourceScormPackage
{
    $package = $action->execute(UploadPackageDTO::make($request->validated()));
    return ResourceScormPackage::make($package);
}
```

### Action класс:
```php
class UploadScormPackageAction
{
    public function __construct(
        private readonly UploadScormPackageService $uploadService,
        private readonly ScormValidator $validator
    ) {}

    public function execute(UploadPackageDTO $dto): ScormPackage
    {
        $this->validator->validate($dto);
        return $this->uploadService->run($dto);
    }
}
```


