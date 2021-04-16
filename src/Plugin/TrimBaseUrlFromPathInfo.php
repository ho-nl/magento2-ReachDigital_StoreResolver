<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Ho\StoreResolver\Model\StoreUrls;
use Magento\Framework\App\Request\Http;

class TrimBaseUrlFromPathInfo
{
    /** @var StoreUrls $storeUrls */
    private $storeUrls;

    /**
     * @param StoreUrls\Proxy $storeUrls
     */
    public function __construct(StoreUrls\Proxy $storeUrls)
    {
        $this->storeUrls = $storeUrls;
    }

    /**
     * Strip URI part of base URL from original path info. Subject method does this only when storecodes are used in URLs.
     *
     * Needed for correct URL building when doing so for the current request while on a webshop's home page. See also
     * `\Magento\Framework\App\Router\Base::matchActionPath`, where an incorrect rewrite alias would be set.
     *
     * @param \Magento\Framework\App\Request\PathInfoProcessorInterface $subject
     * @param string                                                    $resultPathInfo
     * @param \Magento\Framework\App\RequestInterface                   $request
     * @param                                                           $pathInfo
     *
     * @return string
     */
    public function afterProcess(
        \Magento\Store\App\Request\PathInfoProcessor $subject,
        string $resultPathInfo,
        \Magento\Framework\App\RequestInterface $request,
        $pathInfo
    ): string {
        if (!$request instanceof Http) {
            return $resultPathInfo;
        }

        $storeCode = false;
        if ($request->getUriString() && $request->getUri()->getHost() !== '') {
            $storeCode = $this->storeUrls->getStoreCodeByHostAndPath($request->getUri()->getHost(), $resultPathInfo);
        }

        if (!$storeCode) {
            return $resultPathInfo;
        }

        $baseUrls = $this->storeUrls->getBaseUrls();
        $baseUrl = $baseUrls[$storeCode];

        if (strpos($baseUrl, '/')) {
            $baseUri = substr($baseUrl, strpos($baseUrl, '/'));
            if (strpos($resultPathInfo, $baseUri) === 0) {
                $resultPathInfo = substr($resultPathInfo, strlen($baseUri));
            }
        }

        return $resultPathInfo;
    }
}
