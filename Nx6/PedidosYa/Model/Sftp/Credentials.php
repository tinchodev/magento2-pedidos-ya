<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Sftp;

/**
 * Per-profile SFTP connection details, built from a ProductsProfile/PromoProfile record
 * (each profile can point at a different SFTP server) rather than shared module config.
 */
final class Credentials
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $username,
        public readonly string $password,
        public readonly string $remotePath,
        public readonly int $timeout
    ) {
    }
}
