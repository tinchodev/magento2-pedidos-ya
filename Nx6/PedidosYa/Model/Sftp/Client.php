<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Sftp;

use Magento\Framework\Exception\LocalizedException;
use phpseclib3\Net\SFTP;
use Psr\Log\LoggerInterface;

/**
 * Thin wrapper around phpseclib3's SFTP client - one connection per upload, since each profile
 * can point at a different server (see Nx6\PedidosYa\Model\Sftp\Credentials).
 */
class Client
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function uploadFile(string $localPath, Credentials $credentials, string $remoteFilename): void
    {
        $sftp = $this->connect($credentials);
        $this->ensureRemoteDirExists($sftp, $credentials->remotePath);
        $remotePath = rtrim($credentials->remotePath, '/') . '/' . $remoteFilename;

        if (!$sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE)) {
            throw new LocalizedException(
                __('Failed to upload %1 to %2@%3:%4.', $remoteFilename, $credentials->username, $credentials->host, $remotePath)
            );
        }

        $this->logger->info(sprintf(
            'PedidosYa: uploaded %s to %s@%s:%s',
            $remoteFilename,
            $credentials->username,
            $credentials->host,
            $remotePath
        ));
    }

    /**
     * Connects, authenticates, and confirms the configured remote path is usable - creating it
     * (recursively) if it doesn't exist yet - without uploading anything. A blank remote path is
     * treated as "not configured yet" and skipped rather than failed, so testing a profile
     * mid-setup doesn't false-negative on that alone.
     */
    public function testConnection(Credentials $credentials): void
    {
        $sftp = $this->connect($credentials);
        $this->ensureRemoteDirExists($sftp, $credentials->remotePath);

        $this->logger->info(sprintf(
            'PedidosYa: test connection succeeded for %s@%s',
            $credentials->username,
            $credentials->host
        ));
    }

    /**
     * Creates the remote directory (and any missing parent directories) if it doesn't already
     * exist, so a profile pointed at a fresh/never-used path doesn't have to be pre-provisioned
     * on the SFTP server by hand. No-op for a blank path (not configured yet).
     */
    private function ensureRemoteDirExists(SFTP $sftp, string $remotePath): void
    {
        $remotePath = rtrim($remotePath, '/');

        if ($remotePath === '' || $sftp->is_dir($remotePath)) {
            return;
        }

        if (!$sftp->mkdir($remotePath, -1, true)) {
            throw new LocalizedException(
                __('Remote path "%1" does not exist and could not be created.', $remotePath)
            );
        }

        $this->logger->info(sprintf('PedidosYa: created missing remote directory %s', $remotePath));
    }

    private function connect(Credentials $credentials): SFTP
    {
        if ($credentials->host === '') {
            throw new LocalizedException(__('SFTP host is not configured for this profile.'));
        }

        $sftp = new SFTP($credentials->host, $credentials->port, $credentials->timeout);

        if (!$sftp->login($credentials->username, $credentials->password)) {
            throw new LocalizedException(
                __('Could not authenticate to SFTP server %1@%2.', $credentials->username, $credentials->host)
            );
        }

        return $sftp;
    }
}
