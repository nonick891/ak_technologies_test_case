<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slot Availability Cache
    |--------------------------------------------------------------------------
    |
    | Configuration for caching the slot availability list and protecting
    | against cache stampedes using a lock.
    |
    */

    'cache_key' => 'slots.availability',

    'ttl_seconds' => 25,

    'lock_key' => 'slots.availability.lock',

    'lock_seconds' => 5,

];
