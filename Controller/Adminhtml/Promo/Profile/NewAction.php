<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;

class NewAction extends Profile implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly ForwardFactory $resultForwardFactory
    ) {
        parent::__construct($context);
    }

    #[\Override]
    public function execute(): ResultInterface
    {
        $resultForward = $this->resultForwardFactory->create();

        return $resultForward->forward('edit');
    }
}
