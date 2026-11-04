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
use Magento\Ui\Component\MassAction\Filter;
use Normaro\Verifactu\Model\Document;
use Normaro\Verifactu\Model\ResourceModel\Document\CollectionFactory;
use Normaro\Verifactu\Model\Resender;

/**
 * Mass resend from the Verifactu documents grid.
 */
class MassResend extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Normaro_Verifactu::resend';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param Resender $resender
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly Resender $resender
    ) {
        parent::__construct($context);
    }

    /**
     * Resend the selected rows.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $done = 0;
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            foreach ($collection as $row) {
                if ($row instanceof Document && !$row->isIssued()) {
                    $this->resender->resend($row);
                    $done++;
                }
            }
            $this->messageManager->addSuccessMessage((string)__('%1 Verifactu document(s) resent.', $done));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
