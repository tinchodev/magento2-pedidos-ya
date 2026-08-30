<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Export;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Archive\Gz;
use Magento\Framework\Archive\Tar;
use Magento\Framework\Filesystem;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * Saves a copy of every generated export file to var/export/peya as a timestamped .tar.gz,
 * when enabled via Stores > Configuration. Purely a record-keeping step - it never touches the
 * working file the SFTP upload actually reads, and a failure here never fails the export run.
 */
class Archiver
{
    private const XML_PATH_ENABLED = 'nx6_pedidosya/archive/enabled';

    private const ARCHIVE_DIR = 'export/peya';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Filesystem $filesystem,
        private readonly DateTime $dateTime,
        private readonly Tar $tar,
        private readonly Gz $gz,
        private readonly LoggerInterface $logger
    ) {
    }

    public function archive(string $sourcePath, string $filename): void
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED)) {
            return;
        }

        $tarPath = null;

        try {
            $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $varDir->create(self::ARCHIVE_DIR);

            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $timestamp = $this->dateTime->date('Ymd_His');
            $tarPath = $varDir->getAbsolutePath(self::ARCHIVE_DIR . '/' . $baseName . '_' . $timestamp . '.tar');
            $tarGzPath = $tarPath . '.gz';

            $this->tar->pack($sourcePath, $tarPath);
            $this->gz->pack($tarPath, $tarGzPath);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('PedidosYa export archiving failed for %s: %s', $filename, $e->getMessage()));
        } finally {
            if ($tarPath !== null && is_file($tarPath)) {
                unlink($tarPath);
            }
        }
    }
}
