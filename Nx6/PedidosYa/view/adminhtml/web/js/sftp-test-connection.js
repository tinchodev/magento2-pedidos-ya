/**
 * "Test Connection" button rendered inside the SFTP Settings fieldset. Extends Magento's own
 * core button component (used for e.g. the "Advanced Inventory"/"Assign Sources" buttons on the
 * product form) purely for its already-correct rendering/styling, overriding only the click
 * behavior: POSTs to the profile's already-saved-credentials test endpoint
 * (Nx6\PedidosYa\Controller\Adminhtml\*\Profile\TestConnection) and lets the page redirect back
 * with a normal admin flash message, same as the "Run" action.
 */
define([
    'jquery',
    'Magento_Ui/js/form/components/button'
], function ($, Button) {
    'use strict';

    return Button.extend({
        defaults: {
            testUrl: ''
        },

        /** @inheritdoc */
        action: function () {
            $('<form>', {
                action: this.testUrl,
                method: 'post'
            })
                .append($('<input>', {
                    type: 'hidden',
                    name: 'form_key',
                    value: window.FORM_KEY
                }))
                .appendTo('body')
                .trigger('submit');
        }
    });
});
