<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

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
     * @param Http                  $request
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Http $request,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->storeManager = $storeManager;
    }

    /**
     * @param RedirectInterface $redirect
     * @param string            $url
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

        return $url;
    }
}
