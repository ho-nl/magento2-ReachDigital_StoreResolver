<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Ho\StoreResolver\Model\StoreUrls;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class BlockIndexPlugin
{
    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /**
     * @var StoreUrls
     */
    private $storeUrls;

    /**
     * @param StoreManagerInterface $storeManager
     * @param StoreUrls             $storeUrls
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        StoreUrls $storeUrls
    ) {
        $this->storeManager = $storeManager;
        $this->storeUrls = $storeUrls;
    }

    /**
     * @param \Magento\Swagger\Block\Index $subject
     * @param string                       $result
     *
     * @return string
     */
    public function afterGetSchemaUrl(\Magento\Swagger\Block\Index $subject, string $result): string
    {
        try {
            $storeCode = $this->storeUrls->getStoreCodeByBaseUrl($subject->getBaseUrl());
            $this->storeManager->getStore($storeCode);
        } catch (NoSuchEntityException $e) {
            return $result;
        }

        return rtrim($subject->getRequest()->getDistroBaseUrl(), '/')
            . '/rest/' .
            ($subject->getRequest()->getParam('store') ?: 'all')
            . '/schema?services=all';
    }
}
