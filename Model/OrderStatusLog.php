<?php
namespace Mallika\CustomOrderProcessing\Model;

use Magento\Framework\Model\AbstractModel;

class OrderStatusLog extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Mallika\CustomOrderProcessing\Model\ResourceModel\OrderStatusLog::class);
    }
}