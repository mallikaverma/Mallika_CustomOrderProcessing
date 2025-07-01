<?php
namespace Mallika\CustomOrderProcessing\Model\ResourceModel\OrderStatusLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Mallika\CustomOrderProcessing\Model\OrderStatusLog::class,
            \Mallika\CustomOrderProcessing\Model\ResourceModel\OrderStatusLog::class
        );
    }
}