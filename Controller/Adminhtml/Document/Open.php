<?php
/**
 * Copyright (c) Iurii Tkachenko. Licensed under OSL-3.0, see LICENSE.
 */
declare(strict_types=1);

namespace Normaro\Verifactu\Controller\Adminhtml\Document;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\DocumentRepository;

/**
 * "Read Details" of a Verifactu admin notification: opens the Magento invoice / credit memo of a row.
 *
 * The notification URL is created outside an admin session (queue consumer, cron), so it cannot carry
 * the URL secret key; this action accepts a request without it (it only redirects, changes nothing) and
 * the redirect target is a normal admin URL with the key. Login and ACL still apply.
 */
class Open extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Normaro_Verifactu::documents';

    /**
     * @var string[]
     */
    protected $_publicActions = ['open'];

    /**
     * @param Context $context
     * @param DocumentRepository $documentRepository
     */
    public function __construct(
        Context $context,
        private readonly DocumentRepository $documentRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Redirect to the invoice / credit memo page, or to the documents grid.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        try {
            $row = $this->documentRepository->getById((int)$this->getRequest()->getParam('id'));
        } catch (NoSuchEntityException $e) {
            return $redirect->setPath('verifactu/document/index');
        }
        $sourceId = (int)$row->getData('source_id');
        return $row->getData('source_type') === Document::SOURCE_CREDITMEMO
            ? $redirect->setPath('sales/creditmemo/view', ['creditmemo_id' => $sourceId])
            : $redirect->setPath('sales/invoice/view', ['invoice_id' => $sourceId]);
    }
}
