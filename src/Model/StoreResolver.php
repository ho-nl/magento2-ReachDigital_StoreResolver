<?php
/**
 * Copyright (c) 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\StoreResolver\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\GroupRepositoryInterface;
use Magento\Store\Api\StoreCookieManagerInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreIsInactiveException;

class StoreResolver implements \Magento\Store\Api\StoreResolverInterface
{
    /**
     * Cache tag
     */
    const CACHE_TAG = 'store_relations';

    /** @var StoreRepositoryInterface $storeRepository */
    private $storeRepository;

    /** @var StoreCookieManagerInterface $storeCookieManager */
    private $storeCookieManager;

    /** @var \Magento\Framework\Cache\FrontendInterface $cache */
    private $cache;

    /** @var \Magento\Store\Model\StoreResolver\ReaderList $readerList */
    private $readerList;

    /** @var string $runMode */
    private $runMode;

    /** @var null $scopeCode */
    private $scopeCode = null;

    /** @var RequestInterface $request */
    private $request;

    /** @var \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $configCollectionFactory */
    private $configCollectionFactory;

    /**
     * @var UrlInterface $urlInterface */
    private $urlInterface;

    /** @var GroupRepositoryInterface $groupRepository */
    private $groupRepository;

    /** @var WebsiteRepositoryInterface $websiteRepository */
    private $websiteRepository;

    /**
     * @param StoreRepositoryInterface                                          $storeRepository
     * @param StoreCookieManagerInterface                                       $storeCookieManager
     * @param RequestInterface                                                  $request
     * @param \Magento\Framework\Cache\FrontendInterface                        $cache
     * @param \Magento\Store\Model\StoreResolver\ReaderList                     $readerList
     * @param \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $configCollectionFactory
     * @param UrlInterface                                                      $urlInterface
     * @param GroupRepositoryInterface                                          $groupRepository
     * @param WebsiteRepositoryInterface                                        $websiteRepository
     */
    public function __construct(
        StoreRepositoryInterface $storeRepository,
        StoreCookieManagerInterface $storeCookieManager,
        RequestInterface $request,
        \Magento\Framework\Cache\FrontendInterface $cache,
        \Magento\Store\Model\StoreResolver\ReaderList $readerList,
        \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $configCollectionFactory,
        UrlInterface $urlInterface,
        GroupRepositoryInterface $groupRepository,
        WebsiteRepositoryInterface $websiteRepository
    ) {
        $this->storeRepository         = $storeRepository;
        $this->storeCookieManager      = $storeCookieManager;
        $this->request                 = $request;
        $this->cache                   = $cache;
        $this->readerList              = $readerList;
        $this->runMode                 = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE;
        $this->configCollectionFactory = $configCollectionFactory;
        $this->urlInterface            = $urlInterface;
        $this->groupRepository         = $groupRepository;
        $this->websiteRepository       = $websiteRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentStoreId()
    {
        [$stores, $defaultStoreId] = $this->getStoresData();

        // get ALL stores with their default store
        
        $storeCode = $this->request->getParam(self::PARAM_NAME, $this->storeCookieManager->getStoreCodeFromCookie());
        if (\is_array($storeCode)) {
            if (! isset($storeCode['_data']['code'])) {
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

            if (! \in_array($store->getId(), $stores, false)) {
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
    private function getStoresData(): array
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
    private function readStoresData(): array
    {
        $reader = $this->readerList->getReader($this->runMode);
        $allowedStores = [];
        foreach ($this->websiteRepository->getList() as $website) {
            $allowedStoreIds = $reader->getAllowedStoreIds($website->getCode());
            foreach ($allowedStoreIds as $allowedStoreId) {
                $allowedStores[] = $allowedStoreId;
            }
        }

        return [$allowedStores, $reader->getDefaultStoreId($this->scopeCode)];
    }

    /**
     * Get stores data
     *
     * @return array
     */
    private function getWebsitesData(): array
    {
        $cacheKey  = 'resolved_websites_' . $this->runMode .'_'. $this->scopeCode;
        $cacheData = $this->cache->load($cacheKey);
        if ($cacheData) {
            $websitesData = unserialize($cacheData);
        } else {
            $websitesData = $this->readWebsitesData();
            $this->cache->save(serialize($websitesData), $cacheKey, [self::CACHE_TAG]);
        }

        return $websitesData;
    }

    /**
     * Read websites data. First element is allowed website id, second is default store id
     */
    private function readWebsitesData(): array
    {
        $defaultStores = [];
        foreach ($this->websiteRepository->getList() as $website) {
            $defaultStores[$website->getId()] = $this->groupRepository->get($website->getDefaultGroupId())->getDefaultStoreId();
        }

        return $defaultStores;
    }

    /**
     * Automatically resolve the URL to a website.
     *
     * @throws NoSuchEntityException
     *
     * @return bool|int
     */
    private function getAutoResolvedStore()
    {
        $websites = $this->getWebsitesData();
        $scope = 'store';
        $currentUrl = $this->urlInterface->getCurrentUrl();

        $found = array_filter($this->getAutoResolveData($scope), function ($storeUrl) use ($currentUrl) {
            $currentUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $currentUrl), '/');
            $storeUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $storeUrl), '/');

            return stripos($currentUrlIdentifier, $storeUrlIdentifier) === 0;
        });

        // see if url is defined at website scope
        if (count($found) === 0) {
            $scope = 'website';
            $found = array_filter($this->getAutoResolveData($scope), function ($storeUrl) use ($currentUrl) {
                $currentUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $currentUrl), '/');
                $storeUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $storeUrl), '/');

                return stripos($currentUrlIdentifier, $storeUrlIdentifier) === 0;
            });
        }

        if (count($found) === 1) {
            if ($scope === 'store') {
                return current(array_flip($found));
            }

            return $websites[current(array_flip($found))];
        }

        if (count($found) > 1) {
            if ($scope === 'store') {
                $storeId = current(array_flip($found));
            } else {
                // Should never happen (2 websites with the same url) but could be a wrong configuration.
                // Get the first one.
                $storeId = $websites[current(array_flip($found))];
            }

            // get the default store for this website because all it's stores have the same url
            $store = $this->getDefaultStoreById($storeId);
            $group = $this->groupRepository->get($store->getStoreGroupId());

            return $group->getDefaultStoreId();
        }

        return false;
    }

    /**
     * Get a map of URL's to website mapping
     *
     * @param string $scope
     *
     * @return int[]
     */
    public function getAutoResolveData($scope = 'store'): array
    {
        $cacheKey  = 'auto_resolved_stores_' . $scope . '_' . $this->runMode .'_'. $this->scopeCode;
        $cacheData = $this->cache->load($cacheKey);
        if ($cacheData) {
            $storesData = unserialize($cacheData);
        } else {
            $storesData = $this->readAutoResolveData($scope);
            $this->cache->save(serialize($storesData), $cacheKey, [self::CACHE_TAG]);
        }

        return $storesData;
    }

    /**
     * Load a map of URL's to website mapping
     *
     * @param string $scope
     *
     * @return int[]
     */
    private function readAutoResolveData($scope = 'store'): array
    {
        $configCollection = $this->configCollectionFactory->create();
        $configCollection->addFieldToFilter('path', 'web/unsecure/base_url');
        if ($scope === 'store') {
            $configCollection->addFieldToFilter('scope', \Magento\Store\Model\ScopeInterface::SCOPE_STORES);
        } else {
            $configCollection->addFieldToFilter('scope', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES);
        }

        $configCollection->getSelect()->reset('columns')->columns(['scope_id', 'value']);

        return $configCollection->getConnection()->fetchPairs($configCollection->getSelect());
    }

    /**
     * Retrieve active store by code
     *
     * @param string $storeCode
     *
     * @throws NoSuchEntityException
     *
     * @return StoreInterface
     */
    private function getRequestedStoreByCode($storeCode): StoreInterface
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
     * @throws NoSuchEntityException
     *
     * @return StoreInterface
     */
    private function getDefaultStoreById($id): StoreInterface
    {
        try {
            $store = $this->storeRepository->getActiveStoreById($id);
        } catch (StoreIsInactiveException $e) {
            throw new NoSuchEntityException(__('Default store is inactive'));
        }

        return $store;
    }
}
