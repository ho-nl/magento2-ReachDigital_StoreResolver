<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\StoreResolver\Plugin;


use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManager;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class FixSeoUrlParsing
{
    /**
     * @var UrlFinderInterface
     */
    private $urlFinder;

    /**
     * @var StoreManager
     */
    private $storeManager;

    public function __construct(
        UrlFinderInterface $urlFinder,
        StoreManager $storeManager
    )
    {
        $this->urlFinder = $urlFinder;
        $this->storeManager = $storeManager;
    }

    private $skipRequestIdentifiers = [
        'catalog/category/',
        'catalog/product/',
        'cms/page/',
        'amasty_xsearch/',
        'customer/',
        'checkout/',
        'catalogsearch'
    ];

    /**
     * Obtain modified URL from \Ho\StoreResolver\Plugin\AppAreaListPlugin for correct handling of SEO URLs
     *
     * @param                  $subject
     * @param \Closure         $proceed
     * @param RequestInterface $request
     * @param                  $allowEmptyModuleName
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function aroundIsAllowedRequest($subject, \Closure $proceed, RequestInterface $request, $allowEmptyModuleName)
    {
        if (!$allowEmptyModuleName && !$request->getModuleName()) {
            return false;
        }

        if (AppAreaListPlugin::getModifiedOriginalPathInfo()) {
            $identifier = ltrim(AppAreaListPlugin::getModifiedOriginalPathInfo(), '/');
        } else {
            $identifier = ltrim($request->getOriginalPathInfo(), '/');
        }
        if (!empty($identifier)) {
            foreach ($this->skipRequestIdentifiers as $skipRequestIdentifier) {
                if (strpos($identifier, $skipRequestIdentifier) === 0) {
                    return false;
                }
            }

            $rewrite = $this->urlFinder->findOneByData([
                UrlRewrite::REQUEST_PATH => $identifier,
                UrlRewrite::STORE_ID => $this->storeManager->getStore()->getId(),
            ]);
            if ($rewrite !== null) {
                return false;
            }

            return true;
        }

        return false;

    }
}
