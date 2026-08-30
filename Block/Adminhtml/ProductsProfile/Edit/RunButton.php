<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Block\Adminhtml\ProductsProfile\Edit;

use Magento\Framework\Phrase;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Runs the products profile straight from its edit page.
 *
 * Sits immediately left of Save (sort order 90) and, like the grid's "Run Now" action,
 * confirms first and submits a POST - ProductsProfile\Run is an HttpPostActionInterface.
 */
class RunButton extends GenericButton implements ButtonProviderInterface
{
    #[\Override]
    public function getButtonData(): array
    {
        $id = $this->getProfileId();

        // An unsaved profile has nothing to export yet.
        if (!$id) {
            return [];
        }

        return [
            'label' => __('Run'),
            'class' => 'primary',
            'on_click' => $this->getOnClick($this->getUrl('*/*/run', ['id' => $id])),
            'sort_order' => 80,
        ];
    }

    /**
     * Confirm, then post to the run action with the admin form key.
     */
    private function getOnClick(string $url): string
    {
        return sprintf(
            "require(['jquery', 'Magento_Ui/js/modal/confirm'], function ($, confirm) {"
            . " confirm({ title: '%s', content: '%s', actions: { confirm: function () {"
            . " $('<form>', { action: '%s', method: 'post' })"
            . ".append($('<input>', { type: 'hidden', name: 'form_key', value: window.FORM_KEY }))"
            . ".appendTo('body').trigger('submit'); } } }); });",
            $this->escapeJs(__('Run Export')),
            $this->escapeJs(__('Generate and upload the export file for this profile now?')),
            $this->escapeJs($url)
        );
    }

    private function escapeJs(string|Phrase $value): string
    {
        return str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '', ''], (string) $value);
    }
}
