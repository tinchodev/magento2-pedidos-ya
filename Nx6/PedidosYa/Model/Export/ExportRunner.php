<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Export;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Nx6\PedidosYa\Model\ProductsProfile;
use Nx6\PedidosYa\Model\PromoProfile;
use Nx6\PedidosYa\Model\Sftp\Client;
use Nx6\PedidosYa\Model\Sftp\CredentialsBuilder;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates a single profile run: generate the CSV, upload it via the profile's own SFTP
 * credentials, and persist the outcome on the profile so both the "Run Now" action and the
 * cron share one code path.
 */
class ExportRunner
{
    public function __construct(
        private readonly Generator $generator,
        private readonly Client $sftpClient,
        private readonly CredentialsBuilder $credentialsBuilder,
        private readonly Archiver $archiver,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return string human-readable run summary
     * @throws \Throwable
     */
    public function run(ProductsProfile|PromoProfile $profile): string
    {
        try {
            $batches = $this->generator->generate($profile);
            $credentials = $this->credentialsBuilder->fromProfile($profile);

            $summaries = [];
            foreach ($batches as $batch) {
                $this->archiver->archive($batch['path'], $batch['filename']);
                $this->sftpClient->uploadFile($batch['path'], $credentials, $batch['filename']);
                $summaries[] = sprintf('%d rows uploaded to %s/%s', $batch['rows'], $credentials->remotePath, $batch['filename']);
            }

            $status = implode('; ', $summaries);
            $this->persistResult($profile, $status);

            return $status;
        } catch (\Throwable $throwable) {
            $this->logger->error(sprintf('PedidosYa export failed for profile #%d: %s', $profile->getId(), $throwable->getMessage()));
            $this->persistResult($profile, 'Error: ' . $throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * last_run_status is a varchar(255) column - a multi-batch summary (one clause per file) can
     * run past that under a large split, and MySQL's default strict mode turns an overlong value
     * into a save error instead of a silent truncation. Cut short rather than let a successful
     * multi-file upload get reported back to the caller as a failure.
     */
    private function persistResult(ProductsProfile|PromoProfile $profile, string $status): void
    {
        $profile->setLastRunAt($this->dateTime->gmtDate());
        $profile->setLastRunStatus(mb_strlen($status) > 255 ? mb_substr($status, 0, 252) . '...' : $status);
        $profile->save();
    }
}
