<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Block\Adminhtml\PromoProfile\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    #[\Override]
    public function getButtonData(): array
    {
        if (!$this->getProfileId()) {
            return [];
        }

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this promo profile?'),
                $this->getUrl('*/*/delete', ['id' => $this->getProfileId()])
            ),
            'sort_order' => 20,
        ];
    }
}
