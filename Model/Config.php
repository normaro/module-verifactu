<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to the module configuration (Stores > Configuration > Sales > Verifactu).
 */
class Config
{
    public const XML_ENABLED = 'verifactu/general/enabled';
    public const XML_ENVIRONMENT = 'verifactu/general/environment';
    public const XML_API_URL = 'verifactu/general/api_url';
    public const XML_API_KEY = 'verifactu/general/api_key';
    public const XML_TRIGGER = 'verifactu/general/trigger';
    public const XML_THRESHOLD = 'verifactu/general/threshold';
    public const XML_DEBUG = 'verifactu/general/debug';
    public const XML_TAX_MAPPING = 'verifactu/tax/mapping';
    public const XML_SHIPPING_TAX_CLASS = 'tax/classes/shipping_tax_class';

    public const ENV_SANDBOX = 'sandbox';
    public const ENV_LIVE = 'live';
    public const TRIGGER_CAPTURE = 'capture';
    public const TRIGGER_INVOICE = 'invoice';

    /** Hosts of Quaderno sandbox accounts end with this suffix */
    public const SANDBOX_HOST_SUFFIX = '.sandbox-quadernoapp.com';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $json
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {
    }

    /**
     * Module enabled for the store.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Debug logging: every Quaderno request and response in var/log/verifactu.log.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDebug(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_DEBUG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Sandbox or live.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEnvironment(?int $storeId = null): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_ENVIRONMENT, ScopeInterface::SCOPE_STORE, $storeId);
        return $value === self::ENV_LIVE ? self::ENV_LIVE : self::ENV_SANDBOX;
    }

    /**
     * Account API URL, always ending with "/".
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiUrl(?int $storeId = null): string
    {
        $url = trim((string)$this->scopeConfig->getValue(self::XML_API_URL, ScopeInterface::SCOPE_STORE, $storeId));
        return $url === '' ? '' : rtrim($url, '/') . '/';
    }

    /**
     * Private API key.
     *
     * Stored encrypted; config.xml declares the Encrypted backend model for the path, so the
     * scope config already returns the decrypted value.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_API_KEY, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /**
     * When invoices are sent: on capture (paid invoices) or on every new invoice.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTrigger(?int $storeId = null): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_TRIGGER, ScopeInterface::SCOPE_STORE, $storeId);
        return $value === self::TRIGGER_INVOICE ? self::TRIGGER_INVOICE : self::TRIGGER_CAPTURE;
    }

    /**
     * Simplificada threshold (VAT included); must equal the provider's receipts threshold.
     *
     * @param int|null $storeId
     * @return float
     */
    public function getThreshold(?int $storeId = null): float
    {
        $value = $this->scopeConfig->getValue(self::XML_THRESHOLD, ScopeInterface::SCOPE_STORE, $storeId);
        return $value === null || $value === '' ? 3000.0 : (float)$value;
    }

    /**
     * Tax class ID => ['rate' => float, 'code' => string].
     *
     * @param int|null $storeId
     * @return array<int,array{rate:float,code:string}>
     */
    public function getTaxMapping(?int $storeId = null): array
    {
        $raw = $this->scopeConfig->getValue(self::XML_TAX_MAPPING, ScopeInterface::SCOPE_STORE, $storeId);
        if (!$raw) {
            return [];
        }
        $rows = is_array($raw) ? $raw : $this->json->unserialize((string)$raw);
        $mapping = [];
        foreach ((array)$rows as $row) {
            if (!is_array($row) || !isset($row['tax_class_id']) || $row['tax_class_id'] === '') {
                continue;
            }
            $mapping[(int)$row['tax_class_id']] = [
                'rate' => (float)($row['rate'] ?? 0),
                'code' => (string)($row['tax_code'] ?? 'standard'),
            ];
        }
        return $mapping;
    }

    /**
     * Magento shipping tax class.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getShippingTaxClassId(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_SHIPPING_TAX_CLASS, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
