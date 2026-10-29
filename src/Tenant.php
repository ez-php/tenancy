<?php

declare(strict_types=1);

namespace EzPhp\Tenancy;

/**
 * Class Tenant
 *
 * Immutable tenant value: an id plus free-form attributes from the tenant store.
 *
 * Ids are restricted to a DNS label — lowercase letters, digits and hyphens,
 * 1–63 characters, no leading/trailing hyphen. The id ends up in cache key
 * prefixes (`tenant:<id>:`) and search index names (`<id>_<index>`); with no
 * `:` or `_` allowed, two tenants can never produce the same prefix, and every
 * search backend accepts the result.
 *
 * @package EzPhp\Tenancy
 */
final readonly class Tenant
{
    private const string ID_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /**
     * @param string               $id
     * @param array<string, mixed> $attributes
     *
     * @throws TenancyException When $id is not a valid tenant id.
     */
    public function __construct(
        public string $id,
        public array $attributes = [],
    ) {
        if (!self::isValidId($id)) {
            throw new TenancyException(sprintf(
                'Invalid tenant id "%s": use 1-63 lowercase letters, digits or inner hyphens.',
                $id,
            ));
        }
    }

    /**
     * Whether $id is a valid tenant id.
     *
     * @param string $id
     *
     * @return bool
     */
    public static function isValidId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    /**
     * Return one attribute, or $default when it is absent.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }
}
