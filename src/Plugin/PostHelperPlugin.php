<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Ho\StoreResolver\Model\StoreUrls;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Helper\PostHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class PostHelperPlugin
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var Http
     */
    private $request;
    /**
     * @var StoreUrls
     */
    private $storeUrls;
    /**
     * @var ProductMetadata
     */
    private $productMetadata;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Http $request
     * @param StoreUrls $storeUrls
     * @param ProductMetadata $productMetadata
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Http $request,
        StoreUrls $storeUrls,
        ProductMetadata $productMetadata
    ) {
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->storeUrls = $storeUrls;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Support storeswitcher URLs before Magento v2.3.0
     *
     * @param PostHelper $subject
     * @param $url
     * @param array $data
     * @return array
     */
    public function beforeGetPostData(PostHelper $subject, $url, array $data = [])
    {
        if ($this->productMetadata->getVersion() >= '2.3.0') {
            return [$url, $data];
        }

        $storeCode = $this->storeUrls->getStoreCodeByHostAndPath(
            $this->request->getHttpHost(),
            $this->request->getOriginalPathInfo()
        );

        try {
            /** @var \Magento\Store\Model\Store $store */
            $store = $this->storeManager->getStore($storeCode);
        } catch (NoSuchEntityException $e) {
            return [$url, $data];
        }

        $baseUrl = $store->getBaseUrl();
        $strippedBaseUrl = rtrim(str_replace(['www.', 'http://', 'https://'], '', $baseUrl), '/');
        $urlParts = explode('/', $strippedBaseUrl);
        if (count($urlParts) > 1) {
            $position = strpos($url, $urlParts[1]);
            if ($position !== false) {
                // Remove first occurence of custom path of referer store in URL
                $url = substr_replace($url, '', $position, strlen($urlParts[1] . '/'));
            }
        }

        return [$url, $data];
    }

}
