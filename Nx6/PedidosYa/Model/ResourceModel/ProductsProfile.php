<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ProductsProfile extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('nx6_pedidosya_products_profile', 'products_profile_id');
    }
}
