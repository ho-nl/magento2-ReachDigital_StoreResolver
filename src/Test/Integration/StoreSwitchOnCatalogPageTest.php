<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Test\Integration;

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ScopeInterface;
use Magento\Store\Api\StoreRepositoryInterface;

class StoreSwitchOnCatalogPageTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Magento\TestFramework\Request $request */
    private $request;

    /** @var \Magento\Store\Model\StoreFactory $storeFactory */
    private $storeFactory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        $this->request = $objectManager->get(\Magento\TestFramework\Request::class);
        $this->storeFactory = $objectManager->get(\Magento\Store\Model\StoreFactory::class);
    }

    /**
     * Make sure path exists when changing store on PDP/PLP.
     * This is later used to set correct redirect to translated path.
     *
     * @see \Magento\UrlRewrite\Model\StoreSwitcher\RewriteUrl::switch()
     *
     * @magentoAppArea frontend
     * @magentoAppIsolation enabled
     *
     * @magentoConfigFixture test_store web/seo/use_rewrites 1
     *
     * @magentoDataFixture Magento/Store/_files/store.php
     */
    public function testCurentUrlContainsPath(): void
    {
        $reflection = new \ReflectionClass($this->request);
        $reflectionProperty = $reflection->getProperty('requestString');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->request, '/catalog/product/view/1');

        $store = $this->storeFactory->create(['request' => $this->request])->load('test', 'code');

        self::assertEquals('http://localhost/catalog/product/view/1?___store=test', $store->getCurrentUrl(false));
    }
}
