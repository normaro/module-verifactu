<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Block\Adminhtml\System\Config;

use Magento\Backend\Block\AbstractBlock;
use Magento\Backend\Block\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Module\PackageInfo;

/**
 * Footer line of the Verifactu config section: module name, version, link to the product site.
 */
class Footer extends AbstractBlock implements RendererInterface
{
    public const SITE_URL = 'https://normaro.es';
    private const MODULE = 'Normaro_Verifactu';

    /**
     * @param Context $context
     * @param PackageInfo $packageInfo
     * @param Escaper $escaper
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        private readonly PackageInfo $packageInfo,
        private readonly Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $version = (string)$this->packageInfo->getVersion(self::MODULE);
        $label = trim('Normaro Verifactu ' . $version);
        return sprintf(
            '<p class="note verifactu-footer">%s · <a href="%s" target="_blank" rel="noopener">%s</a></p>',
            $this->escaper->escapeHtml($label),
            $this->escaper->escapeUrl(self::SITE_URL),
            'normaro.es'
        );
    }
}
