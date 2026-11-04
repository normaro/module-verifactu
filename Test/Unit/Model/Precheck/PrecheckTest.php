<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model\Precheck;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Normaro\Verifactu\Model\Config;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\DocumentRepository;
use Normaro\Verifactu\Model\Mapper\CreditmemoMapper;
use Normaro\Verifactu\Model\Mapper\InvoiceMapper;
use Normaro\Verifactu\Model\Mapper\MappingException;
use Normaro\Verifactu\Model\Precheck\NifValidator;
use Normaro\Verifactu\Model\Precheck\Precheck;
use Normaro\Verifactu\Model\Precheck\PrecheckResult;
use Normaro\Verifactu\Model\Provider\CustomerData;
use Normaro\Verifactu\Model\Provider\DocumentData;
use Normaro\Verifactu\Model\Provider\LineData;

/**
 * Precheck rules (sale and credit memo rules).
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class PrecheckTest extends TestCase
{
    /**
     * @var InvoiceMapper&MockObject
     */
    private InvoiceMapper $invoiceMapper;

    /**
     * @var CreditmemoMapper&MockObject
     */
    private CreditmemoMapper $creditmemoMapper;

    /**
     * @var DocumentRepository&MockObject
     */
    private DocumentRepository $repository;

    /**
     * @var InvoiceRepositoryInterface&MockObject
     */
    private InvoiceRepositoryInterface $invoiceRepository;

    /**
     * @var Precheck
     */
    private Precheck $precheck;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getThreshold')->willReturn(3000.0);
        $this->invoiceMapper = $this->createMock(InvoiceMapper::class);
        $this->creditmemoMapper = $this->createMock(CreditmemoMapper::class);
        $this->repository = $this->createMock(DocumentRepository::class);
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->precheck = new Precheck(
            $config,
            $this->invoiceMapper,
            $this->creditmemoMapper,
            new NifValidator(),
            $this->repository,
            $this->invoiceRepository
        );
    }

    /**
     * B2C ES without NIF below the threshold is sent.
     *
     * @return void
     */
    public function testBelowThresholdWithoutNifIsSent(): void
    {
        $this->assertSame(PrecheckResult::OK, $this->checkSale('ES', null, 2999.00)->outcome);
    }

    /**
     * At the threshold (Quaderno issues a receipt only below it) without NIF -> blocked.
     *
     * @return void
     */
    public function testAtThresholdWithoutNifIsBlocked(): void
    {
        $result = $this->checkSale('ES', null, 3000.00);

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
        $this->assertStringContainsString('NIF required', (string)$result->reason);
    }

    /**
     * Above the threshold with a valid NIF is sent.
     *
     * @return void
     */
    public function testAboveThresholdWithNifIsSent(): void
    {
        $this->assertSame(PrecheckResult::OK, $this->checkSale('ES', '12345678Z', 3029.84)->outcome);
    }

    /**
     * Quaderno sandbox: wrong check letter would leave a numberless draft: blocked before sending.
     *
     * @return void
     */
    public function testInvalidNifIsBlocked(): void
    {
        $result = $this->checkSale('ES', '12345678A', 100.00);

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
        $this->assertStringContainsString('12345678A', (string)$result->reason);
    }

    /**
     * Export above the threshold without NIF is not a Spanish domestic case.
     *
     * @return void
     */
    public function testForeignCustomerIsNotThresholdChecked(): void
    {
        $this->assertSame(PrecheckResult::OK, $this->checkSale('US', null, 5000.00)->outcome);
    }

    /**
     * Quaderno drafts a document whose contact has no street (Inbox, 2026-11-04): blocked, sale and refund.
     *
     * @dataProvider incompleteAddresses
     * @param string $type
     * @param string|null $street
     * @param string|null $city
     * @param string|null $postcode
     * @param string $country
     * @param string $missing
     * @return void
     */
    public function testIncompleteAddressIsBlocked(
        string $type,
        ?string $street,
        ?string $city,
        ?string $postcode,
        string $country,
        string $missing
    ): void {
        $data = new DocumentData(
            $type,
            '000000001',
            'EUR',
            new CustomerData('person', 'Cliente', 'Prueba', $country, null, $street, null, $city, $postcode),
            [new LineData('Producto', 24.20, 21.0, 'standard')],
            24.20,
            1
        );
        $this->invoiceMapper->method('map')->willReturn($data);

        $result = $this->precheck->checkInvoice($this->createMock(Invoice::class));

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
        $this->assertStringContainsString('(missing: ' . $missing . ')', (string)$result->reason);
    }

    /**
     * Address variants with what is missing.
     *
     * @return array<string,array{string,?string,?string,?string,string,string}>
     */
    public static function incompleteAddresses(): array
    {
        $sale = DocumentData::TRANSACTION_SALE;
        $refund = DocumentData::TRANSACTION_REFUND;
        return [
            'sale without street' => [$sale, null, 'Alicante', '03001', 'ES', 'street'],
            'sale with blank street' => [$sale, '  ', 'Alicante', '03001', 'ES', 'street'],
            'sale without city and postcode' => [$sale, 'Calle 1', '', null, 'ES', 'city, postcode'],
            'sale without country' => [$sale, 'Calle 1', 'Alicante', '03001', '', 'country'],
            'refund without street' => [$refund, null, 'Alicante', '03001', 'ES', 'street'],
        ];
    }

    /**
     * Lines that do not add up to the grand total are blocked.
     *
     * @return void
     */
    public function testLinesNotReconcilingAreBlocked(): void
    {
        $this->invoiceMapper->method('map')->willReturn($this->sale('ES', null, 100.00, 95.00));

        $result = $this->precheck->checkInvoice($this->createMock(Invoice::class));

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
        $this->assertStringContainsString('is an adjustment, fee or gift card', (string)$result->reason);
    }

    /**
     * A missing tax-class mapping blocks.
     *
     * @return void
     */
    public function testMissingMappingIsBlocked(): void
    {
        $this->invoiceMapper->method('map')->willThrowException(new MappingException(__('No IVA mapping')));

        $result = $this->precheck->checkInvoice($this->createMock(Invoice::class));

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
        $this->assertSame('No IVA mapping', $result->reason);
    }

    /**
     * Canarias, Ceuta, Melilla without NIF are blocked at any amount (RF 2.2.d).
     *
     * @dataProvider zeroIvaPostcodes
     * @param string $postcode
     * @return void
     */
    public function testZeroIvaTerritoryWithoutNifIsBlocked(string $postcode): void
    {
        $this->invoiceMapper->method('map')->willReturn($this->territorySale($postcode, null, 0.0));

        $result = $this->precheck->checkInvoice($this->createMock(Invoice::class));

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
        $this->assertStringContainsString('RF art. 2.2.d', (string)$result->reason);
    }

    /**
     * Postcodes of Canarias (35, 38), Ceuta (51), Melilla (52).
     *
     * @return array<string,array{string}>
     */
    public static function zeroIvaPostcodes(): array
    {
        return ['Las Palmas' => ['35002'], 'Tenerife' => ['38001'], 'Ceuta' => ['51001'], 'Melilla' => ['52001']];
    }

    /**
     * Canarias with a NIF: sent.
     *
     * @return void
     */
    public function testCanariasWithNifIsSent(): void
    {
        $this->invoiceMapper->method('map')->willReturn($this->territorySale('35002', '12345678Z', 0.0));

        $result = $this->precheck->checkInvoice($this->createMock(Invoice::class));

        $this->assertSame(PrecheckResult::OK, $result->outcome);
        $this->assertSame([], $result->warnings);
    }

    /**
     * Canarias with a NIF and IVA charged: sent, with a warning.
     *
     * @return void
     */
    public function testCanariasWithIvaGivesWarning(): void
    {
        $this->invoiceMapper->method('map')->willReturn($this->territorySale('35002', '12345678Z', 21.0));

        $result = $this->precheck->checkInvoice($this->createMock(Invoice::class));

        $this->assertSame(PrecheckResult::OK, $result->outcome);
        $this->assertCount(1, $result->warnings);
    }

    /**
     * One-line sale of 24.20 to a Spanish postcode.
     *
     * @param string $postcode
     * @param string|null $taxId
     * @param float $rate
     * @return DocumentData
     */
    private function territorySale(string $postcode, ?string $taxId, float $rate): DocumentData
    {
        return new DocumentData(
            DocumentData::TRANSACTION_SALE,
            '1',
            'EUR',
            new CustomerData('person', 'Cliente', 'Canarias', 'ES', $taxId, 'Calle 5', null, 'Ciudad', $postcode),
            [new LineData('Camiseta', 24.20, $rate, $rate > 0.0 ? 'standard' : 'exempt')],
            24.20,
            1
        );
    }

    /**
     * Credit memo without a row of its invoice: blocked.
     *
     * @return void
     */
    public function testCreditmemoWithoutOriginalIsBlocked(): void
    {
        $result = $this->precheck->checkCreditmemo($this->creditmemo(10.00), $this->row(5, null));

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
    }

    /**
     * Original still queued: wait.
     *
     * @return void
     */
    public function testCreditmemoWaitsForQueuedOriginal(): void
    {
        $this->repository->method('getById')->willReturn($this->row(1, null, Document::STATUS_QUEUED));

        $result = $this->precheck->checkCreditmemo($this->creditmemo(10.00), $this->row(5, 1));

        $this->assertSame(PrecheckResult::WAIT, $result->outcome);
    }

    /**
     * Original blocked: the credit memo is blocked too.
     *
     * @return void
     */
    public function testCreditmemoOfBlockedOriginalIsBlocked(): void
    {
        $this->repository->method('getById')->willReturn($this->row(1, null, Document::STATUS_BLOCKED));

        $result = $this->precheck->checkCreditmemo($this->creditmemo(10.00), $this->row(5, 1));

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
    }

    /**
     * Quaderno sandbox: one refund per original; a second credit memo is blocked.
     *
     * @return void
     */
    public function testSecondCreditmemoIsBlocked(): void
    {
        $this->repository->method('getById')->willReturn($this->row(1, null, Document::STATUS_ISSUED));
        $this->repository->method('findCredits')->willReturn([$this->row(4, 1, Document::STATUS_ISSUED)]);

        $result = $this->precheck->checkCreditmemo($this->creditmemo(10.00), $this->row(5, 1));

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
        $this->assertStringContainsString('already has a credit note', (string)$result->reason);
    }

    /**
     * A credit memo above the original total is blocked.
     *
     * @return void
     */
    public function testCreditmemoAboveOriginalIsBlocked(): void
    {
        $this->repository->method('getById')->willReturn($this->row(1, null, Document::STATUS_ISSUED));
        $this->repository->method('findCredits')->willReturn([]);
        $this->originalTotal(100.00);

        $result = $this->precheck->checkCreditmemo($this->creditmemo(150.00), $this->row(5, 1));

        $this->assertSame(PrecheckResult::BLOCKED, $result->outcome);
        $this->assertStringContainsString('exceeds', (string)$result->reason);
    }

    /**
     * Full refund of an issued original is sent.
     *
     * @return void
     */
    public function testFullRefundOfIssuedOriginalIsSent(): void
    {
        $this->repository->method('getById')->willReturn($this->row(1, null, Document::STATUS_ISSUED));
        $this->repository->method('findCredits')->willReturn([$this->row(4, 1, Document::STATUS_BLOCKED)]);
        $this->originalTotal(29.04);
        $refund = new DocumentData(
            DocumentData::TRANSACTION_REFUND,
            '000000002',
            'EUR',
            new CustomerData('person', 'Cliente', 'Peninsula', 'ES', null, 'Calle Mayor 10', null, 'Madrid', '28013'),
            [new LineData('Taza', 29.04, 21.0, 'standard')],
            29.04,
            1
        );
        $this->creditmemoMapper->method('map')->willReturn($refund);

        $result = $this->precheck->checkCreditmemo($this->creditmemo(29.04), $this->row(5, 1));

        $this->assertSame(PrecheckResult::OK, $result->outcome);
        $this->assertSame($refund, $result->data);
    }

    /**
     * Run the invoice precheck on a one-line sale.
     *
     * @param string $country
     * @param string|null $taxId
     * @param float $total
     * @return PrecheckResult
     */
    private function checkSale(string $country, ?string $taxId, float $total): PrecheckResult
    {
        $this->invoiceMapper->method('map')->willReturn($this->sale($country, $taxId, $total, $total));
        return $this->precheck->checkInvoice($this->createMock(Invoice::class));
    }

    /**
     * One-line sale.
     *
     * @param string $country
     * @param string|null $taxId
     * @param float $grandTotal
     * @param float $lineAmount
     * @return DocumentData
     */
    private function sale(string $country, ?string $taxId, float $grandTotal, float $lineAmount): DocumentData
    {
        return new DocumentData(
            DocumentData::TRANSACTION_SALE,
            '000000001',
            'EUR',
            new CustomerData('person', 'Cliente', 'Prueba', $country, $taxId, 'Calle Mayor 1', null, 'Ciudad', '28013'),
            [new LineData('Producto', $lineAmount, 21.0, 'standard')],
            $grandTotal,
            1
        );
    }

    /**
     * Document row.
     *
     * @param int $id
     * @param int|null $originalId
     * @param string $status
     * @return Document&MockObject
     */
    private function row(int $id, ?int $originalId, string $status = Document::STATUS_PENDING): Document
    {
        $row = $this->getMockBuilder(Document::class)->disableOriginalConstructor()
            ->onlyMethods(['getId'])->getMock();
        $row->method('getId')->willReturn($id);
        $row->setData(['original_document_id' => $originalId, 'status' => $status, 'source_id' => 10]);
        return $row;
    }

    /**
     * Credit memo with a grand total.
     *
     * @param float $grandTotal
     * @return Creditmemo&MockObject
     */
    private function creditmemo(float $grandTotal): Creditmemo
    {
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getGrandTotal')->willReturn($grandTotal);
        return $creditmemo;
    }

    /**
     * Grand total of the original Magento invoice.
     *
     * @param float $total
     * @return void
     */
    private function originalTotal(float $total): void
    {
        $invoice = $this->createMock(InvoiceInterface::class);
        $invoice->method('getGrandTotal')->willReturn($total);
        $this->invoiceRepository->method('get')->willReturn($invoice);
    }
}
