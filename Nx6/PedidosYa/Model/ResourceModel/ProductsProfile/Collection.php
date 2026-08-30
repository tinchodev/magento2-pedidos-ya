<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\ResourceModel\ProductsProfile;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Nx6\PedidosYa\Model\ProductsProfile;
use Nx6\PedidosYa\Model\ResourceModel\ProductsProfile as ProductsProfileResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'products_profile_id';

    protected function _construct(): void
    {
        $this->_init(ProductsProfile::class, ProductsProfileResource::class);
    }
}
