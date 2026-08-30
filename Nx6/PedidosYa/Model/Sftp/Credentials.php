<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Sftp;

/**
 * Per-profile SFTP connection details, built from a ProductsProfile/PromoProfile record
 * (each profile can point at a different SFTP server) rather than shared module config.
 */
final readonly class Credentials
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public string $remotePath,
        public int $timeout
    ) {
    }
}
