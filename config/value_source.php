<?php

return [
    'enabled' => (bool) env('VALUE_SOURCE_ENABLED', false),
    'csv_url' => env('VALUE_SOURCE_CSV_URL', 'https://docs.google.com/spreadsheets/d/1GSCY4NA_h5il8nrZIapGcC4Y7IcqugYWNFAGNsJY0c4/gviz/tq'),
    'gid' => env('VALUE_SOURCE_GID', '1079603703'),
    'category' => env('VALUE_SOURCE_CATEGORY', 'skin'),
    'headers' => [
        'name' => env('VALUE_SOURCE_NAME_HEADER', 'Name 🎣SKINS'),
        'value' => env('VALUE_SOURCE_VALUE_HEADER', 'Value'),
        'offer_range' => env('VALUE_SOURCE_OFFER_RANGE_HEADER', 'Offer Range'),
    ],
    'connect_timeout_seconds' => (int) env('VALUE_SOURCE_CONNECT_TIMEOUT', 5),
    'timeout_seconds' => (int) env('VALUE_SOURCE_TIMEOUT', 15),
    'anomaly_threshold_percent' => (float) env('VALUE_SOURCE_ANOMALY_THRESHOLD', 25),
    'schedule' => env('VALUE_SOURCE_SCHEDULE', '02:00'),
    'timezone' => env('VALUE_SOURCE_TIMEZONE', 'Asia/Jakarta'),
];
