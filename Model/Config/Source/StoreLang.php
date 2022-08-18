<?php

namespace OuterEdge\Hreflang\Model\Config\Source;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

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
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * Locale constructor.
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $categoryCollectionFactory
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Get list of Locale for all stores
     * @return array
     */
    public function getAllStoresLang($currentStoreLang, $obj = false)
    {
        $locale = [];
        $stores = $this->storeManager->getStores($withDefault = false);
        $type = $obj instanceof Category ? 'category' : ($obj instanceof Product ? 'product' : null);

        foreach($stores as $store) {
            $langPrefix = $this->scopeConfig->getValue('general/locale/code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getStoreId());
            $langUlr = substr($store->getCurrentUrl(), 0, strpos($store->getCurrentUrl(), "?"));

            if ($langPrefix == $currentStoreLang) {
                continue;
            }

            if ($type == 'category') {
                $storeCategories = $this->categoryCollectionFactory->create();
                $storeCategories->setStore($store)
                        ->addAttributeToFilter('entity_id', $obj->getId())
                        ->addAttributeToFilter('is_active', 1);

                if (empty($storeCategories->getData())) {
                    continue;
                }
            } elseif ($type == 'product') {
                if (!in_array($store->getId(), $obj->getStoreIds())) {
                    continue;
                }
            }

            $locale[$langPrefix] = $langUlr;
        }

        return $locale;
    }
}
