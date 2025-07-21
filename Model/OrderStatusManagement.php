<?php
namespace Mallika\CustomOrderProcessing\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as StatusCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class OrderStatusManagement
 * Handles updating the status of Magento orders via custom API logic.
 */
class OrderStatusManagement implements \Mallika\CustomOrderProcessing\Api\OrderStatusInterface
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var LoggerInterface */
    private $logger;

    /** @var StatusCollectionFactory */
    private $statusCollectionFactory;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var RequestInterface */
    private $request;

    /** @var CacheInterface */
    private $cache;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var Json */
    private $json;

    /**
     * Constructor
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     * @param StatusCollectionFactory $statusCollectionFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param CacheInterface $cache
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $json
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        StatusCollectionFactory $statusCollectionFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        CacheInterface $cache,
        ScopeConfigInterface $scopeConfig,
        Json $json
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->request = $request;
        $this->cache = $cache;
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
    }

    /**
     * Update the status of a Magento order by increment ID.
     * Includes rate limiting to prevent abuse.
     *
     * @param string $orderIncrementId
     * @param string $status
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateOrderStatus($orderIncrementId, $status)
    {
        $clientIp = $this->request->getClientIp();
        $enabled = $this->scopeConfig->isSetFlag(
            'custom_order_processing/rate_limit/enabled',
            ScopeInterface::SCOPE_STORE
        );
        $maxRequests = (int) $this->scopeConfig->getValue(
            'custom_order_processing/rate_limit/max_requests',
            ScopeInterface::SCOPE_STORE
        ) ?: 5;

        try {
            if ($enabled) {
                $cacheKey = 'order_status_rate_limit_' . md5($clientIp);
                $requestData = $this->cache->load($cacheKey);
                $requests = $requestData ? $this->json->unserialize($requestData) : [];

                $oneMinuteAgo = time() - 60;
                $requests = array_filter($requests, function ($timestamp) use ($oneMinuteAgo) {
                    return $timestamp >= $oneMinuteAgo;
                });

                if (count($requests) >= $maxRequests) {
                    throw new LocalizedException(
                        __('Rate limit exceeded. Please try again after a minute.')
                    );
                }

                $requests[] = time();
                $this->cache->save($this->json->serialize($requests), $cacheKey, [], 60);
            }

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $orderIncrementId)
                ->create();

            $orderList = $this->orderRepository->getList($searchCriteria)->getItems();

            if (empty($orderList)) {
                throw new NoSuchEntityException(
                    __('Order with increment ID "%1" does not exist.', $orderIncrementId)
                );
            }

            $order = reset($orderList);
            $allowedStatuses = $this->getAllowedStatuses();

            if (!in_array($status, $allowedStatuses)) {
                throw new StateException(
                    __('Invalid status "%1". Allowed statuses: %2', $status, implode(', ', $allowedStatuses))
                );
            }

            $order->setStatus($status)
                  ->setState($this->getOrderStateByStatus($status));
            $this->orderRepository->save($order);

            return true;
        } catch (LocalizedException $e) {
            // Known Magento errors: rethrow and log
            $this->logger->error('Order status update error: ' . $e->getMessage(), [
                'order_increment_id' => $orderIncrementId,
                'status' => $status,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            // Unknown error: log detailed stack trace and throw generic error to user
            $this->logger->critical('Unexpected exception during order status update', [
                'exception' => $e,
                'order_increment_id' => $orderIncrementId,
                'status' => $status,
            ]);
            throw new LocalizedException(__('Something went wrong while updating the order status.'));
        }
    }

    /**
     * Retrieve a list of all allowed order statuses.
     * Caches result for 24 hours to avoid repeated DB queries.
     *
     * @return array
     */
    private function getAllowedStatuses()
    {
        $cacheKey = 'mallika_allowed_order_statuses';
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return $this->json->unserialize($cached);
        }

        $statusCollection = $this->statusCollectionFactory->create();
        $statuses = $statusCollection->getColumnValues('status');

        $this->cache->save($this->json->serialize($statuses), $cacheKey, [], 86400); // cache for 1 day
        return $statuses;
    }

    /**
     * Get the order state corresponding to a given status.
     * Falls back to "processing" if not found.
     * Result is cached for 24 hours.
     *
     * @param string $status
     * @return string
     * @throws StateException
     */
    private function getOrderStateByStatus($status)
    {
        $cacheKey = 'mallika_order_state_by_status_' . $status;
        $cachedState = $this->cache->load($cacheKey);
        if ($cachedState) {
            return $cachedState;
        }

        $statusCollection = $this->statusCollectionFactory->create();
        $statusItem = $statusCollection->addFieldToFilter('status', $status)->getFirstItem();

        if (!$statusItem->getId()) {
            throw new StateException(__('Status "%1" is not configured in Magento.', $status));
        }

        $state = $statusItem->getData('state') ?: Order::STATE_PROCESSING;
        $this->cache->save($state, $cacheKey, [], 86400); // cache for 1 day

        return $state;
    }
}
