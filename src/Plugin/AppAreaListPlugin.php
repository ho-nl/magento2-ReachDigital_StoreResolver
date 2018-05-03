<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

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
     * @param Http                  $request
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(Http $request, StoreManagerInterface $storeManager)
    {
        $this->request = $request;
        $this->storeManager = $storeManager;
    }

    /**
     * @param AreaList $subject
     * @param string   $frontName
     *
     * @return array
     */
    public function beforeGetCodeByFrontName(AreaList $subject, $frontName): array
    {
        $pathParts = explode('/', trim($this->request->getPathInfo(), '/'));
        $storeCode = reset($pathParts);

        try {
            $this->storeManager->getStore($storeCode);
        } catch (NoSuchEntityException $e) {
            return [$frontName];
        }

        array_shift($pathParts);
        $this->request->setPathInfo(implode('/', $pathParts));

        return [reset($pathParts)];
    }
}
