<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManager;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class FixSeoUrlParsing
{
    private const SKIP_REQUEST_IDENTIFIERS = [
        'catalog/category/',
        'catalog/product/',
        'cms/page/',
        'amasty_xsearch/',
        'customer/',
        'checkout/',
        'catalogsearch',
        'channable/feed'
    ];

    /** @var UrlFinderInterface $urlFinder */
    private $urlFinder;

    /** @var StoreManager $storeManager */
    private $storeManager;

    /**
     * @param UrlFinderInterface $urlFinder
     * @param StoreManager       $storeManager
     */
    public function __construct(UrlFinderInterface $urlFinder, StoreManager $storeManager)
    {
        $this->urlFinder = $urlFinder;
        $this->storeManager = $storeManager;
    }

    /**
     * Obtain modified URL from \Ho\StoreResolver\Plugin\AppAreaListPlugin for correct handling of SEO URLs
     *
     * @param                  $subject
     * @param \Closure         $proceed
     * @param RequestInterface $request
     * @param bool             $allowEmptyModuleName
     *
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function aroundIsAllowedRequest(
        $subject,
        \Closure $proceed,
        RequestInterface $request,
        $allowEmptyModuleName = false
    ) {
        if (!$allowEmptyModuleName && !$request->getModuleName()) {
            return false;
        }

        if (AppAreaListPlugin::getModifiedOriginalPathInfo()) {
            $identifier = ltrim(AppAreaListPlugin::getModifiedOriginalPathInfo(), '/');
        } else {
            $identifier = ltrim($request->getOriginalPathInfo(), '/');
        }
        if (!empty($identifier)) {
            foreach (self::SKIP_REQUEST_IDENTIFIERS as $skipRequestIdentifier) {
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
