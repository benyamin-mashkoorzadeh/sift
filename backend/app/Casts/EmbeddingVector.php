<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<list<float>|null, list<int|float>|null>
 */
class EmbeddingVector implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<float>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $components = trim((string) $value, '[]');

        if ($components === '') {
            return [];
        }

        return array_map(
            static fn (string $component): float => (float) $component,
            explode(',', $components),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException('An embedding must be a non-empty numeric array.');
        }

        $components = array_map(function (mixed $component): string {
            if (! is_numeric($component) || ! is_finite((float) $component)) {
                throw new InvalidArgumentException('Every embedding component must be a finite number.');
            }

            return (string) (float) $component;
        }, $value);

        return '['.implode(',', $components).']';
    }
}
