<?php

namespace Mallika\CustomOrderProcessing\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;

class OrderStatusLogIndexer implements ActionInterface, MviewActionInterface
{
    public function execute($ids = [])
    {
        // Index specific entities by ID
    }

    public function executeFull()
    {
        // Rebuild full index logic
    }

    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    public function executeRow($id)
    {
        $this->execute([$id]);
    }
}
