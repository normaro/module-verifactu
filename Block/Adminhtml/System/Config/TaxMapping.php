<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * Dynamic rows: Magento product tax class -> IVA rate and Quaderno tax code.
 */
class TaxMapping extends AbstractFieldArray
{
    /**
     * @var TaxClassColumn|null
     */
    private ?TaxClassColumn $taxClassRenderer = null;

    /**
     * @var TaxCodeColumn|null
     */
    private ?TaxCodeColumn $taxCodeRenderer = null;

    /**
     * @inheritdoc
     */
    protected function _prepareToRender()
    {
        $this->addColumn('tax_class_id', [
            'label' => __('Tax class'),
            'renderer' => $this->getTaxClassRenderer(),
        ]);
        $this->addColumn('rate', [
            'label' => __('IVA rate, %'),
            'class' => 'required-entry validate-number validate-zero-or-greater',
            'style' => 'width:60px',
        ]);
        $this->addColumn('tax_code', [
            'label' => __('Tax type in Quaderno'),
            'renderer' => $this->getTaxCodeRenderer(),
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string)__('Add mapping');
    }

    /**
     * @inheritdoc
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $classId = $row->getData('tax_class_id');
        if ($classId !== null) {
            $options['option_' . $this->getTaxClassRenderer()->calcOptionHash($classId)] = 'selected="selected"';
        }
        $code = $row->getData('tax_code');
        if ($code !== null) {
            $options['option_' . $this->getTaxCodeRenderer()->calcOptionHash($code)] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Select of product tax classes.
     *
     * @return TaxClassColumn
     * @throws LocalizedException
     */
    private function getTaxClassRenderer(): TaxClassColumn
    {
        if ($this->taxClassRenderer === null) {
            /** @var TaxClassColumn $block */
            $block = $this->getLayout()->createBlock(
                TaxClassColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->taxClassRenderer = $block;
        }
        return $this->taxClassRenderer;
    }

    /**
     * Select of Quaderno tax codes.
     *
     * @return TaxCodeColumn
     * @throws LocalizedException
     */
    private function getTaxCodeRenderer(): TaxCodeColumn
    {
        if ($this->taxCodeRenderer === null) {
            /** @var TaxCodeColumn $block */
            $block = $this->getLayout()->createBlock(
                TaxCodeColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->taxCodeRenderer = $block;
        }
        return $this->taxCodeRenderer;
    }
}
