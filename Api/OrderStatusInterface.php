<?php
namespace Mallika\CustomOrderProcessing\Api;

interface OrderStatusInterface
{
    /**
     * Update order status
     * @param string $orderId
     * @param string $status
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    public function updateOrderStatus($orderId, $status);
}