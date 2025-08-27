<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Cast;

use Hyperf\Contract\CastsAttributes;
use InvalidArgumentException;
use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

abstract class DtoCast implements CastsAttributes
{
    /**
     * @return class-string<AbstractDTO>
     */
    abstract protected function dtoClass(): string;

    /**
     * @param  mixed  $value
     */
    public function get(
        $model,
        string $key,
        $value,
        array $attributes,
    ): ?AbstractDTO {
        if (!$value) {
            return null;
        }

        $dtoClass = $this->dtoClass();

        if (!is_subclass_of($dtoClass, AbstractDTO::class)) {
            throw new InvalidArgumentException(
                "The dtoClass (" .
                    $dtoClass .
                    ") should be a subclass of " .
                    AbstractDTO::class,
            );
        }

        return $dtoClass::make(json_decode($value, true));
    }

    /**
     * @param  mixed  $value
     */
    public function set($model, string $key, $value, array $attributes): string
    {
        $dtoClass = $this->dtoClass();

        if (is_array($value)) {
            return json_encode($value);
        }

        if (!$value instanceof $dtoClass) {
            throw new InvalidArgumentException(
                "The given value is not an instance of AbstractDTO and " .
                    $dtoClass,
            );
        }
        /** @var AbstractDTO $value */

        return json_encode($value->toArray());
    }
}
