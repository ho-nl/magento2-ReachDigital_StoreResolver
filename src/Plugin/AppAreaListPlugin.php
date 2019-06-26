<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Ho\StoreResolver\Model\StoreUrls;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class AppAreaListPlugin
{
    /** @var Http $request */
    private $request;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /**
     * @var StoreUrls
     */
    private $storeUrls;

    /**
     * @param Http                  $request
     * @param StoreManagerInterface $storeManager
     * @param StoreUrls             $storeUrls
     */
    public function __construct(
        Http $request,
        StoreManagerInterface $storeManager,
        StoreUrls $storeUrls
    ) {
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->storeUrls = $storeUrls;
    }

    private static $modifiedOriginalPathInfo = false;

    /**
     * @fixme This plugin may no longer be needed with the TrimBaseUrlFromPathInfo plugin
     *
     * @param AreaList $subject
     * @param string   $frontName
     *
     * @return array
     */
    public function beforeGetCodeByFrontName(AreaList $subject, $frontName): array
    {
        $storeCode = $this->storeUrls->getStoreCodeByRequest($this->request);

        try {
            /** @var \Magento\Store\Model\Store $store */
            $store = $this->storeManager->getStore($storeCode);
        } catch (NoSuchEntityException $e) {
            return [$frontName];
        }

        $pathParts = explode('/', trim($this->request->getPathInfo(), '/'));

        $baseUrl = rtrim(str_replace(['www.', 'http://', 'https://'], '', $store->getBaseUrl()), '/');
        if (count(explode('/', $baseUrl)) > 1) {
            // Push custom path out array
            array_shift($pathParts);
        }

        $this->request->setPathInfo(implode('/', $pathParts) ?: '/');
        self::$modifiedOriginalPathInfo = implode('/', $pathParts) ?: '/';

        return [reset($pathParts) ?: ''];
    }

    public static function getModifiedOriginalPathInfo()
    {
        return self::$modifiedOriginalPathInfo;
    }
}
