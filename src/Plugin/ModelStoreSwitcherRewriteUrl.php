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
    ): string
    {
        // Remove store code in redirect url for correct rewrite search
        $redirectUrl = $this->stripBaseUrlEnd($targetStore->getBaseUrl(), $redirectUrl);
        $redirectUrl = $this->stripBaseUrlEnd($fromStore->getBaseUrl(), $redirectUrl);

        $targetUrl = $proceed($fromStore, $targetStore, $redirectUrl);
        return $targetUrl === $this->stripBaseUrlEnd($targetStore->getBaseUrl(), $targetStore->getBaseUrl())
            ? $targetStore->getBaseUrl()
            : $targetUrl;
    }

    private function stripBaseUrlEnd($baseUrl, $urlToStrip)
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
