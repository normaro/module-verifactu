<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;
use Magento\Framework\Module\PackageInfo;
use Normaro\Verifactu\Block\Adminhtml\System\Config\Footer;
use PHPUnit\Framework\TestCase;

class FooterTest extends TestCase
{
    public function testOneLineWithVersionAndSiteLink(): void
    {
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->method('getVersion')->with('Normaro_Verifactu')->willReturn('0.1.0');
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $escaper->method('escapeUrl')->willReturnArgument(0);
        $footer = new Footer($this->createMock(Context::class), $packageInfo, $escaper);

        $html = $footer->render($this->createMock(AbstractElement::class));

        $this->assertStringContainsString('Normaro Verifactu 0.1.0', $html);
        $this->assertStringContainsString('href="https://normaro.es"', $html);
        $this->assertSame(1, substr_count($html, '<a '));
    }
}
