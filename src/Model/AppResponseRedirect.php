<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Model;

use Magento\Framework\App\ObjectManager;

class AppResponseRedirect extends \Magento\Store\App\Response\Redirect
{
    /** @var StoreResolver $storeResolver */
    private $storeResolver;

    /**
     * @param \Magento\Framework\App\RequestInterface            $request
     * @param \Magento\Store\Model\StoreManagerInterface         $storeManager
     * @param \Magento\Framework\Encryption\UrlCoder             $urlCoder
     * @param \Magento\Framework\Session\SessionManagerInterface $session
     * @param \Magento\Framework\Session\SidResolverInterface    $sidResolver
     * @param \Magento\Framework\UrlInterface                    $urlBuilder
     * @param StoreResolver                                      $storeResolver
     * @param \Laminas\Uri\Uri|null                                 $uri
     * @param bool                                               $canUseSessionIdInParam
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Encryption\UrlCoder $urlCoder,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Magento\Framework\Session\SidResolverInterface $sidResolver,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Ho\StoreResolver\Model\StoreResolver $storeResolver,
        \Laminas\Uri\Uri $uri = null,
        $canUseSessionIdInParam = true
    )
    {
        parent::__construct(
            $request,
            $storeManager,
            $urlCoder,
            $session,
            $sidResolver,
            $urlBuilder,
            $uri,
            $canUseSessionIdInParam
        );
        $this->storeResolver = $storeResolver;
    }

    /**
     * @param string $url
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _isUrlInternal($url) // phpcs:ignore
    {
        // Determine if URL is within current store
        if ($url !== null && strpos($url, 'http') !== false) {
            $urlStoreId = $this->storeResolver->getStoreForUrl($url);
            if ($urlStoreId !== false) {
                $curStoreId = (int) $this->_storeManager->getStore()->getId();

                return (int) $urlStoreId === $curStoreId;
            }
        }

        return false;
    }
}
