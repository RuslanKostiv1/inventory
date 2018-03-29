<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Observer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Controller\Adminhtml\Product\Save;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryCatalog\Observer\SourceItemsProcessor;

/**
 * Process source items for configurable children products during product saving via controller.
 */
class ProcessSourceItemsObserver implements ObserverInterface
{
    /**
     * @var SourceItemsProcessor
     */
    private $sourceItemsProcessor;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param SourceItemsProcessor $sourceItemsProcessor
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        SourceItemsProcessor $sourceItemsProcessor,
        ProductRepositoryInterface $productRepository
    ) {
        $this->sourceItemsProcessor = $sourceItemsProcessor;
        $this->productRepository = $productRepository;
    }

    /**
     * @param EventObserver $observer
     *
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        /** @var ProductInterface $product */
        $product = $observer->getEvent()->getProduct();
            if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            return;
        }
        /** @var Save $controller */
        $controller = $observer->getEvent()->getController();
        $configurableMatrix = $controller->getRequest()->getParam('configurable-matrix-serialized', '');

        if ($configurableMatrix != '') {
            $productsData = json_decode($configurableMatrix, true);
            foreach ($productsData as $key => $productData) {
                if (isset($productData['quantity_per_source'])) {
                    $quantityPerSource = is_array($productData['quantity_per_source'])
                        ? $productData['quantity_per_source']
                        : [];

                    // get sku by child id, because child sku can be changed if product with such sku already exists.
                    $childProductId = $product->getExtensionAttributes()->getConfigurableProductLinks()[$key];
                    $childProduct = $this->productRepository->getById($childProductId);

                    $this->processSourceItems($quantityPerSource, $childProduct->getSku());
                }
            }
        }
    }

    /**
     * @param array $sourceItems
     * @param string $productSku
     *
     * @return void
     */
    private function processSourceItems(array $sourceItems, string $productSku)
    {
        foreach ($sourceItems as $key => $sourceItem) {
            if (!isset($sourceItem[SourceItemInterface::STATUS])) {
                $sourceItems[$key][SourceItemInterface::QUANTITY] = $sourceItems[$key]['quantity_per_source'];
                $sourceItems[$key][SourceItemInterface::STATUS]
                    = $sourceItems[$key][SourceItemInterface::QUANTITY] > 0 ? 1 : 0;
            }
        }

        $this->sourceItemsProcessor->process($productSku, $sourceItems);
    }
}
