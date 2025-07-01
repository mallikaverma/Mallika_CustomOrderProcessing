<?php
namespace Mallika\CustomOrderProcessing\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OrderStatusLog extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('mallika_order_status_log', 'log_id');
    }
}