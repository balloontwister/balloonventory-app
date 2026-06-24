<?php

namespace App\Contracts;

use App\Models\Distributor;
use App\Services\DistributorPlatforms\FetchReport;

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

    /**
     * Diagnostics for the most recent fetchProducts() call — whether it was
     * blocked / rate limited / truncated, used a fallback, etc.
     */
    public function lastFetchReport(): FetchReport;
}
