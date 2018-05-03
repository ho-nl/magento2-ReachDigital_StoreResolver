<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class BlockIndexPlugin
{
    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * @param \Magento\Swagger\Block\Index $subject
     * @param string                       $result
     *
     * @return string
     */
    public function afterGetSchemaUrl(\Magento\Swagger\Block\Index $subject, string $result): string
    {
        $pathParts = explode('/', rtrim($subject->getBaseUrl(), '/'));
        $storeCode = end($pathParts);

        try {
            $this->storeManager->getStore($storeCode);
        } catch (NoSuchEntityException $e) {
            return $result;
        }

        return rtrim($subject->getRequest()->getDistroBaseUrl(), '/')
            . '/rest/' .
            ($subject->getRequest()->getParam('store') ?: $storeCode)
            . '/schema?services=all';
    }
}
