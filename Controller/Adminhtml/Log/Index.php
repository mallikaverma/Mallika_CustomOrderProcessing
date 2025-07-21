<?php
namespace Mallika\CustomOrderProcessing\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Mallika_CustomOrderProcessing::order_log';

    protected $resultPageFactory;

    public function __construct(Action\Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Mallika_CustomOrderProcessing::order_log');
        $resultPage->getConfig()->getTitle()->prepend(__('Order Status Logs'));
        return $resultPage;
    }
}
