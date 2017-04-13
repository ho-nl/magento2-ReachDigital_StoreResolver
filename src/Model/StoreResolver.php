<?php
/**
 * Copyright (c) 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\StoreResolver\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\StoreCookieManagerInterface;
use Magento\Store\Model\StoreIsInactiveException;

class StoreResolver implements \Magento\Store\Api\StoreResolverInterface
{
    /**
     * Cache tag
     */
    const CACHE_TAG = 'store_relations';

    /**
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var StoreCookieManagerInterface
     */
    private $storeCookieManager;

    /**
     * @var \Magento\Framework\Cache\FrontendInterface
     */
    private $cache;

    /**
     * @var \Magento\Store\Model\StoreResolver\ReaderList
     */
    private $readerList;

    /**
     * @var string
     */
    private $runMode;

    /**
     * @var string
     */
    private $scopeCode;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * @var \Magento\Store\Model\ResourceModel\Website\CollectionFactory
     */
    private $storeCollectionFactory;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory
     */
    private $configCollectionFactory;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlInterface;

    /**
     * StoreResolver constructor.
     *
     * @param \Magento\Store\Api\StoreRepositoryInterface                       $storeRepository
     * @param StoreCookieManagerInterface                                       $storeCookieManager
     * @param \Magento\Framework\App\RequestInterface                           $request
     * @param \Magento\Framework\Cache\FrontendInterface                        $cache
     * @param \Magento\Store\Model\StoreResolver\ReaderList                     $readerList
     * @param \Magento\Store\Model\ResourceModel\Store\CollectionFactory      $storeCollectionFactory
     * @param \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $configCollectionFactory
     * @param \Magento\Framework\UrlInterface                                   $urlInterface
     */
    public function __construct(
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        StoreCookieManagerInterface $storeCookieManager,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Cache\FrontendInterface $cache,
        \Magento\Store\Model\StoreResolver\ReaderList $readerList,
        \Magento\Store\Model\ResourceModel\Store\CollectionFactory $storeCollectionFactory,
        \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $configCollectionFactory,
        \Magento\Framework\UrlInterface $urlInterface
    ) {
        $this->storeRepository         = $storeRepository;
        $this->storeCookieManager      = $storeCookieManager;
        $this->request                 = $request;
        $this->cache                   = $cache;
        $this->readerList              = $readerList;
        $this->runMode                 = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE;
        $this->scopeCode               = null;
        $this->storeCollectionFactory  = $storeCollectionFactory;
        $this->configCollectionFactory = $configCollectionFactory;
        $this->urlInterface            = $urlInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentStoreId()
    {
        list($stores, $defaultStoreId) = $this->getStoresData();
        
        $storeCode = $this->request->getParam(self::PARAM_NAME, $this->storeCookieManager->getStoreCodeFromCookie());
        if (is_array($storeCode)) {
            if (!isset($storeCode['_data']['code'])) {
                throw new \InvalidArgumentException(__('Invalid store parameter.'));
            }
            $storeCode = $storeCode['_data']['code'];
        }
        if ($storeCode) {
            try {
                $store = $this->getRequestedStoreByCode($storeCode);
            } catch (NoSuchEntityException $e) {
                $store = $this->getDefaultStoreById($defaultStoreId);
            }
            if (!in_array($store->getId(), $stores)) {
                $store = $this->getDefaultStoreById($defaultStoreId);
            }
        } else {
            if ($resolvedStoreId = $this->getAutoResolvedStore()) {
                $defaultStoreId = $resolvedStoreId;
            }
            $store = $this->getDefaultStoreById($defaultStoreId);
        }
        return $store->getId();
    }

    /**
     * Get stores data
     *
     * @return array
     */
    private function getStoresData()
    {
        $cacheKey  = 'resolved_stores_' . $this->runMode .'_'. $this->scopeCode;
        $cacheData = $this->cache->load($cacheKey);
        if ($cacheData) {
            $storesData = unserialize($cacheData);
        } else {
            $storesData = $this->readStoresData();
            $this->cache->save(serialize($storesData), $cacheKey, [self::CACHE_TAG]);
        }
        return $storesData;
    }

    /**
     * Read stores data. First element is allowed store ids, second is default store id
     */
    private function readStoresData()
    {
        $reader = $this->readerList->getReader($this->runMode);
        return [$reader->getAllowedStoreIds($this->scopeCode), $reader->getDefaultStoreId($this->scopeCode)];
    }

    /**
     * Automatically resolve the URL to a website.
     *
     * @return int
     */
    private function getAutoResolvedStore()
    {
        $currentUrl = $this->urlInterface->getCurrentUrl();

        $found = array_filter($this->getAutoResolveData(), function ($storeUrl) use ($currentUrl) {
            $currentUrlIdentifier = rtrim(str_replace(['www.', 'http://'], '', $currentUrl), '/');
            $storeUrlIdentifier = rtrim(str_replace(['www.', 'http://'], '', $storeUrl), '/');

            return stripos($currentUrlIdentifier, $storeUrlIdentifier) === 0;
        });

        if (count($found) == 1) {
            return current(array_flip($found));
        }

        return false;
    }

    /**
     * Get a map of URL's to website mapping
     * @return int[]
     */
    public function getAutoResolveData()
    {
        $cacheKey  = 'auto_resolved_stores_' . $this->runMode .'_'. $this->scopeCode;
        $cacheData = $this->cache->load($cacheKey);
        if ($cacheData) {
            $storesData = unserialize($cacheData);
        } else {
            $storesData = $this->readAutoResolveData();
            $this->cache->save(serialize($storesData), $cacheKey, [self::CACHE_TAG]);
        }
        return $storesData;
    }

    /**
     * Load a map of URL's to website mapping
     * @return int[]
     */
    private function readAutoResolveData()
    {
        $configCollection = $this->configCollectionFactory->create();
        $configCollection->addFieldToFilter('path', 'web/unsecure/base_url');
        $configCollection->addFieldToFilter('scope', \Magento\Store\Model\ScopeInterface::SCOPE_STORES);
        $configCollection->getSelect()->reset('columns')->columns(['scope_id', 'value']);

        $storeToUrl = $configCollection->getConnection()->fetchPairs($configCollection->getSelect());
        return $storeToUrl;
    }

    /**
     * Retrieve active store by code
     *
     * @param string $storeCode
     *
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws NoSuchEntityException
     */
    private function getRequestedStoreByCode($storeCode)
    {
        try {
            $store = $this->storeRepository->getActiveStoreByCode($storeCode);
        } catch (StoreIsInactiveException $e) {
            throw new NoSuchEntityException(__('Requested store is inactive'));
        }
        return $store;
    }

    /**
     * Retrieve active store by code
     *
     * @param int $id
     *
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws NoSuchEntityException
     */
    private function getDefaultStoreById($id)
    {
        try {
            $store = $this->storeRepository->getActiveStoreById($id);
        } catch (StoreIsInactiveException $e) {
            throw new NoSuchEntityException(__('Default store is inactive'));
        }
        return $store;
    }
}
