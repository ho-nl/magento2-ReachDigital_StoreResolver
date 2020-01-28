<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ho\StoreResolver\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Store\Model\StoreManagerInterface;

class StoreUrls
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
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
        $baseUrls = [];
        foreach ($this->storeManager->getStores() as $store) {
            $baseUrl = $this->scopeConfig->getValue(
                \Magento\Store\Model\Store::XML_PATH_UNSECURE_BASE_URL,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $store->getId());

            $baseUrl = rtrim(str_replace(['www.', 'http://', 'https://'], '', $baseUrl), '/');
            $baseUrls[$store->getCode()] = $baseUrl;
        }

        return $baseUrls;
    }
}
