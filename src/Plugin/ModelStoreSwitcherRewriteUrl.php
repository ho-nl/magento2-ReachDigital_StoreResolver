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

        // Remove store code in redirect url for correct rewrite search
        $baseUrl = rtrim(str_replace(['www.', 'http://', 'https://'], '', $targetStore->getBaseUrl()), '/');
        if (substr_count($baseUrl, '/') + 1 > 1) {
            $redirectUrl = str_replace([$fromStoreCode.'/', $targetStoreCode.'/'], '', $redirectUrl);
        }

        $targetUrl = $proceed($fromStore, $targetStore, $redirectUrl);

        return $targetUrl.$targetStoreCode.'/' === $targetStore->getBaseUrl()
            ? $targetStore->getBaseUrl()
            : $targetUrl;
    }
}
