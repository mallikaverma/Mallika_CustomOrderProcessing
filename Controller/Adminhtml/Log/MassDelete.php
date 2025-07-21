<?php

namespace Mallika\CustomOrderProcessing\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Ui\Component\MassAction\Filter;
use Mallika\CustomOrderProcessing\Model\ResourceModel\OrderStatusLog\CollectionFactory;

class MassDelete extends Action
{
    protected $filter;
    protected $collectionFactory;

    public function __construct(
        Action\Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
    }

    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;
            foreach ($collection as $item) {
                $item->delete();
                $deleted++;
            }
            $this->messageManager->addSuccessMessage(__('%1 record(s) have been deleted.', $deleted));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Something went wrong while deleting records.'));
        }

        return $this->_redirect('*/*/index');
    }
}
