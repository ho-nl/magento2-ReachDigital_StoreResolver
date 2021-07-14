<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Magento\Store\Api\Data\StoreInterface;
use Magento\UrlRewrite\Model\StoreSwitcher\RewriteUrl;

class ModelStoreSwitcherRewriteUrl
{
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
        $requestUrl = $redirectUrl;

        // Remove store code in redirect url for correct rewrite search
        $redirectUrl = $this->stripBaseUrlEnd($targetStore->getBaseUrl(), $redirectUrl);
        $redirectUrl = $this->stripBaseUrlEnd($fromStore->getBaseUrl(), $redirectUrl);
        $toUrlWithoutEnd = $this->stripBaseUrlEnd($targetStore->getBaseUrl(), $targetStore->getBaseUrl());

        $targetUrl = $proceed($fromStore, $targetStore, $redirectUrl);

        return $redirectUrl === $targetUrl ? $requestUrl : $targetUrl;
    }

    /**
     * @param string $baseUrl
     * @param string $urlToStrip
     *
     * @return string
     */
    private function stripBaseUrlEnd(string $baseUrl, string $urlToStrip): string
    {
        $strippedBaseUrl = rtrim(str_replace(['www.', 'http://', 'https://'], '', $baseUrl), '/');
        if (substr_count($strippedBaseUrl, '/') > 0) {
            $urlEnd = substr($strippedBaseUrl, strpos($strippedBaseUrl, '/') + 1) . '/';
            $baseUrlStart = str_replace($urlEnd, '', $baseUrl);
            $urlToStrip = str_replace($baseUrl, $baseUrlStart, $urlToStrip);
        }

        return $urlToStrip;
    }
}
