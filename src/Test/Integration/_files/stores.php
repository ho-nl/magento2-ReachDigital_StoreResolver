<?php
declare(strict_types=1);

use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/** @var \Magento\TestFramework\ObjectManager $objectManager */
$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

// Override the Uri to prevent errors while running this fixture.
$httpRequest = $objectManager->get(\Magento\Framework\App\Request\Http::class);
$httpRequest->setUri('');

$storeManager = $objectManager->get(StoreManagerInterface::class);
$storeRepository = $objectManager->get(StoreRepositoryInterface::class);


$storeModelTest1 = $objectManager->create('Magento\Store\Model\Store');
$storeModelTest1->setId(null);
$storeModelTest1->setName('Test store 1');
$storeModelTest1->setIsActive(true);
$storeModelTest1->setCode('test_1');
$storeModelTest1->setGroupId(0);

$groupModel = $objectManager->create(
    'Magento\Store\Model\Group'
)->load(
    $storeModelTest1->getGroupId()
);
$storeModelTest1->setWebsiteId($groupModel->getWebsiteId());
$storeModelTest1->save();


$storeModelTest2 = $objectManager->create('Magento\Store\Model\Store');
$storeModelTest2->setId(null);
$storeModelTest2->setName('Test store 2');
$storeModelTest2->setIsActive(true);
$storeModelTest2->setCode('test_2');
$storeModelTest2->setGroupId(0);

$groupModel = $objectManager->create(
    'Magento\Store\Model\Group'
)->load(
    $storeModelTest2->getGroupId()
);
$storeModelTest2->setWebsiteId($groupModel->getWebsiteId());
$storeModelTest2->save();
