<?php

namespace OuterEdge\Hreflang\Model\Config\Source;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\State as MagentoState;
use Magento\LayeredNavigation\Block\Navigation\State;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;

class StoreLang
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\UrlRewrite\Model\UrlFinderInterface
     */
    protected $urlFinder;

    /**
     * @var MagentoState
     */
    protected $magentoState;

    /**
     * @var State
     */
    protected $layeredNavState;

    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Amasty\ShopbyBase\Model\UrlBuilder\UrlModifier
     */
    protected $urlModifier = null;

    /**
     * Locale constructor.
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ProductRepositoryInterface $productRepository
     * @param UrlFinderInterface $urlFinder
     * @param MagentoState $magentoState
     * @param State $layeredNavState
     * @param ModuleManager $moduleManager
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CategoryRepositoryInterface $categoryRepository,
        ProductRepositoryInterface $productRepository,
        UrlFinderInterface $urlFinder,
        MagentoState $magentoState,
        State $layeredNavState,
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
        $this->urlFinder = $urlFinder;
        $this->magentoState = $magentoState;
        $this->layeredNavState = $layeredNavState;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;

        if ($this->moduleManager->isEnabled('Amasty_ShopbyBase') && $this->magentoState->getAreaCode() == Area::AREA_FRONTEND) {
            $this->urlModifier = $this->objectManager->create('Amasty\ShopbyBase\Model\UrlBuilder\UrlModifier');
        }
    }

    /**
     * Get list of Locale for all stores
     * @return array
     */
    public function getAllStoresLang($obj = false)
    {
        $locale = [];
        $stores = $this->storeManager->getStores($withDefault = false);
        $currentStoreId = $this->storeManager->getStore()->getId();
        $type = $obj instanceof Category ? 'category' : ($obj instanceof Product ? 'product' : null);

        foreach ($stores as $store) {
            if ($this->getOnlyHreflangSameDomain($store->getStoreId())) {
                $currentDomain = parse_url($this->storeManager->getStore()->getBaseUrl(), PHP_URL_HOST);
                $storeDomain = parse_url($store->getBaseUrl(), PHP_URL_HOST);
                if ($currentDomain != $storeDomain) {
                    continue;
                }
            }

            if (!$this->alternateUrlEnabledForStore($store)) {
                continue;
            }

            $langPrefix = $this->getCustomHreflangTagForStore($store->getStoreId());
            if (empty($langPrefix)) {
                $langPrefix = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $store->getStoreId());
            }

            $langUrl = substr($store->getCurrentUrl(), 0, strpos($store->getCurrentUrl(), "?"));

            try {
                if ($type == 'category') {
                    $storeCategory = $this->categoryRepository->get($obj->getId(), $store->getId());

                    if (!$storeCategory->getIsActive()) {
                        continue;
                    }

                    $rewrite = $this->urlFinder->findOneByData(
                        [
                            UrlRewrite::ENTITY_ID => $storeCategory->getId(),
                            UrlRewrite::ENTITY_TYPE => CategoryUrlRewriteGenerator::ENTITY_TYPE,
                            UrlRewrite::STORE_ID => $store->getId(),
                        ]
                    );

                    $stripSlash = false;

                    if ($rewrite) {
                        $urlPath    = $rewrite->getRequestPath();
                        $stripSlash = substr_compare($urlPath, '/', -1) !== 0; // Check if rewrite has a slash
                    } else {
                        $urlPath = $storeCategory->getUrlPath();
                    }

                    $selectedFilters = $this->layeredNavState->getActiveFilters();
                    $queryParams     = [];

                    foreach($selectedFilters as $filter){
                        $queryParams[$filter->getFilter()->getRequestVar()] = $filter->getValue();
                    }

                    $this->storeManager->setCurrentStore($store->getStoreId());

                    $langUrl = $store->getUrl($urlPath, ['_query' => $queryParams]);

                    if ($stripSlash) {
                        // Remove slash to avoid redirect
                        $langUrl = substr_replace($langUrl, '', strrpos($langUrl, '/'), 1);
                    }

                    if ($this->urlModifier) {
                        // Amasty Shopby
                        $langUrl = strtok($this->urlModifier->execute($langUrl, $storeCategory->getId()), '?');
                    }
                    $this->storeManager->setCurrentStore($currentStoreId);
                } elseif ($type == 'product') {
                    $storeProduct = $this->productRepository->getById($obj->getId(), false, $store->getId());

                    if (!in_array($store->getId(), $obj->getStoreIds())) {
                        continue;
                    }

                    $storeProduct->setDoNotUseCategoryId(true);
                    $langUrl = $storeProduct->getProductUrl();
                }
            } catch (NoSuchEntityException $e) {
                continue;
            }

            if (is_array($langPrefix)) {
                foreach($langPrefix as $lang) {
                    $locale[$lang] = $langUrl;
                }
            } else {
                $locale[$langPrefix] = $langUrl;
            }
        }

        return $locale;
    }

    public function alternateUrlEnabledForStore($store)
    {
        if (!$store->getIsActive()) {
            return false;
        }

        return $this->scopeConfig->getValue(
            'oe_hreflang/general/alternate_url_for_store',
            ScopeInterface::SCOPE_STORE,
            $store->getStoreId()
        );
    }

    private function getCustomHreflangTagForStore($storeId)
    {
        $result = (string) $this->scopeConfig->getValue(
            'oe_hreflang/general/custom_hreflang_tag',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $arrayLangs = array_filter(array_map('trim', explode(',', $result)));
        if (empty($arrayLangs)) {
            return false;
        }

        return $arrayLangs;
    }

    private function getOnlyHreflangSameDomain($storeId)
    {
        return $this->scopeConfig->getValue(
            'oe_hreflang/general/only_hreflang_same_domain',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
