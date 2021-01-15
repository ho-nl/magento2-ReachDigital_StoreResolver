<?php
declare(strict_types=1);

namespace Ho\StoreResolver\Test\Integration;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\StoreManagementInterface;
use Magento\Store\Controller\Store\Redirect;
use Magento\Store\Controller\Store\SwitchAction;
use Magento\Store\Model\StoreCookieManager;
use Magento\Store\Model\StoreManagement;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoreResolver;
use Magento\TestFramework\Store\StoreManager;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\Store\Model\Store;
use Magento\Framework\App\Http\Context as HttpContext;
use Zend\Stdlib\Parameters;

class StoreSwitchingTest extends TestCase
{
    public static function createStores(): void
    {
        include __DIR__ . '/_files/stores.php';
    }

    /**
     * @test
     *
     * @magentoAppIsolation enabled
     * @magentoDataFixture createStores
     */
    public function shouldSwitchSightfulNlNlToNlBe()
    {
        $this->performTestSwitch(
            'https://www.sightful.nl/',
            'https://www.sightful.be/nl/',
            'https://www.sightful.be/nl/checkout/cart/index/');
    }
    /**
     * @test
     *
     * @magentoAppIsolation enabled
     * @magentoDataFixture createStores
     */
    public function shouldSwitchSightfulNlBeToNlNl()
    {
        $this->performTestSwitch(
            'https://www.sightful.be/nl/',
            'https://www.sightful.nl/',
            'https://www.sightful.nl/checkout/cart/index/');
    }
    /**
     * @test
     *
     * @magentoAppIsolation enabled
     * @magentoDataFixture createStores
     */
    public function shouldSwitchSightfulEnNlToNlNl()
    {
        $this->performTestSwitch(
            'https://www.sightful.nl/en/',
            'https://www.sightful.nl/',
            'https://www.sightful.nl/checkout/cart/index/');
    }
    /**
     * @test
     *
     * @magentoAppIsolation enabled
     * @magentoDataFixture createStores
     */
    public function shouldSwitchSightfulNlNlToEnNl()
    {
        $this->performTestSwitch(
            'https://www.sightful.nl/',
            'https://www.sightful.nl/en/',
            'https://www.sightful.nl/en/checkout/cart/index/');
    }
    /**
     * @test
     *
     * @magentoAppIsolation enabled
     * @magentoDataFixture createStores
     */
    public function shouldSwitchSightfulFrBeToNlBe()
    {
        $this->performTestSwitch(
            'https://www.sightful.be/fr/',
            'https://www.sightful.be/nl/',
            'https://www.sightful.be/nl/checkout/cart/index/');
    }
    /**
     * @test
     *
     * @magentoAppIsolation enabled
     * @magentoDataFixture createStores
     */
    public function shouldSwitchSightfulNlBeToFrBe()
    {
        $this->performTestSwitch(
            'https://www.sightful.be/nl/',
            'https://www.sightful.be/fr/',
            'https://www.sightful.be/fr/checkout/cart/index/');
    }

    private function performTestSwitch($fromStoreBaseUrl, $toStoreBaseUrl, $toUrl)
    {
        // Override the Uri to prevent errors while running this fixture.
        $objectManager = Bootstrap::getObjectManager();
        $httpRequest = $objectManager->get(\Magento\Framework\App\Request\Http::class);
        $httpRequest->setUri('');

        $testStore1 = $this->getStore('test_1');
        $testStore2 = $this->getStore('test_2');
        $config = $this->getConfig();
        $config->saveConfig('web/unsecure/base_url', $fromStoreBaseUrl, 'stores', $testStore1->getId());
        $config->saveConfig('web/unsecure/base_url', $toStoreBaseUrl, 'stores', $testStore2->getId());
        $config->saveConfig('web/secure/base_url', $fromStoreBaseUrl, 'stores', $testStore1->getId());
        $config->saveConfig('web/secure/base_url', $toStoreBaseUrl, 'stores', $testStore2->getId());

        // Use seo rewrites. This prevents index.php from appearing in the base url and is usually active in all our stores.
        $config->saveConfig('web/seo/use_rewrites', 1, 'default', 0);

        // Clean the cache so the base urls will be found.
        $this->getCacheManager()->clean($this->getCacheManager()->getAvailableTypes());

        $testStore1->resetConfig();
        $testStore2->resetConfig();
        $storeManager = $this->getStoreManager();
        $storeManager->setCurrentStore($testStore1->getStoreId());
        $this->getHttpContext()->setValue(Store::ENTITY, 'YOUR_STORE_CODE', 'DEFAULT_STORE_CODE');
        $this->getStoreCookieManager()->setStoreCookie($testStore1);

        $request = $this->getRequest();
        $request->setParam('___from_store', 'test_1');
        $request->setParam(StoreManagerInterface::PARAM_NAME, 'test_2');
        $request->setParam(ActionInterface::PARAM_NAME_URL_ENCODED, \base64_encode($toUrl));

        $redirect = $this->getRedirect();
        $redirect->execute();
        /** @var Http $redirectResponse */
        $redirectResponse = $redirect->getResponse();

        // Reset the current store to null. This will prevent caching of the old store.
        $storeManager->setCurrentStore(null);

        $switchAction = $this->getSwitchAction();
        $switchAction->execute();

        $redirectUrlSwitch = $redirectResponse->getHeaders()->get('Location');
        $this->assertEquals($redirectUrlSwitch->getFieldValue(), $toUrl);
        $this->assertEquals($storeManager->getStore()->getCode(), 'test_2');
    }

    private function getStore($storeCode) :Store
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var StoreManager $storeManagent */
        $storeManager = $objectManager->get(StoreManager::class);
        $stores = $storeManager->getStores();
        /** @var Store $store */
        foreach ($stores as $store) {
            if ($store->getCode() === $storeCode) {
                return $store;
            }
        }
        return null;
    }

    private function getRedirect() : Redirect
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Redirect $storeManagent */
        return $objectManager->get(Redirect::class);
    }
    private function getRequest() : RequestInterface
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        return $objectManager->get(RequestInterface::class);
    }
    private function getSwitchAction() : SwitchAction
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        return $objectManager->get(SwitchAction::class);
    }
    private function getConfig() : Config
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        return $objectManager->get(Config::class);
    }
    private function getStoreManager() : StoreManager
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        return $objectManager->get(StoreManager::class);
    }

    private function getHttpContext() : HttpContext
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        return $objectManager->get(HttpContext::class);
    }

    private function getStoreCookieManager() : StoreCookieManager
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        return $objectManager->get(StoreCookieManager::class);
    }
    private function getCacheManager() : Manager
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        return $objectManager->get(Manager::class);
    }
}
