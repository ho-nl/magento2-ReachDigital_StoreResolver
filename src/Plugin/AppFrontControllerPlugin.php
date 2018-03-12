<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Magento\Framework\App\FrontController;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class AppFrontControllerPlugin
{
    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * @param FrontController  $subject
     * @param RequestInterface $request
     *
     * @return array
     */
    public function beforeDispatch(FrontController $subject, RequestInterface $request): array
    {
        $pathParts = explode('/', ltrim($request->getPathInfo(), '/'), 2);
        $storeCode = $pathParts[0];

        try {
            $this->storeManager->getStore($storeCode);
        } catch (NoSuchEntityException $e) {
            return [$request];
        }

        $pathInfo = '/'.($pathParts[1] ?? '');
        $request->setPathInfo($pathInfo);

        return [$request];
    }
}
