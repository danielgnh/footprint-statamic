<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    |
    | Collections whose entries get the Interactions tab, e.g. ['pages',
    | 'articles']. Null gives it to every collection whose entries have a URL.
    |
    */

    'collections' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Seconds each part of the tab is cached for one entry, range and device.
    |
    */

    'cache' => 600,

    /*
    |--------------------------------------------------------------------------
    | Custom events for agents
    |--------------------------------------------------------------------------
    |
    | Names sent with footprint.track() that AI agents may see through the
    | footprint-statamic-mcp tools. Any other name stays hidden from them,
    | since anyone can post a batch with any name.
    |
    */

    'events' => [],

];
