<?php

declare(strict_types=1);

namespace Ho\StoreResolver\Test\Integration;

class StoreResolverTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Magento\Config\Model\ResourceModel\Config $config */
    private $config;

    /** @var \Magento\Framework\App\Cache\Manager $cacheManager */
    private $cacheManager;

    /** @var \Magento\Framework\App\Request\Http $request */
    private $request;

    /** @var \Magento\Store\Api\StoreResolverInterface $storeResolver */
    private $storeResolver;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->config = $objectManager->get(\Magento\Config\Model\ResourceModel\Config::class);
        $this->cacheManager = $objectManager->get(\Magento\Framework\App\Cache\Manager::class);
        $this->request = $objectManager->get(\Magento\Framework\App\Request\Http::class);
        $this->storeResolver = $objectManager->get(\Magento\Store\Api\StoreResolverInterface::class);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     *
     * @magentoDataFixture Magento/Store/_files/core_fixturestore.php
     *
     * @magentoConfigFixture default/web/seo/use_rewrites 1
     */
    public function testUriContainsStoreCode()
    {
        $this->config
            ->saveConfig('web/unsecure/base_url', 'http://localhost:81/', 'stores', 1)
            ->saveConfig('web/unsecure/base_url', 'http://localhost:81/es/', 'stores', 2);

        $this->cacheManager->clean($this->cacheManager->getAvailableTypes());
        $this->request->setRequestUri('/estimatetimeshipping/estimation/quoteDate');

        self::assertSame(1, (int) $this->storeResolver->getCurrentStoreId());
    }
}
