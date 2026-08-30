<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class PromoProfileActions extends Column
{
    public const URL_PATH_EDIT = 'pedidosya/promo_profile/edit';

    public const URL_PATH_DELETE = 'pedidosya/promo_profile/delete';

    public const URL_PATH_RUN = 'pedidosya/promo_profile/run';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $url,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    #[\Override]
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['promo_profile_id'])) {
                continue;
            }

            $name = $this->escaper->escapeHtmlAttr($item['vendor_id'] ?? '');
            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->url->getUrl(self::URL_PATH_EDIT, ['id' => $item['promo_profile_id']]),
                    'label' => __('Edit'),
                ],
                'run' => [
                    'href' => $this->url->getUrl(self::URL_PATH_RUN, ['id' => $item['promo_profile_id']]),
                    'label' => __('Run Now'),
                    'confirm' => [
                        'title' => __('Run Export'),
                        'message' => __('Generate and upload the export file for "%1" now?', $name),
                    ],
                    'post' => true,
                ],
                'delete' => [
                    'href' => $this->url->getUrl(self::URL_PATH_DELETE, ['id' => $item['promo_profile_id']]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete "%1"', $name),
                        'message' => __('Are you sure you want to delete this promo profile?'),
                    ],
                    'post' => true,
                ],
            ];
        }

        return $dataSource;
    }
}
