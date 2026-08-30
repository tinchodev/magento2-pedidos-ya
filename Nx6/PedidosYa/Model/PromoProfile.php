<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model;

use Magento\Framework\Model\AbstractModel;
use Nx6\PedidosYa\Model\ResourceModel\PromoProfile as PromoProfileResource;

class PromoProfile extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(PromoProfileResource::class);
    }
}
