<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ho\StoreResolver\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\StoreManagerInterface;

class StoreUrls
{
    /**
     * @var null|string[]
     */
    private $baseUrls = null;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var StoreFactory
     */
    private $storeFactory;

    public function __construct(
        StoreManagerInterface $storeManager,
        StoreFactory $storeFactory,
        ResourceConnection $resource
    ) {
        $this->storeManager = $storeManager;
        $this->storeFactory = $storeFactory;
        $this->resource = $resource;
    }

    /**
     * @param Http $request
     * @return false|string
     */
    public function getStoreCodeByRequest(Http $request)
    {
        /** @var \Zend\Uri\Http $uri */
        $uri = $request->getUri();
        $host = $uri->getHost();
        if (!$host) {
            $host = '';
        }

        return $this->getStoreCodeByHostAndPath($host, $request->getPathInfo());
    }

    /**
     * @param string $host
     * @param string $pathInfo
     * @return false|string
     */
    public function getStoreCodeByHostAndPath(string $host, string $pathInfo)
    {
        $pathParts = explode('/', trim($pathInfo, '/'));
        $customPath = reset($pathParts);

        return $this->getStoreCodeByBaseUrl($host . '/' . $customPath);
    }

    /**
     * @param string $url
     * @return false|string
     */
    public function getStoreCodeByBaseUrl(string $url)
    {
        $url = rtrim(str_replace(['www.', 'http://', 'https://'], '', $url), '/');
        $baseUrls = $this->getBaseUrls();

        $storeCode = array_search($url, $baseUrls);

        if (!$storeCode) {
            // Check if there is a matching URL without custom path after TLD
            $baseUrl = explode('/', $url)[0];
            $storeCode = array_search($baseUrl, $baseUrls);
        }

        return $storeCode;
    }

    /**
     * @return array
     */
    public function getBaseUrls()
    {
        $connection = $this->resource->getConnection();

        if (is_null($this->baseUrls)) {
            $baseUrls = [];

            $select = $select = $connection->select()
                ->from($connection->getTableName('core_config_data'))
                ->reset(\Zend_Db_Select::COLUMNS)
                ->columns('value')
                ->where('path = ?', Store::XML_PATH_UNSECURE_BASE_URL)
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0);
            $defaultBaseUrl = $connection->fetchOne($select);

            foreach ($this->storeManager->getWebsites() as $website) {
                $storeCollection = $this->storeFactory
                    ->create()
                    ->getCollection()
                    ->addWebsiteFilter($website->getId());

                $select = $connection->select()
                    ->from($connection->getTableName('core_config_data'))
                    ->reset(\Zend_Db_Select::COLUMNS)
                    ->columns('value')
                    ->where('path = ?', Store::XML_PATH_UNSECURE_BASE_URL)
                    ->where('scope = ?', ScopeInterface::SCOPE_WEBSITES)
                    ->where('scope_id = ?', $website->getId());
                $websiteBaseUrl = $connection->fetchOne($select) ?: $defaultBaseUrl;

                foreach ($storeCollection as $store) {
                    /** @var Store $store */
                    $select = $connection->select()
                        ->from($connection->getTableName('core_config_data'))
                        ->reset(\Zend_Db_Select::COLUMNS)
                        ->columns('value')
                        ->where('path = ?', Store::XML_PATH_UNSECURE_BASE_URL)
                        ->where('scope = ?', ScopeInterface::SCOPE_STORES)
                        ->where('scope_id = ?', $store->getStoreId());

                    $storeBaseUrl = $connection->fetchOne($select);

                    $baseUrl = $storeBaseUrl ?: $websiteBaseUrl;
                    $baseUrls[$store->getCode()] = rtrim(str_replace(['www.', 'http://', 'https://'], '', $baseUrl), '/');
                }
            }

            $this->baseUrls = $baseUrls;
        }

        return $this->baseUrls;
    }
}
