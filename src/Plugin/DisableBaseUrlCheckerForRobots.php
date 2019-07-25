<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;

class DisableBaseUrlCheckerForRobots
{
    public function aroundExecute(
        \Magento\Store\Model\BaseUrlChecker $subject,
        \Closure $proceed,
        $uri,
        \Magento\Framework\App\Request\Http $request
    ): bool
    {
        if ($request->getRequestUri() === '/robots.txt') {
            return true;
        }
        return $proceed($uri, $request);
    }
}
