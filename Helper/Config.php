<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Centralises all system-config reads for Nx6_PedidosYa.
 *
 * Config paths live under nx6_pedidosya/<group>/<field>
 * and are set at Stores > Configuration > Farma > PedidosYa.
 */
class Config extends AbstractHelper
{
    private const string XML_BLACKLISTED_PRODUCT_NAMES = 'nx6_pedidosya/export/blacklisted_product_names';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * Blacklisted product names, one per line in admin config. Products whose name
     * contains any of these as a whole word must always be exported as inactive.
     *
     * @return string[] trimmed, non-empty names
     */
    public function getBlacklistedProductNames(): array
    {
        $raw = $this->scopeConfig->getValue(self::XML_BLACKLISTED_PRODUCT_NAMES, ScopeInterface::SCOPE_STORE) ?? '';

        $names = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw)));

        return array_values($names);
    }

    /**
     * True when the given product name contains any configured blacklisted name as a
     * case-insensitive whole word (not merely a substring - e.g. "LEVO" must not match
     * inside "LEVOTIROXINA", "EFIL" must not match inside "REFILL").
     */
    public function isBlacklisted(string $productName): bool
    {
        foreach ($this->getBlacklistedProductNames() as $name) {
            if ($name === '') {
                continue;
            }

            if (preg_match('/\b' . preg_quote($name, '/') . '\b/ui', $productName) === 1) {
                return true;
            }
        }

        return false;
    }
}
