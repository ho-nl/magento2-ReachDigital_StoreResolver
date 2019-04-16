<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Ho\StoreResolver\Model\StoreUrls;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class AppAreaListPlugin
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
     * @param AreaList $subject
     * @param string   $frontName
     *
     * @return array
     */
    public function beforeGetCodeByFrontName(AreaList $subject, $frontName): array
    {
        $storeCode = $this->storeUrls->getStoreCodeByRequest($this->request);

        try {
            $this->storeManager->getStore($storeCode);
        } catch (NoSuchEntityException $e) {
            return [$frontName];
        }

        // Push custom path out array
        $pathParts = explode('/', trim($this->request->getPathInfo(), '/'));
        array_shift($pathParts);

        $this->request->setPathInfo(implode('/', $pathParts) ?: '/');

        return [reset($pathParts) ?: ''];
    }
}
