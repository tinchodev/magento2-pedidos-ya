<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PromoProfile extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('nx6_pedidosya_promo_profile', 'promo_profile_id');
    }
}
