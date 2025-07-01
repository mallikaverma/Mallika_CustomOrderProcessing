<?php
namespace Mallika\CustomOrderProcessing\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Mallika\CustomOrderProcessing\Model\OrderStatusManagement;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection;
use Magento\Sales\Model\Order\Status;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Psr\Log\LoggerInterface;

class OrderStatusManagementTest extends TestCase
{
    private $orderRepository;
    private $logger;
    private $statusCollectionFactory;
    private $searchCriteriaBuilder;
    private $orderStatusManagement;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->statusCollectionFactory = $this->createMock(CollectionFactory::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $objectManager = new ObjectManager($this);
        $this->orderStatusManagement = $objectManager->getObject(
            OrderStatusManagement::class,
            [
                'orderRepository' => $this->orderRepository,
                'logger' => $this->logger,
                'statusCollectionFactory' => $this->statusCollectionFactory,
                'searchCriteriaBuilder' => $this->searchCriteriaBuilder
            ]
        );
    }

    public function testUpdateOrderStatusSuccess()
    {
        $orderIncrementId = '000000123';
        $status = 'shipped';
        $order = $this->createMock(Order::class);
        $statusCollection = $this->createMock(Collection::class);
        $statusItem = $this->createMock(Status::class);
        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $this->searchCriteriaBuilder->expects($this->once())
            ->method('addFilter')->with('increment_id', $orderIncrementId)->willReturnSelf();
        $this->searchCriteriaBuilder->expects($this->once())
            ->method('create')->willReturn($searchCriteria);
        $this->orderRepository->expects($this->once())
            ->method('getList')->with($searchCriteria)->willReturn(new \Magento\Framework\DataObject(['items' => [$order]]));
        $order->expects($this->once())->method('setStatus')->with($status)->willReturnSelf();
        $order->expects($this->once())->method('setState')->with(Order::STATE_PROCESSING)->willReturnSelf();
        $this->orderRepository->expects($this->once())
            ->method('save')->with($order);
        $this->statusCollectionFactory->expects($this->exactly(2))->method('create')->willReturn($statusCollection);
        $statusCollection->expects($this->once())->method('getColumnValues')->with('status')->willReturn(['pending', 'processing', 'complete', 'canceled', 'shipped']);
        $statusCollection->expects($this->once())->method('addFieldToFilter')->with('status', $status)->willReturnSelf();
        $statusCollection->expects($this->once())->method('getFirstItem')->willReturn($statusItem);
        $statusItem->expects($this->once())->method('getId')->willReturn(1);
        $statusItem->expects($this->once())->method('getData')->with('state')->willReturn(Order::STATE_PROCESSING);

        $result = $this->orderStatusManagement->updateOrderStatus($orderIncrementId, $status);
        $this->assertTrue($result);
    }

    public function testUpdateOrderStatusInvalidOrder()
    {
        $orderIncrementId = '999999999';
        $status = 'shipped';
        $statusCollection = $this->createMock(Collection::class);
        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $this->searchCriteriaBuilder->expects($this->once())
            ->method('addFilter')->with('increment_id', $orderIncrementId)->willReturnSelf();
        $this->searchCriteriaBuilder->expects($this->once())
            ->method('create')->willReturn($searchCriteria);
        $this->orderRepository->expects($this->once())
            ->method('getList')->with($searchCriteria)->willReturn(new \Magento\Framework\DataObject(['items' => []]));
        $this->statusCollectionFactory->expects($this->never())->method('create');
        $this->logger->expects($this->once())->method('error');

        $this->expectException(\Magento\Framework\Exception\NoSuchEntityException::class);
        $this->expectExceptionMessage('Order with increment ID "999999999" does not exist.');
        $this->orderStatusManagement->updateOrderStatus($orderIncrementId, $status);
    }

    public function testUpdateOrderStatusInvalidStatus()
    {
        $orderIncrementId = '000000123';
        $status = 'invalid';
        $order = $this->createMock(Order::class);
        $statusCollection = $this->createMock(Collection::class);
        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);

        $this->searchCriteriaBuilder->expects($this->once())
            ->method('addFilter')->with('increment_id', $orderIncrementId)->willReturnSelf();
        $this->searchCriteriaBuilder->expects($this->once())
            ->method('create')->willReturn($searchCriteria);
        $this->orderRepository->expects($this->once())
            ->method('getList')->with($searchCriteria)->willReturn(new \Magento\Framework\DataObject(['items' => [$order]]));
        $this->statusCollectionFactory->expects($this->once())->method('create')->willReturn($statusCollection);
        $statusCollection->expects($this->once())->method('getColumnValues')->with('status')->willReturn(['pending', 'processing', 'complete', 'canceled']);
        $this->logger->expects($this->once())->method('error');

        $this->expectException(\Magento\Framework\Exception\StateException::class);
        $this->expectExceptionMessageMatches('/Invalid status "invalid". Allowed statuses: pending, processing, complete, canceled/');
        $this->orderStatusManagement->updateOrderStatus($orderIncrementId, $status);
    }
}