<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Controller\Adminhtml\Document;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\DocumentRepository;
use Normaro\Verifactu\Model\Resender;
use Normaro\Verifactu\Model\Sender;

/**
 * Resend one document (button on the invoice / credit memo page).
 */
class Resend extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Normaro_Verifactu::resend';

    /**
     * @param Context $context
     * @param DocumentRepository $documentRepository
     * @param Resender $resender
     */
    public function __construct(
        Context $context,
        private readonly DocumentRepository $documentRepository,
        private readonly Resender $resender
    ) {
        parent::__construct($context);
    }

    /**
     * Resend and go back.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $row = $this->documentRepository->getById((int)$this->getRequest()->getParam('id'));
            $status = $this->resender->resend($row);
            $label = $row->getMagentoLabel();
            $statusText = Document::STATUS_LABELS[$status] ?? $status;
            $statusLabel = __($statusText);
            $this->messageManager->addSuccessMessage(in_array($status, Sender::REFRESH_STATUSES, true)
                ? (string)__('Verifactu, %1: %2; a refresh from Quaderno is queued.', $label, $statusLabel)
                : (string)__('Verifactu, %1: %2.', $label, $statusLabel));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }
}
