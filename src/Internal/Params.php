<?php

declare(strict_types=1);

namespace Formable\Internal;

use DateTimeInterface;

final class Params
{
    public static function encodePath(string $value): string
    {
        return rawurlencode($value);
    }

    public static function updatedSince(string|DateTimeInterface|null $updatedSince): ?string
    {
        if ($updatedSince instanceof DateTimeInterface) {
            return $updatedSince->format(DATE_ATOM);
        }

        return $updatedSince;
    }

    public static function dropNone(array $mapping): array
    {
        return array_filter($mapping, static fn ($value) => $value !== null);
    }

    public static function toApiFields(?array $fields): ?array
    {
        if ($fields === null) {
            return null;
        }

        return array_map(
            static fn (array $field) => self::renameKey($field, 'field_id', 'fieldId'),
            $fields,
        );
    }

    public static function toApiMembers(array $members): array
    {
        return array_map(
            static fn (array $member) => self::renameKey($member, 'display_name', 'displayName'),
            $members,
        );
    }

    private static function renameKey(array $mapping, string $source, string $target): array
    {
        if (!array_key_exists($source, $mapping)) {
            return $mapping;
        }

        $renamed = [];
        foreach ($mapping as $key => $value) {
            $renamed[$key === $source ? $target : $key] = $value;
        }

        return $renamed;
    }
}
