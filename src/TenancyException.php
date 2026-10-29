<?php

declare(strict_types=1);

namespace EzPhp\Tenancy;

use EzPhp\Contracts\EzPhpException;

/**
 * Class TenancyException
 *
 * Thrown for an invalid tenant id, a misconfigured resolver, or tenant-scoped
 * code running while no tenant is active.
 *
 * @package EzPhp\Tenancy
 */
final class TenancyException extends EzPhpException
{
}
