<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

/**
 * Turns the answer of a scoring service call (a plain array that holds a couple of Eloquent models) into JSON that can wait
 * in the database, and back again, so a results page can be drawn later from the very same data the old in-request page got.
 *
 * A model is stored as its class and key and found again when the page is drawn (so it is the current row, which only
 * matters for the child's points, and never stale). Only App\Models classes can come back this way.
 */
class OutcomeCodec
{
    private const MARK = '__model';

    public static function encode(array $outcome): string
    {
        return json_encode(self::pack($outcome), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function decode(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? self::unpack($data) : null;
    }

    private static function pack(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return [self::MARK => $value::class, 'id' => $value->getKey()];
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof Arrayable) {
            return self::pack($value->toArray());
        }

        if ($value instanceof JsonSerializable) {
            return self::pack($value->jsonSerialize());
        }

        if (is_array($value)) {
            return array_map(fn ($item) => self::pack($item), $value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    private static function unpack(mixed $value): mixed
    {
        if (is_array($value) && isset($value[self::MARK])) {
            $class = $value[self::MARK];

            // Only our own models: a stored class name is never run as anything else.
            if (is_string($class) && str_starts_with($class, 'App\\Models\\') && is_subclass_of($class, Model::class)) {
                return $class::find($value['id'] ?? null);
            }

            return null;
        }

        if (is_array($value)) {
            return array_map(fn ($item) => self::unpack($item), $value);
        }

        return $value;
    }
}
