<?php
namespace Mallika\CustomOrderProcessing\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class OrderStatusChange implements ObserverInterface
{
    private $orderStatusLogFactory;
    private $orderStatusLogResource;
    private $transportBuilder;
    private $storeManager;
    private $logger;

    public function __construct(
        \Mallika\CustomOrderProcessing\Model\OrderStatusLogFactory $orderStatusLogFactory,
        \Mallika\CustomOrderProcessing\Model\ResourceModel\OrderStatusLog $orderStatusLogResource,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->orderStatusLogFactory = $orderStatusLogFactory;
        $this->orderStatusLogResource = $orderStatusLogResource;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Observe sales_order_save_after event and log status changes
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $oldStatus = $order->getOrigData('status') ?: 'pending'; // Default to 'pending' if no original status
        $newStatus = $order->getStatus();

        if ($oldStatus !== $newStatus) {
            try {
                // Log status change to custom table
                $log = $this->orderStatusLogFactory->create();
                $log->setData([
                    'order_id' => $order->getIncrementId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $this->orderStatusLogResource->save($log);

                // Send email if status is 'shipped'
                if ($newStatus === 'shipped') {
                    $this->sendShippedEmail($order);
                }
            } catch (\Exception $e) {
                $this->logger->error('Order status change processing failed: ' . $e->getMessage(), [
                    'order_id' => $order->getIncrementId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
            }
        }
    }

    /**
     * Send email notification for shipped order
     *
     * @param Order $order
     * @return void
     * @throws LocalizedException
     */
    private function sendShippedEmail(Order $order)
    {
        try {
            $storeId = $order->getStoreId();
            $store = $this->storeManager->getStore($storeId);

            $this->transportBuilder
                ->setTemplateIdentifier('mallika_order_shipped_template')
                ->setTemplateOptions([
                    'area' => Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars([
                    'order_id' => $order->getIncrementId(),
                    'store' => $store,
                    'customer_name' => $order->getCustomerName()
                ])
                ->setFromByScope('general')
                ->addTo($order->getCustomerEmail(), $order->getCustomerName())
                ->getTransport()
                ->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error('Failed to send shipped email for order ' . $order->getId() . ': ' . $e->getMessage());
            throw new LocalizedException(__('Failed to send shipped email: %1', $e->getMessage()));
        }
    }
}