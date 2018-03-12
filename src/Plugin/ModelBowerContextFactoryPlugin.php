<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\StoreResolver\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\File\ContextFactory;
use Magento\Store\Model\StoreManagerInterface;
use ReachDigital\Polymer\Model\BowerContextFactory;

class ModelBowerContextFactoryPlugin
{
    /** @var \Magento\Framework\View\Asset\File\Context */
    private $fileContext;

    /** @var ContextFactory $contextFactory */
    private $contextFactory;

    /** @var UrlInterface $url */
    private $url;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /**
     * @param ContextFactory        $contextFactory
     * @param UrlInterface          $url
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ContextFactory $contextFactory,
        UrlInterface $url,
        StoreManagerInterface $storeManager
    ) {
        $this->contextFactory = $contextFactory;
        $this->url = $url;
        $this->storeManager = $storeManager;
    }

    /**
     * @param BowerContextFactory $subject
     * @param callable            $proceed
     * @param string              $baseDirType
     * @param string              $urlType
     * @param string              $dirPath
     *
     * @return \Magento\Framework\View\Asset\File\Context
     */
    public function aroundGetFileContext(
        BowerContextFactory $subject,
        callable $proceed,
        string $baseDirType,
        string $urlType,
        string $dirPath
    ): \Magento\Framework\View\Asset\File\Context {
        if ($this->fileContext === null) {
            $this->fileContext = $this->contextFactory->create([
                'baseUrl'       => $this->getProcessedUrl($urlType),
                'baseDirType'   => $baseDirType,
                'contextPath'   => $dirPath
            ]);
        }

        return $this->fileContext;
    }

    /**
     * Strip Store Code from url when added to Base url.
     *
     * @param string $urlType
     *
     * @return string
     */
    private function getProcessedUrl(string $urlType): string
    {
        $url = $this->url->getBaseUrl(['_type' => $urlType]);

        $pos = strrpos(rtrim($url, '/'), '/');
        if ($pos !== false) {
            $storeCode = trim(substr($url, $pos), '/');

            try {
                $this->storeManager->getStore($storeCode);
            } catch (NoSuchEntityException $e) {
                return $url;
            }

            $url = substr($url, 0, $pos+1);
        }

        return $url;
    }
}
