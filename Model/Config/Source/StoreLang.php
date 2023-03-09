<?php

namespace OuterEdge\Hreflang\Model\Config\Source;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

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
     * Locale constructor.
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CategoryRepositoryInterface $categoryRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * Get list of Locale for all stores
     * @return array
     */
    public function getAllStoresLang($obj = false)
    {
        $locale = [];
        $stores = $this->storeManager->getStores($withDefault = false);
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

            $langUlr = substr($store->getCurrentUrl(), 0, strpos($store->getCurrentUrl(), "?"));

            try {
                if ($type == 'category') {
                    $storeCategory = $this->categoryRepository->get($obj->getId(), $store->getId());

                    if (!$storeCategory->getIsActive()) {
                        continue;
                    }

                    $langUlr = $store->getUrl($storeCategory->getUrlPath());
                } elseif ($type == 'product') {
                    $storeProduct = $this->productRepository->getById($obj->getId(), false, $store->getId());

                    if (!in_array($store->getId(), $obj->getStoreIds())) {
                        continue;
                    }

                    $langUlr = $storeProduct->getProductUrl();
                }
            } catch (NoSuchEntityException $e) {
                continue;
            }

            if (is_array($langPrefix)) {
                foreach($langPrefix as $lang) {
                    $locale[$lang] = $langUlr;
                }
            } else {
                $locale[$langPrefix] = $langUlr;
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
