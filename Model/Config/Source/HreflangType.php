<?php

namespace OuterEdge\Hreflang\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class HreflangType implements ArrayInterface
{
    public const HREFLANG_LOCAL = 'local';

    public const HREFLANG_REMOTE = 'remote';

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
                'value' => self::HREFLANG_REMOTE,
                'label' => __('Remote - Separate website or Magento installation')
            ]
        ];
    }
}
