<?php
return [
    'db_host' => '127.0.0.1', 'db_port' => 3306,
    'db_name' => 'flawed_store', 'db_user' => 'root', 'db_pass' => '',
    // Set to /flawed when copied to htdocs/flawed. Empty means the domain root.
    'base_path' => '/flawed',
    // Required only for FIRST setup on public hosting. Choose a long random value.
    'setup_key' => '',
    // Keep false until a tested bKash merchant integration is installed.
    'bkash_enabled' => false,
];
