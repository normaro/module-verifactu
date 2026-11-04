<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Block\Adminhtml\System\Config;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;
use Magento\Tax\Model\TaxClass\Source\Product as ProductTaxClassSource;

/**
 * Select of product tax classes for the mapping rows.
 */
class TaxClassColumn extends Select
{
    /**
     * @param Context $context
     * @param ProductTaxClassSource $source
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly ProductTaxClassSource $source,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Input name used by the dynamic rows template.
     *
     * @param string $value
     * @return $this
     */
    public function setInputName(string $value): self
    {
        return $this->setData('name', $value);
    }

    /**
     * Input ID used by the dynamic rows template.
     *
     * @param string $value
     * @return $this
     */
    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }

    /**
     * @inheritdoc
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            foreach ($this->source->getAllOptions(false) as $option) {
                $this->addOption($option['value'], $option['label']);
            }
        }
        $this->setExtraParams('style="width:170px"');
        return parent::_toHtml();
    }
}
