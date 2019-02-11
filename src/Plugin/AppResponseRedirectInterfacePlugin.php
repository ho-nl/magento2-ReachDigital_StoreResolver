<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class AppResponseRedirectInterfacePlugin
{
    /** @var RequestInterface $request */
    private $request;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /**
     * @param RequestInterface      $request
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(RequestInterface $request, StoreManagerInterface $storeManager)
    {
        $this->request = $request;
        $this->storeManager = $storeManager;
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
            $this->storeManager->getStore($storeCode);
        } catch (NoSuchEntityException $e) {
            return $url;
        }

        $targetBaseUrl = $this->storeManager->getStore()->getBaseUrl();
        $pathParts = explode('/', trim(str_replace($targetBaseUrl, '', $url), '/'));

        if ($pathParts[0] === $storeCode) {
            return $targetBaseUrl.$pathParts[1];
        }

        return $url;
    }
}
