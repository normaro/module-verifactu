<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Model\Quaderno;

use Normaro\Verifactu\Model\Aeat\Codes;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\Provider\ProviderResult;

/**
 * Quaderno document JSON -> ProviderResult.
 *
 * Status rules come from runs against the Quaderno sandbox:
 * - state "draft" or no number: not issued (receipt above the threshold without customer data,
 *   invalid NIF, unlinked refund);
 * - registration delivery "completed" + EstadoRegistro "Correcto": issued;
 * - "completed_with_warning" / "AceptadoConErrores": issued with warning;
 * - no delivery yet or "pending": issued, AEAT pending (minutes are normal);
 * - any other delivery state / EstadoRegistro "Incorrecto": rejected.
 */
class ResponseParser
{
    private const TYPE_BY_NAME = [
        'Invoice' => Document::TYPE_INVOICE,
        'Receipt' => Document::TYPE_RECEIPT,
        'Credit' => Document::TYPE_CREDIT,
    ];

    /**
     * Parse one document.
     *
     * @param array<mixed> $body
     * @param string|null $type type known from the endpoint (GET responses have no "type")
     * @return ProviderResult
     */
    public function parse(array $body, ?string $type = null): ProviderResult
    {
        $typeName = (string)($body['type'] ?? '');
        $docType = self::TYPE_BY_NAME[$typeName] ?? ($type ?? Document::TYPE_INVOICE);
        $number = $this->str($body['number'] ?? null);
        $state = (string)($body['state'] ?? '');

        [$status, $aeatStatus, $aeatMessage, $qrUrl] = $this->deliveryState($body);
        if ($state === 'draft' || $number === null) {
            $status = Document::STATUS_DRAFT;
            $aeatStatus = null;
        }

        return new ProviderResult(
            (string)($body['id'] ?? ''),
            $docType,
            $status,
            $number,
            $aeatStatus,
            $aeatMessage,
            $this->str($body['verification_permalink'] ?? null),
            $qrUrl,
            $this->str($body['pdf'] ?? null),
            $this->str($body['issue_date'] ?? null),
            $this->str($body['contact']['id'] ?? null),
            $this->str($body['related_document']['id'] ?? null),
            isset($body['total_cents']) ? (int)$body['total_cents'] : null,
            $this->str($body['processor_id'] ?? null),
            $body
        );
    }

    /**
     * Status from the last Verifactu registration delivery.
     *
     * @param array<mixed> $body
     * @return array{0:string,1:string|null,2:string|null,3:string|null}
     */
    private function deliveryState(array $body): array
    {
        $delivery = $this->registration($body);
        if ($delivery === null) {
            return [Document::STATUS_ISSUED, 'pending', null, null];
        }
        $state = (string)($delivery['state'] ?? '');
        $line = (array)($delivery['service_response']['respuesta_linea'] ?? []);
        $estado = $this->str($line['estado_registro'] ?? null);
        $message = $this->aeatMessage($line);
        $qr = $this->str($delivery['code'] ?? null);

        if ($state === 'pending' || $state === '') {
            return [Document::STATUS_ISSUED, 'pending', null, $qr];
        }
        if ($state === 'completed_with_warning' || $estado === Codes::ESTADO_ACEPTADO_CON_ERRORES) {
            return [Document::STATUS_ISSUED_WITH_WARNING, $estado ?? $state, $message, $qr];
        }
        if ($state === 'completed' && ($estado === null || $estado === Codes::ESTADO_CORRECTO)) {
            return [Document::STATUS_ISSUED, $estado ?? $state, null, $qr];
        }
        return [Document::STATUS_REJECTED, $estado ?? $state, $message, $qr];
    }

    /**
     * Last Verifactu registration delivery.
     *
     * @param array<mixed> $body
     * @return array<mixed>|null
     */
    private function registration(array $body): ?array
    {
        $delivery = null;
        foreach ((array)($body['deliveries'] ?? []) as $item) {
            if (is_array($item) && ($item['type'] ?? 'registration') === 'registration') {
                $delivery = $item;
            }
        }
        return $delivery;
    }

    /**
     * AEAT error code and description of a registration line.
     *
     * @param array<mixed> $line
     * @return string|null
     */
    private function aeatMessage(array $line): ?string
    {
        if (empty($line['codigo_error_registro'])) {
            return null;
        }
        return trim($line['codigo_error_registro'] . ' ' . ($line['descripcion_error_registro'] ?? ''));
    }

    /**
     * Non-empty string or null.
     *
     * @param mixed $value
     * @return string|null
     */
    private function str(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }
        return (string)$value;
    }
}
