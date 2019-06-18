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

class ModelStorePlugin
{
    /** @var UrlInterface $url */
    private $url;

    /** @var RequestInterface $request */
    private $request;

    /**
     * @param UrlInterface     $url
     * @param RequestInterface $request
     */
    public function __construct(UrlInterface $url, RequestInterface $request)
    {
        $this->url = $url;
        $this->request = $request;
    }

    /**
     * Request String also contains added url key present in store base url.
     * Strip out this key otherwise it's added twice.
     *
     * @param Store  $subject
     * @param string $currentUrl
     *
     * @return string
     */
    public function afterGetCurrentUrl(Store $subject, string $currentUrl): string
    {
        $requestString = $this->url->escape(ltrim($this->request->getRequestString(), '/'));

        if ($requestString) {
            $position = strpos($currentUrl, $requestString);
            if ($position !== false) {
                $currentUrl = substr_replace($currentUrl, '', $position, strlen($requestString));
            }
        }

        return $currentUrl;
    }
}
