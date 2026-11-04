<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model\Quaderno;

use PHPUnit\Framework\TestCase;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\Quaderno\ResponseParser;

/**
 * Status mapping on real (masked) Quaderno sandbox responses.
 */
class ResponseParserTest extends TestCase
{
    /**
     * @var ResponseParser
     */
    private ResponseParser $parser;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->parser = new ResponseParser();
    }

    /**
     * Issued invoice, AEAT Correcto, QR in deliveries[].code.
     *
     * @return void
     */
    public function testIssuedInvoice(): void
    {
        $result = $this->parser->parse(Fixture::load('invoice-issued.json'), Document::TYPE_INVOICE);

        $this->assertSame(Document::STATUS_ISSUED, $result->status);
        $this->assertSame(Document::TYPE_INVOICE, $result->type);
        $this->assertSame('F2026-00001', $result->number);
        $this->assertSame('Correcto', $result->aeatStatus);
        $this->assertSame('2026-11-03', $result->issueDate);
        $this->assertStringContainsString('ValidarQR', (string)$result->aeatQrUrl);
        $this->assertSame('<url-removed>', $result->verificationPermalink);
        $this->assertSame('623644', $result->providerId);
    }

    /**
     * The create response has no deliveries: issued, AEAT pending (fetch follows).
     *
     * @return void
     */
    public function testCreateResponseWithoutDeliveries(): void
    {
        $result = $this->parser->parse(Fixture::load('create-invoice-201.json'));

        $this->assertSame(Document::TYPE_INVOICE, $result->type);
        $this->assertSame(Document::STATUS_ISSUED, $result->status);
        $this->assertSame('pending', $result->aeatStatus);
        $this->assertNull($result->aeatQrUrl);
    }

    /**
     * Receipt (simplificada) below the threshold.
     *
     * @return void
     */
    public function testReceipt(): void
    {
        $result = $this->parser->parse(Fixture::load('receipt-issued.json'), Document::TYPE_RECEIPT);

        $this->assertSame(Document::TYPE_RECEIPT, $result->type);
        $this->assertSame('FS2026-00001', $result->number);
        $this->assertSame(Document::STATUS_ISSUED, $result->status);
    }

    /**
     * €3,001 without customer data: numberless draft, nothing sent to the AEAT.
     *
     * @return void
     */
    public function testDraftWithoutNumber(): void
    {
        $result = $this->parser->parse(Fixture::load('invoice-draft.json'), Document::TYPE_INVOICE);

        $this->assertSame(Document::STATUS_DRAFT, $result->status);
        $this->assertNull($result->number);
        $this->assertNull($result->aeatStatus);
    }

    /**
     * Delivery still pending after minutes is a normal issued state.
     *
     * @return void
     */
    public function testPendingDelivery(): void
    {
        $result = $this->parser->parse(Fixture::load('receipt-pending.json'), Document::TYPE_RECEIPT);

        $this->assertSame(Document::STATUS_ISSUED, $result->status);
        $this->assertSame('pending', $result->aeatStatus);
    }

    /**
     * AceptadoConErrores 2006 -> issued with warning, AEAT message kept.
     *
     * @return void
     */
    public function testAcceptedWithErrors(): void
    {
        $result = $this->parser->parse(Fixture::load('invoice-warning.json'), Document::TYPE_INVOICE);

        $this->assertSame(Document::STATUS_ISSUED_WITH_WARNING, $result->status);
        $this->assertSame('AceptadoConErrores', $result->aeatStatus);
        $this->assertStringStartsWith('2006 ', (string)$result->aeatMessage);
    }

    /**
     * Credit linked to the original receipt; processor ID stored with a time suffix.
     *
     * @return void
     */
    public function testCreditLinkedToOriginal(): void
    {
        $result = $this->parser->parse(Fixture::load('credit-issued.json'), Document::TYPE_CREDIT);

        $this->assertSame(Document::TYPE_CREDIT, $result->type);
        $this->assertSame('FRS2026-00001', $result->number);
        $this->assertSame('623683', $result->relatedDocumentId);
        $this->assertStringStartsWith('P1-399_', (string)$result->processorId);
        $this->assertSame(Document::STATUS_ISSUED, $result->status);
    }
}
