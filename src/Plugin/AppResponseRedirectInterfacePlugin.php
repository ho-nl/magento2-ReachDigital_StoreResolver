<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Ho\StoreResolver\Model\StoreUrls;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class AppResponseRedirectInterfacePlugin
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

    /**
     * @param RedirectInterface $redirect
     * @param string            $url
     *
     * @throws NoSuchEntityException
     *
     * @return string
     */
    public function afterGetRedirectUrl(RedirectInterface $redirect, string $url): string
    {
        try {
            $storeCode = $this->request->getParam('___from_store');

            if ($storeCode === null) {
                // No need to redirect to other store
                return $url;
            }

            $this->storeManager->getStore($storeCode);
        } catch (NoSuchEntityException $e) {
            return $url;
        }

        $baseUrl = rtrim(str_replace(['www.', 'http://', 'https://'], '', $url), '/');
        $uri = trim(str_replace($this->request->getHttpHost(), '', $baseUrl), '/');

        $storeCode = $this->storeUrls->getStoreCodeByHostAndPath($this->request->getHttpHost(), $uri);

        if ($storeCode) {
            $targetBaseUrl = $this->storeManager->getStore()->getBaseUrl();

            return $targetBaseUrl;
        }

        return $url;
    }
}
