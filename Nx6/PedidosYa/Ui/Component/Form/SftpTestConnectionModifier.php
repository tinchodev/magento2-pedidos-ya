<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Ui\Component\Form;

use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;

/**
 * Adds a "Test Connection" button inside the "SFTP Settings" fieldset, wired to test the
 * profile's already-saved SFTP credentials. Hidden on a brand new, unsaved profile - there's
 * nothing saved yet to test.
 *
 * $registryKey and $controllerPath are injected per profile type via di.xml virtualTypes, so
 * this one class serves both the products and promo profile forms (mirrors MappingModifier).
 */
class SftpTestConnectionModifier implements ModifierInterface
{
    /**
     * Name of the fieldset declared in both ui_component form XMLs.
     */
    private const FIELDSET = 'sftp';

    public function __construct(
        private readonly Registry $coreRegistry,
        private readonly UrlInterface $urlBuilder,
        private readonly string $registryKey,
        private readonly string $controllerPath
    ) {
    }

    public function modifyData(array $data): array
    {
        return $data;
    }

    public function modifyMeta(array $meta): array
    {
        $model = $this->coreRegistry->registry($this->registryKey);
        $id = $model ? (int) $model->getId() : 0;

        if (!$id) {
            return $meta;
        }

        $meta[self::FIELDSET]['children']['test_connection'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'container',
                        'component' => 'Nx6_PedidosYa/js/sftp-test-connection',
                        'formElement' => 'container',
                        'title' => __('Test Connection'),
                        'testUrl' => $this->urlBuilder->getUrl($this->controllerPath, ['id' => $id]),
                        'buttonClasses' => 'nx6py-test-connection-btn',
                        'sortOrder' => 70,
                    ],
                ],
            ],
        ];

        return $meta;
    }
}
