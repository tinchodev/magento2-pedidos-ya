<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Nx6\PedidosYa\Model\Export\ExportRunner;
use Nx6\PedidosYa\Model\ResourceModel\ProductsProfile\CollectionFactory as ProductsProfileCollectionFactory;
use Nx6\PedidosYa\Model\ResourceModel\PromoProfile\CollectionFactory as PromoProfileCollectionFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs a single PedidosYa export profile, selected by type + Vendor ID - one invocation per
 * profile, so a scheduler runs this once per profile instead of the command looping internally.
 * The admin "Run Now" buttons cover the same single-profile run via
 * Nx6\PedidosYa\Model\Export\ExportRunner directly, without going through this command.
 */
class RunExportCommand extends Command
{
    private const string ARGUMENT_TYPE = 'type';

    private const string ARGUMENT_VENDOR_ID = 'vendor-id';

    private const string TYPE_PRODUCTS = 'products';

    private const string TYPE_PROMO = 'promo';

    public function __construct(
        private readonly State $appState,
        private readonly ProductsProfileCollectionFactory $productsProfileCollectionFactory,
        private readonly PromoProfileCollectionFactory $promoProfileCollectionFactory,
        private readonly ExportRunner $exportRunner,
        private readonly LoggerInterface $logger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setName('pedidosya:export:run');
        $this->setDescription('Generates and uploads the export file for one PedidosYa profile');
        $this->addArgument(
            self::ARGUMENT_TYPE,
            InputArgument::REQUIRED,
            sprintf('Profile type: "%s" or "%s"', self::TYPE_PRODUCTS, self::TYPE_PROMO)
        );
        $this->addArgument(
            self::ARGUMENT_VENDOR_ID,
            InputArgument::REQUIRED,
            'PedidosYa Vendor ID of the profile to run'
        );

        parent::configure();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Area code was already set by the bin/magento bootstrap - safe to ignore.
        }

        $type = (string) $input->getArgument(self::ARGUMENT_TYPE);
        $vendorId = (string) $input->getArgument(self::ARGUMENT_VENDOR_ID);

        if (!in_array($type, [self::TYPE_PRODUCTS, self::TYPE_PROMO], true)) {
            $output->writeln(sprintf(
                '<error>Invalid type "%s". Expected "%s" or "%s".</error>',
                $type,
                self::TYPE_PRODUCTS,
                self::TYPE_PROMO
            ));

            return Command::FAILURE;
        }

        $collection = $type === self::TYPE_PRODUCTS
            ? $this->productsProfileCollectionFactory->create()
            : $this->promoProfileCollectionFactory->create();

        $collection->addFieldToFilter('vendor_id', $vendorId);
        $collection->addFieldToFilter('is_active', 1);
        $collection->setPageSize(1);

        $dataObject = $collection->getFirstItem();
        if (!$dataObject->getId()) {
            $output->writeln(sprintf(
                '<error>No active %s profile found for Vendor ID "%s".</error>',
                $type,
                $vendorId
            ));

            return Command::FAILURE;
        }

        try {
            $result = $this->exportRunner->run($dataObject);
            $output->writeln(sprintf('<info>[%s %s] %s</info>', $type, $vendorId, $result));

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->logger->error(sprintf(
                'PedidosYa CLI export failed for %s vendor "%s": %s',
                $type,
                $vendorId,
                $throwable->getMessage()
            ));
            $output->writeln(sprintf('<error>[%s %s] %s</error>', $type, $vendorId, $throwable->getMessage()));

            return Command::FAILURE;
        }
    }
}
