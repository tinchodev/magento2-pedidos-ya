<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Nx6\PedidosYa\Controller\Adminhtml\Promo\Profile;

class Index extends Profile implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    #[\Override]
    public function execute(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $this->initPage($resultPage);
        $resultPage->getConfig()->getTitle()->prepend(__('PedidosYa Promo Profiles'));

        return $resultPage;
    }
}
