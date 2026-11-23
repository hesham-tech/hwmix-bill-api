<?php
return [
    'geoapify_key'=>env('GEOAPIFY_API_KEY'),
    'geoapify_map_key'=>env('GEOAPIFY_MAP_KEY'), // Public browser key restricted to platform domains.
    'daily_search_limit'=>(int)env('MAP_SEARCH_DAILY_LIMIT',500),
];
