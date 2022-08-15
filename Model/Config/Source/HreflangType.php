<?php

namespace OuterEdge\Hreflang\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class HreflangType implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'local',
                'label' => __('Use Magento store views')
            ],
            [
                'value' => 'local_websites',
                'label' => __('Use Magento websites')
            ],
            [
                'value' => 'remote',
                'label' => __('Remote - Separate website or Magento installation')
            ]
        ];
    }
}
