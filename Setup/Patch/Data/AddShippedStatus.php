<?php
namespace Mallika\CustomOrderProcessing\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\Order\StatusFactory;

class AddShippedStatus implements DataPatchInterface
{
    private $statusFactory;
    private $statusResource;

    public function __construct(
        StatusFactory $statusFactory,
        StatusResource $statusResource
    ) {
        $this->statusFactory = $statusFactory;
        $this->statusResource = $statusResource;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    public function apply()
    {
        $status = $this->statusFactory->create();
        $status->setData([
            'status' => 'shipped',
            'label' => 'Shipped'
        ]);
        $this->statusResource->save($status);

        $status->assignState(Order::STATE_PROCESSING, false, true);

        return $this;
    }
}