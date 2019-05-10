<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Api\Data\StoreInterface;
use Magento\UrlRewrite\Model\StoreSwitcher\RewriteUrl;

class ModelStoreSwitcherRewriteUrl
{
    /** @var HttpRequest $request */
    private $request;

    /**
     * @param HttpRequest $request
     */
    public function __construct(HttpRequest $request)
    {
        $this->request = $request;
    }

    /**
     * @param RewriteUrl     $subject
     * @param callable       $proceed
     * @param StoreInterface $fromStore
     * @param StoreInterface $targetStore
     * @param string         $redirectUrl
     *
     * @return string
     */
    public function aroundSwitch(
        RewriteUrl $subject,
        callable $proceed,
        StoreInterface $fromStore,
        StoreInterface $targetStore,
        string $redirectUrl
    ): string {
        $fromStoreCode = $this->request->getParam('___from_store');
        $targetStoreCode = $this->request->getParam('___store');
        $redirectUrl = str_replace([$fromStoreCode.'/', $targetStoreCode.'/'], '', $redirectUrl);

        $targetUrl = $proceed($fromStore, $targetStore, $redirectUrl);

        return $redirectUrl === $targetUrl
            ? $targetStore->getBaseUrl()
            : $targetUrl;
    }
}
