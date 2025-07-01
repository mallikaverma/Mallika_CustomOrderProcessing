<?php
namespace Mallika\CustomOrderProcessing\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as StatusCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;

class OrderStatusManagement implements \Mallika\CustomOrderProcessing\Api\OrderStatusInterface
{
    private $orderRepository;
    private $logger;
    private $statusCollectionFactory;
    private $searchCriteriaBuilder;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        \Psr\Log\LoggerInterface $logger,
        StatusCollectionFactory $statusCollectionFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function updateOrderStatus($orderIncrementId, $status)
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $orderIncrementId)
                ->create();
            $orderList = $this->orderRepository->getList($searchCriteria)->getItems();

            if (empty($orderList)) {
                throw new NoSuchEntityException(__('Order with increment ID "%1" does not exist.', $orderIncrementId));
            }

            /** @var Order $order */
            $order = reset($orderList);

            $allowedStatuses = $this->getAllowedStatuses();
            if (!in_array($status, $allowedStatuses)) {
                throw new StateException(
                    __('Invalid status "%1". Allowed statuses: %2', $status, implode(', ', $allowedStatuses))
                );
            }

            $order->setStatus($status)->setState($this->getOrderStateByStatus($status));
            $this->orderRepository->save($order);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Order status update failed: ' . $e->getMessage(), [
                'order_increment_id' => $orderIncrementId,
                'status' => $status
            ]);
            throw $e;
        }
    }

    /**
     * Get all allowed order statuses from Magento core
     *
     * @return array
     */
    private function getAllowedStatuses()
    {
        $statusCollection = $this->statusCollectionFactory->create();
        return $statusCollection->getColumnValues('status');
    }

    /**
     * Get the order state associated with a status using Magento core
     *
     * @param string $status
     * @return string
     * @throws StateException
     */
    private function getOrderStateByStatus($status)
    {
        $statusCollection = $this->statusCollectionFactory->create();
        /** @var \Magento\Sales\Model\Order\Status $statusItem */
        $statusItem = $statusCollection->addFieldToFilter('status', $status)->getFirstItem();

        if (!$statusItem->getId()) {
            throw new StateException(__('Status "%1" is not configured in Magento.', $status));
        }

        $state = $statusItem->getData('state');
        return $state ?: Order::STATE_PROCESSING; // Fallback to processing if no state is mapped
    }
}