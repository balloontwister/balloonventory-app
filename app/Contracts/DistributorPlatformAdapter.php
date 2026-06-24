<?php

namespace App\Contracts;

use App\Models\Distributor;

interface DistributorPlatformAdapter
{
    /**
     * Fetch product listings from the distributor.
     *
     * @return array<int, array{
     *     identifier: string,
     *     name: string,
     *     url: string,
     *     barcode: ?string,
     *     price: ?float,
     *     currency: ?string,
     *     in_stock: ?bool,
     * }>
     */
    public function fetchProducts(Distributor $distributor): array;
}
