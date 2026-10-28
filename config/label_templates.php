<?php

return [
    'enabled' => env('LABEL_TEMPLATES_ENABLED', true),
    'company_ids' => array_values(array_filter(array_map('intval', explode(',', env('LABEL_TEMPLATES_COMPANIES', ''))))),
];
