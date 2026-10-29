<?php

declare(strict_types=1);

namespace Tests\Tenancy;

use EzPhp\Testing\ApplicationTestCase as EzPhpApplicationTestCase;
use RuntimeException;

/**
 * Base class for tenancy module tests that need a bootstrapped Application.
 *
 * Creates a temporary application root containing a config/tenancy.php file
 * that reads from env vars at require-time. Because Config and all service
 * bindings are resolved lazily, env vars set in a test method before the
 * first make() call are picked up correctly.
 *
 * @package Tests\Tenancy
 */
abstract class ApplicationTestCase extends EzPhpApplicationTestCase
{
    /**
     * @return string
     */
    protected function getBasePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-tenancy-test-' . uniqid('', true);
        $configDir = $path . DIRECTORY_SEPARATOR . 'config';

        mkdir($configDir, 0o777, true);

        $content = <<<'PHP'
            <?php

            declare(strict_types=1);

            return [
                'resolver' => getenv('TENANCY_RESOLVER') ?: 'subdomain',
                'base_domain' => getenv('TENANCY_BASE_DOMAIN') ?: 'example.com',
                'hosts' => ['shop.acme.com' => 'acme'],
                'header' => getenv('TENANCY_HEADER') ?: 'X-Tenant',
                'path_segment' => getenv('TENANCY_PATH_SEGMENT') ?: 0,
                'required' => getenv('TENANCY_REQUIRED') ?: 'true',
                'tenants' => ['acme' => ['name' => 'Acme'], 'globex' => []],
                'cache_prefix' => getenv('TENANCY_CACHE_PREFIX') ?: 'tenant:',
            ];
            PHP;

        $result = file_put_contents($configDir . DIRECTORY_SEPARATOR . 'tenancy.php', $content);

        if ($result === false) {
            throw new RuntimeException('Failed to write tenancy.php for test at ' . $configDir);
        }

        return $path;
    }
}
