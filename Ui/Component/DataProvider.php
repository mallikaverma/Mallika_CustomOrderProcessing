<?php
namespace Mallika\CustomOrderProcessing\Ui\Component;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Mallika\CustomOrderProcessing\Model\ResourceModel\OrderStatusLog\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    protected $collection;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        return [
            'totalRecords' => $this->collection->getSize(),
            'items' => $this->collection->toArray()['items'],
        ];
    }
}