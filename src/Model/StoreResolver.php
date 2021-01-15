<?php
/**
 * Copyright (c) 2017 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\StoreResolver\Model;

use http\Exception\InvalidArgumentException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
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
     * @var ResourceConnection
     */
    private $resource;

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
        WebsiteRepositoryInterface $websiteRepository,
        ResourceConnection $resource
    ) {
        $this->storeRepository = $storeRepository;
        $this->storeCookieManager = $storeCookieManager;
        $this->request = $request;
        $this->cache = $cache;
        $this->readerList = $readerList;
        $this->runMode = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE;
        $this->configCollectionFactory = $configCollectionFactory;
        $this->urlInterface = $urlInterface;
        $this->groupRepository = $groupRepository;
        $this->websiteRepository = $websiteRepository;
        $this->resource = $resource;
    }

    /**
     * {@inheritdoc}
     * @throws NoSuchEntityException
     */
    public function getCurrentStoreId()
    {
        [$stores, $defaultStoreId] = $this->getStoresData();

        // get ALL stores with their default store

        $storeCode = $this->request->getParam(self::PARAM_NAME, $this->storeCookieManager->getStoreCodeFromCookie());
        if (\is_array($storeCode)) {
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

            if (!\in_array($store->getId(), $stores, false)) {
                $store = $this->getDefaultStoreById($defaultStoreId);
            }
        } else {
            $currentUrl = $this->urlInterface->getCurrentUrl();
            if ($resolvedStoreId = $this->getStoreForUrl($currentUrl)) {
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
        $cacheKey = 'resolved_stores_' . $this->runMode . '_' . $this->scopeCode;
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
        $cacheKey = 'resolved_websites_' . $this->runMode . '_' . $this->scopeCode;
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
            $defaultStores[$website->getId()] = $this->groupRepository
                ->get($website->getDefaultGroupId())
                ->getDefaultStoreId();
        }

        return $defaultStores;
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
        $cacheKey = 'auto_resolved_stores_' . $scope . '_' . $this->runMode . '_' . $this->scopeCode;
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
        } elseif ($scope === 'website') {
            $configCollection->addFieldToFilter('scope', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES);
        } elseif ($scope === 'default') {
            $configCollection->addFieldToFilter('scope', 'default');
        } else {
            throw new \InvalidArgumentException(
                'Config scope has to be one of "store", "website" or "default". Provided scope was: ' . $scope
            );
        }

        $configCollection
            ->getSelect()
            ->reset('columns')
            ->columns(['scope_id', 'value']);
        $result = $configCollection->getConnection()->fetchPairs($configCollection->getSelect());

        if ($scope === 'default' && count($result) > 0) {
            // The url matches the default base url, however, we now need to know which of the stores uses the dafault URL.
            // This is the store that doesn't have a base url set at either store scope or website scope.
            $connection = $this->resource->getConnection();
            $defaultStoreSelect = $connection->select();
            $defaultStoreSelect
                ->from($connection->getTableName('store'), 'store_id')
                ->joinLeft(
                    ['store_urls' => $connection->getTableName('core_config_data')],
                    'store_urls.path = "web/unsecure/base_url" AND store_urls.scope = "stores" AND store_urls.scope_id = store_id',
                    []
                )
                ->joinLeft(
                    ['website_urls' => $connection->getTableName('core_config_data')],
                    'website_urls.path = "web/unsecure/base_url" AND website_urls.scope = "websites" AND website_urls.scope_id = website_id',
                    []
                )
                ->where('store_urls.value IS NULL')
                ->where('website_urls.value IS NULL')
                ->where('store_id > 0');
            $defaultStores = $connection->fetchAll($defaultStoreSelect);
            if (count($defaultStores) === 1) {
                $result = [(int) reset($defaultStores) => reset($result)];
            }
        }

        return $result;
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

    /**
     * Look up store associated with given URL, and return its ID.
     *
     * @param string $currentUrl
     *
     * @return bool|int|mixed store ID
     * @throws NoSuchEntityException
     */
    public function getStoreForUrl(string $currentUrl)
    {
        $websites = $this->getWebsitesData();
        $scope = 'store';
        $found = array_filter($this->getAutoResolveData($scope), static function ($storeUrl) use ($currentUrl) {
            $currentUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $currentUrl));
            $storeUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $storeUrl));
            return stripos($currentUrlIdentifier, $storeUrlIdentifier) === 0;
        });
        // see if url is defined at website scope
        if (count($found) === 0) {
            $scope = 'website';
            $found = array_filter($this->getAutoResolveData($scope), static function ($storeUrl) use ($currentUrl) {
                $currentUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $currentUrl));
                $storeUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $storeUrl));
                return stripos($currentUrlIdentifier, $storeUrlIdentifier) === 0;
            });
        }
        // see if url is defined at default scope
        if (count($found) === 0) {
            $scope = 'default';
            $found = array_filter($this->getAutoResolveData($scope), static function ($storeUrl) use ($currentUrl) {
                $currentUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $currentUrl));
                $storeUrlIdentifier = rtrim(str_replace(['www.', 'http://', 'https://'], '', $storeUrl));
                return stripos($currentUrlIdentifier, $storeUrlIdentifier) === 0;
            });
        }
        if (count($found) === 1) {
            if ($scope === 'website') {
                // The id that is used as a key in $found is a website id, so look up the corresponding store id.
                return $websites[current(array_flip($found))];
            }
            return current(array_flip($found));
        }
        if (count($found) > 1) {
            if ($scope === 'store') {
                // It is possible to find multiple stores when one store url is a substring of another.
                // In that case we need to return the longest matching store url.
                // To do this we sort the stores in descending order of url length.
                uasort($found, function ($a, $b) {
                    return strlen($b) - strlen($a);
                });
                return current(array_flip($found));
            } else {
                // Should never happen (2 websites with the same url) but could be a wrong configuration.
                // Get the first one.
                // @fixme Maybe throw an exception instead of trying to make sense of senseless configurations
                $storeId = $websites[current(array_flip($found))];
            }
            // get the default store for this website because all it's stores have the same url
            $store = $this->getDefaultStoreById($storeId);
            $group = $this->groupRepository->get($store->getStoreGroupId());
            return $group->getDefaultStoreId();
        }
        if (count($found) === 0 && parse_url($currentUrl, PHP_URL_PATH) === '/robots.txt') {
            // Handle robots.txt special case. For setups where stores have a path component in the base URL, try to map
            // by host only.
            $storeUrls = $this->getAutoResolveData('store');
            asort($storeUrls);
            $found = array_filter($storeUrls, static function ($storeUrl) use ($currentUrl) {
                return parse_url($storeUrl, PHP_URL_SCHEME) === parse_url($currentUrl, PHP_URL_SCHEME) &&
                    parse_url($storeUrl, PHP_URL_HOST) === parse_url($currentUrl, PHP_URL_HOST);
            });
            if (count($found) > 0) {
                return current(array_flip($found));
            }
        }
        return false;
    }
}
