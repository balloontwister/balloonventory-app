<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Distributors data connection
    |--------------------------------------------------------------------------
    |
    | The staging / clustering tables (distributor_products,
    | distributor_catalog_proposals) can be relocated to their own database or
    | server later without code changes. Leave DISTRIBUTORS_DB_CONNECTION unset
    | and they live in the primary database (the migrations and models resolve
    | `null` to the default connection). To move them, define a new connection
    | in config/database.php and point this env at it.
    |
    | NOTE: because these tables may live on a different connection than
    | `distributors` / `skus`, they intentionally carry NO database-level
    | foreign keys to those tables — referential integrity is enforced in app
    | code. They reference rows by indexed id columns only.
    |
    */

    'connection' => env('DISTRIBUTORS_DB_CONNECTION') ?: null,

];
