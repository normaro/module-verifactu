<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Plugin;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Block\Adminhtml\Order\View\Info;
use Normaro\Verifactu\Plugin\AdminDocumentBlockPosition;
use PHPUnit\Framework\TestCase;

class AdminDocumentBlockPositionTest extends TestCase
{
    /**
     * The Verifactu block goes in front of "order_info" when the page layout declares it.
     *
     * @return void
     */
    public function testPrependsTheBlockOnInvoiceAndCreditMemoPages(): void
    {
        $block = $this->createMock(AbstractBlock::class);
        $block->method('toHtml')->willReturn('<section>verifactu</section>');

        $html = (new AdminDocumentBlockPosition())->afterToHtml($this->info('order_info', $block), '<info/>');

        $this->assertSame('<section>verifactu</section><info/>', $html);
    }

    /**
     * Order view (no Verifactu block in its layout) and other Info blocks stay as they are.
     *
     * @return void
     */
    public function testLeavesOtherPagesAlone(): void
    {
        $plugin = new AdminDocumentBlockPosition();

        $this->assertSame('<info/>', $plugin->afterToHtml($this->info('order_info', false), '<info/>'));
        $this->assertSame('<info/>', $plugin->afterToHtml($this->info('other_info', false), '<info/>'));
    }

    /**
     * Info block with a layout that has (or not) the Verifactu block.
     *
     * @param string $name
     * @param AbstractBlock|false $verifactuBlock
     * @return Info
     */
    private function info(string $name, $verifactuBlock): Info
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getBlock')->with('verifactu.document.info')->willReturn($verifactuBlock);
        $info = $this->createMock(Info::class);
        $info->method('getNameInLayout')->willReturn($name);
        $info->method('getLayout')->willReturn($layout);
        return $info;
    }
}
