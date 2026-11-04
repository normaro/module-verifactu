<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Test\Unit\Model\Quaderno;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\Provider\CustomerData;
use Normaro\Verifactu\Model\Provider\DocumentData;
use Normaro\Verifactu\Model\Provider\Exception\DuplicateException;
use Normaro\Verifactu\Model\Provider\LineData;
use Normaro\Verifactu\Model\Quaderno\Client;
use Normaro\Verifactu\Model\Quaderno\HttpException;
use Normaro\Verifactu\Model\Quaderno\PayloadBuilder;
use Normaro\Verifactu\Model\Quaderno\QuadernoProvider;
use Normaro\Verifactu\Model\Quaderno\ResponseParser;

/**
 * send = POST + fetch; 422 "processor_id taken" = lookup in the list of the document type.
 */
class QuadernoProviderTest extends TestCase
{
    /**
     * @var Client&MockObject
     */
    private Client $client;

    /**
     * @var QuadernoProvider
     */
    private QuadernoProvider $provider;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->provider = new QuadernoProvider($this->client, new PayloadBuilder(), new ResponseParser());
    }

    /**
     * The create response has no deliveries, so the invoice is fetched once.
     *
     * @return void
     */
    public function testSendFetchesTheCreatedDocument(): void
    {
        $this->client->expects($this->once())->method('post')
            ->with('transactions', $this->isType('array'), 1)
            ->willReturn(Fixture::load('create-invoice-201.json'));
        $this->client->expects($this->once())->method('get')
            ->with('invoices/623644', [], 1)
            ->willReturn(Fixture::load('invoice-issued.json'));

        $result = $this->provider->send($this->sale('T0-20241028-1', 121.00));

        $this->assertSame(Document::STATUS_ISSUED, $result->status);
        $this->assertSame('Correcto', $result->aeatStatus);
        $this->assertSame('F2026-00001', $result->number);
    }

    /**
     * A retry gets 422 "has already been taken"; the receipt is found in /receipts only, then fetched.
     *
     * @return void
     */
    public function testRetryAfterLostResponseReturnsTheExistingReceipt(): void
    {
        $this->client->method('post')->willThrowException($this->taken());
        $calls = [];
        $this->client->expects($this->exactly(3))->method('get')
            ->willReturnCallback(static function (string $path, array $query) use (&$calls): array {
                $calls[] = $path;
                if ($path === 'receipts/623697') {
                    return Fixture::load('receipts-lookup.json')[0];
                }
                self::assertSame(['processor_id' => 'P3-retry'], $query);
                return $path === 'receipts' ? Fixture::load('receipts-lookup.json') : [];
            });

        $result = $this->provider->send($this->sale('P3-retry', 60.00));

        $this->assertSame(['invoices', 'receipts', 'receipts/623697'], $calls);
        $this->assertSame(Document::TYPE_RECEIPT, $result->type);
        $this->assertSame('FS2026-00006', $result->number);
    }

    /**
     * A credit's processor ID is stored as "<sent>_<time>"; found among the original contact's
     * credits by prefix and amount, then fetched.
     *
     * @return void
     */
    public function testRetriedRefundFindsTheCreditByPrefixAndAmount(): void
    {
        $this->client->method('post')->willThrowException($this->taken());
        $calls = [];
        $this->client->method('get')
            ->willReturnCallback(static function (string $path, array $query) use (&$calls): array {
                $calls[] = [$path, $query];
                return $path === 'credits' ? Fixture::load('credits-list.json') : Fixture::load('credit-issued.json');
            });

        $result = $this->provider->send($this->refund('P1-399', 399.00));

        $this->assertSame(
            [['credits', ['limit' => '100', 'contact' => '9795037']], ['credits/623700', []]],
            $calls
        );
        $this->assertSame(Document::TYPE_CREDIT, $result->type);
        $this->assertSame('FRS2026-00001', $result->number);
    }

    /**
     * A full page without a match: the next page is read with the created_before cursor.
     *
     * @return void
     */
    public function testCreditLookupPagesWithCreatedBefore(): void
    {
        $this->client->method('post')->willThrowException($this->taken());
        $queries = [];
        $this->client->method('get')
            ->willReturnCallback(function (string $path, array $query) use (&$queries): array {
                if ($path !== 'credits') {
                    return Fixture::load('credit-issued.json');
                }
                $queries[] = $query;
                return isset($query['created_before'])
                    ? Fixture::load('credits-list.json')
                    : $this->otherCredits(800000, 1793800000);
            });

        $result = $this->provider->send($this->refund('P1-399', 399.00, 1793700000));

        $this->assertCount(2, $queries);
        $this->assertSame('799901', $queries[1]['created_before']);
        $this->assertSame('FRS2026-00001', $result->number);
    }

    /**
     * Paging stops once the credits are older than the original (minus one day of margin).
     *
     * @return void
     */
    public function testCreditLookupStopsBeforeTheOriginal(): void
    {
        $this->client->method('post')->willThrowException($this->taken());
        $this->client->expects($this->once())->method('get')
            ->with('credits', ['limit' => '100', 'contact' => '9795037'], 1)
            ->willReturn($this->otherCredits(800000, 1793600000));

        $this->expectException(DuplicateException::class);
        $this->provider->send($this->refund('P1-399', 399.00, 1793700000));
    }

    /**
     * A credit with the same prefix but another amount is not taken for ours.
     *
     * @return void
     */
    public function testRefundLookupIgnoresCreditWithOtherAmount(): void
    {
        $this->client->method('post')->willThrowException($this->taken());
        $this->client->method('get')->willReturn(Fixture::load('credits-list.json'));

        $this->expectException(DuplicateException::class);
        $this->provider->send($this->refund('P1-399', 100.00));
    }

    /**
     * Other 4xx errors are permanent and not retried as lookups.
     *
     * @return void
     */
    public function testValidationErrorIsRethrown(): void
    {
        $this->client->method('post')->willThrowException(
            new HttpException(__('bad'), null, 0, 422, ['errors' => ['items' => ['is invalid']]])
        );
        $this->client->expects($this->never())->method('get');

        $this->expectException(HttpException::class);
        $this->provider->send($this->sale('X-1', 10.00));
    }

    /**
     * 422 "processor_id has already been taken" (Quaderno sandbox).
     *
     * @return HttpException
     */
    private function taken(): HttpException
    {
        return new HttpException(
            __('taken'),
            null,
            0,
            422,
            Fixture::load('error-422-taken.json')
        );
    }

    /**
     * Minimal sale.
     *
     * @param string $processorId
     * @param float $total
     * @return DocumentData
     */
    private function sale(string $processorId, float $total): DocumentData
    {
        return new DocumentData(
            DocumentData::TRANSACTION_SALE,
            $processorId,
            'EUR',
            new CustomerData('person', 'Cliente', 'Prueba', 'ES'),
            [new LineData('Producto', $total, 21.0, 'standard')],
            $total,
            1
        );
    }

    /**
     * A full page (100) of other credits, newest first, one second apart.
     *
     * @param int $firstId
     * @param int $firstTime
     * @return array<int,array<string,mixed>>
     */
    private function otherCredits(int $firstId, int $firstTime): array
    {
        $list = [];
        for ($i = 0; $i < 100; $i++) {
            $list[] = ['id' => $firstId - $i, 'processor_id' => 'other_1', 'total_cents' => 100,
                'created_at' => $firstTime - $i];
        }
        return $list;
    }

    /**
     * Minimal refund of the original's contact 9795037.
     *
     * @param string $processorId
     * @param float $total
     * @param int|null $originalCreatedAt
     * @return DocumentData
     */
    private function refund(string $processorId, float $total, ?int $originalCreatedAt = null): DocumentData
    {
        return new DocumentData(
            DocumentData::TRANSACTION_REFUND,
            $processorId,
            'EUR',
            new CustomerData('person', 'Cliente', 'Prueba', 'ES', null, null, null, null, null, null, null, '9795037'),
            [new LineData('Producto', $total, 21.0, 'standard')],
            $total,
            1,
            'other',
            null,
            $originalCreatedAt
        );
    }
}
