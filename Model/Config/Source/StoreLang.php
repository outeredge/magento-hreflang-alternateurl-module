<?php

namespace OuterEdge\Hreflang\Model\Config\Source;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

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
     * Locale constructor.
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get list of Locale for all stores
     * @return array
     */
    public function getAllStoresLang()
    {
        $locale = [];
        $stores = $this->storeManager->getStores($withDefault = false);

        foreach($stores as $store) {
            $locale[] = $this->scopeConfig->getValue('general/locale/code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getStoreId());
        }
        return $locale;
    }
}
