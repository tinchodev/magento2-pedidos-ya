<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Model\ResourceModel\PromoProfile;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Nx6\PedidosYa\Model\PromoProfile;
use Nx6\PedidosYa\Model\ResourceModel\PromoProfile as PromoProfileResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'promo_profile_id';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init(PromoProfile::class, PromoProfileResource::class);
    }
}
