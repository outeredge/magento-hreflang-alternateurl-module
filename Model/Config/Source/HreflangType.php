<?php

namespace OuterEdge\Hreflang\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class HreflangType implements ArrayInterface
{
    public CONST HREFLANG_LOCAL = 'local';

    public CONST HREFLANG_LOCAL_WEBSITES = 'local_websites';

    public CONST HREFLANG_REMOTE = 'remote';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::HREFLANG_LOCAL,
                'label' => __('Use Magento store views')
            ],
            [
                'value' => self::HREFLANG_LOCAL_WEBSITES,
                'label' => __('Use Magento websites')
            ],
            [
                'value' => self::HREFLANG_REMOTE,
                'label' => __('Remote - Separate website or Magento installation')
            ]
        ];
    }
}
