<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Inventory\Model\Stock\Command;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Validation\ValidationException;
use Magento\Inventory\Model\ResourceModel\Stock as StockResourceModel;
use Magento\Inventory\Model\Stock\Validator\StockValidatorInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Psr\Log\LoggerInterface;

/**
 * @inheritdoc
 */
class Save implements SaveInterface
{
    /**
     * @var StockValidatorInterface
     */
    private $stockValidator;

    /**
     * @var StockResourceModel
     */
    private $stockResource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param StockValidatorInterface $stockValidator
     * @param StockResourceModel $stockResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        StockValidatorInterface $stockValidator,
        StockResourceModel $stockResource,
        LoggerInterface $logger
    ) {
        $this->stockValidator = $stockValidator;
        $this->stockResource = $stockResource;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute(StockInterface $stock)
    {
        $validationResult = $this->stockValidator->validate($stock);
        if (!$validationResult->isValid()) {
            throw new ValidationException($validationResult);
        }

        try {
            $this->stockResource->save($stock);
            return $stock->getStockId();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new CouldNotSaveException(__('Could not save Stock'), $e);
        }
    }
}
