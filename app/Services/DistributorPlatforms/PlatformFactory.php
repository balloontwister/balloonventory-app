<?php

namespace App\Services\DistributorPlatforms;

use App\Contracts\DistributorPlatformAdapter;
use App\Models\Distributor;

class PlatformFactory
{
    private const MAP = [
        'shopify' => ShopifyAdapter::class,
        'bigcommerce' => BigCommerceAdapter::class,
        'magento' => MagentoAdapter::class,
    ];

    public function make(Distributor $distributor): DistributorPlatformAdapter
    {
        $class = self::MAP[$distributor->platform_type]
            ?? throw new \InvalidArgumentException("Unknown platform type: {$distributor->platform_type}");

        return app($class);
    }
}
