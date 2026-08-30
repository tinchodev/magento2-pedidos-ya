<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Sftp;

use Magento\Framework\Encryption\EncryptorInterface;
use Nx6\PedidosYa\Model\ProductsProfile;
use Nx6\PedidosYa\Model\PromoProfile;

/**
 * Builds SFTP Credentials from a saved profile record, decrypting the stored password -
 * shared by ExportRunner and the "Test Connection" action so the encrypt/decrypt handling
 * only lives in one place.
 */
class CredentialsBuilder
{
    private const DEFAULT_PORT = 22;

    private const DEFAULT_TIMEOUT = 10;

    public function __construct(
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function fromProfile(ProductsProfile|PromoProfile $profile): Credentials
    {
        $encryptedPassword = (string) $profile->getSftpPassword();

        return new Credentials(
            (string) $profile->getSftpHost(),
            (int) $profile->getSftpPort() ?: self::DEFAULT_PORT,
            (string) $profile->getSftpUsername(),
            $encryptedPassword !== '' ? $this->encryptor->decrypt($encryptedPassword) : '',
            (string) $profile->getSftpRemotePath(),
            (int) $profile->getSftpTimeout() ?: self::DEFAULT_TIMEOUT
        );
    }
}
