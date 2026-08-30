<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\ResourceModel\ProductsProfile\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Nx6\PedidosYa\Model\ResourceModel\ProductsProfile as ProductsProfileResource;
use Psr\Log\LoggerInterface;

/**
 * Collection used by the products profile listing UI component. It extends the framework
 * SearchResult rather than the plain resource collection so rows are hydrated as
 * UiComponent\DataProvider\Document objects: the generic UI DataProvider reads every row
 * through getCustomAttributes(), which AbstractModel does not implement.
 */
class Collection extends SearchResult
{
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable = 'nx6_pedidosya_products_profile',
        $resourceModel = ProductsProfileResource::class,
        $identifierName = 'products_profile_id',
        $connectionName = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }
}
