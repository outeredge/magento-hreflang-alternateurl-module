<?php
namespace OuterEdge\Hreflang\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Asset\GroupedCollection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Registry;
use OuterEdge\Hreflang\Model\Config\Source\StoreLang;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use OuterEdge\Hreflang\Model\Config\Source\HreflangType;

class MetaTags implements ObserverInterface
{
    /**
     * @var \Magento\Catalog\Model\Layer\Category
     */
    protected $catalogLayer;

    /**
     * @var Config
     */
    protected $_pageConfig;

    /**
     * Asset service
     *
     * @var AssetRepository
     */
    protected $assetRepo;

      /**
     * @var GroupedCollection
     */
    protected $pageAssets;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var StoreLang
     */
    protected $storeLang;

    /**
     * @var LocaleResolver
     */
    protected $resolver;

    /**
     * @param Config $pageConfig
     * @param LayerResolver $layerResolver
     * @param AssetRepository $assetRepo
     * @param GroupedCollection $pageAssets
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param StoreLang $storeLang
     * @param LocaleResolver $resolver
     */
    public function __construct(
        Config $pageConfig,
        LayerResolver $layerResolver,
        AssetRepository $assetRepo,
        GroupedCollection $pageAssets,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Registry $registry,
        StoreLang $storeLang,
        LocaleResolver $resolver
    ) {
        $this->_pageConfig = $pageConfig;
        $this->catalogLayer = $layerResolver->get();
        $this->assetRepo = $assetRepo;
        $this->pageAssets = $pageAssets;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->registry = $registry;
        $this->storeLang = $storeLang;
        $this->resolver = $resolver;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        //Add link rel alternate to head
        $baseUrl        = $this->storeManager->getStore()->getBaseUrl();
        $localLang      = $this->getHrefLangLocal();
        $altLang        = $this->getHrefLang();
        $alternateBase  = $this->getHrefLangBaseurl();
        $mirrorUrlPaths = $this->getMirrorUrlPaths();
        $currentStoreLang = $this->resolver->getLocale();

        if ($this->getProduct()) {
            $this->addAlternateLinkRel($this->getProduct()->getProductUrl(), $localLang);

            $categoryUrl = '';
            $category    = $this->registry->registry('current_category');

            if ($category && $this->getUseCategoryPathForProduct()) {
                $categoryUrl = $category->getUrlPath() . '/';
            }

            $mirrorPath = $mirrorUrlPaths ? $categoryUrl . $this->getProduct()->getUrlPath() : null;
            $altUrl = $this->getProduct()->getAlternateUrl() ? $this->getProduct()->getAlternateUrl() : $mirrorPath;

            if ($categoryUrl == $mirrorPath && $altUrl == $mirrorPath) {
                $altUrl = $mirrorPath . $this->getProduct()->getUrlKey();
            }

            if ($altUrl && ($this->getHreflangType() != HreflangType::HREFLANG_REMOTE)) {

                foreach ($this->storeLang->getAllStoresLang($currentStoreLang, $this->getProduct()) as $lang => $alternativeUrl) {
                    $this->addAlternateLinkRel($alternativeUrl, $lang);
                }
            } else {
                $altUrl = $alternateBase.'/'.$altUrl;
                $this->addAlternateLinkRel($altUrl, $altLang);
            }

        } elseif($this->getCategory()) {
            $url = $this->getCategory()->getUrl();
            $this->addAlternateLinkRel($url, $localLang);

            $mirrorPath = $mirrorUrlPaths ? $mirrorUrlPaths : $this->getCategory()->getUrlPath();
            $altUrl     = $this->getCategory()->getAlternateUrl() ? $this->getCategory()->getAlternateUrl() : $mirrorPath;

            if ($altUrl) {
                foreach ($this->storeLang->getAllStoresLang($currentStoreLang, $this->getCategory()) as $lang => $alternativeUrl) {
                    $this->addAlternateLinkRel($alternativeUrl, $lang);
                }
            }

        } elseif(in_array($observer->getFullActionName(), [
                'cms_index_index',
                'cms_page_view',
                'blog_category_view',
                'blog_index_index',
                'blog_post_view'
            ])) {

            $currentUrl = $this->storeManager->getStore()->getUrl('*/*/*', ['_current' => false, '_use_rewrite' => true]);
            $urlPath    = str_replace($baseUrl, '', $currentUrl);
            $altUrl     = $alternateBase.'/'.$urlPath;

            $this->addAlternateLinkRel($currentUrl, $localLang);

            if ($this->getHreflangType() == HreflangType::HREFLANG_LOCAL_WEBSITES) {
                foreach ($this->storeLang->getAllStoresLang($currentStoreLang) as $lang => $alternativeUrl) {
                    $this->addAlternateLinkRel($alternativeUrl, $lang);
                }
            } else {
                $this->addAlternateLinkRel($altUrl, $altLang);
            }
        }
    }

    /**
    * Add link rel alternate to head
    */
    protected function addAlternateLinkRel($href, $storeLang)
    {
        $remoteAsset = $this->assetRepo->createRemoteAsset((string)$href, 'unknown');
        $this->pageAssets->add(
            "link/{$href}",
            $remoteAsset,
            ['attributes' => 'rel="alternate" hreflang="'.$storeLang.'"']
        );
    }

    private function getHrefLangLocal()
    {
        if ($this->getHreflangType() != HreflangType::HREFLANG_REMOTE) {
            return $this->resolver->getLocale();
        }

        return $this->scopeConfig->getValue(
            'web/hreflang/hreflang_local',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    private function getHrefLang()
    {
        return $this->scopeConfig->getValue(
            'web/hreflang/hreflang',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    private function getHrefLangBaseurl()
    {
        return $this->scopeConfig->getValue(
            'web/hreflang/hreflang_baseurl',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    private function getMirrorUrlPaths()
    {
        return $this->scopeConfig->getValue(
            'web/hreflang/alternate_mirror',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    private function getUseCategoryPathForProduct()
    {
        return $this->scopeConfig->getValue(
            'catalog/hreflang/product_use_categories',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    private function getHreflangType()
    {
        return $this->scopeConfig->getValue(
            'oe_hreflang/general/type',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    private function getProduct()
    {
        $product = $this->registry->registry('product');
        if (!$product) {
            return false;
        }
        return $product;
    }

    private function getCategory()
    {
        $category = $this->registry->registry('current_category');
        if (!$category) {
            return false;
        }
        return $category;
    }

}
