<?php

return [
    'log_file' => __DIR__ . '/logs/baby_slimex.log',
    'app_name' => 'Baby Slimex',
    'asset_path' => '/assets',
    'templates_dir' => __DIR__ . '/templates/',
    'img_dir' => __DIR__ . '/images',      //No Trailing slash
    'img_path' => '/images',                //No trailing slash
    'background_image_size' => '1920/1080', // Set the size of the background image for api call, w / h
    'tmp_dir' => __DIR__ . '/tmp/',        // Directory for temporary files (background images)
    'cached_images' => 100,
    'name_file' => 'name.txt',
    'log_level' => Monolog\Logger::DEBUG, // Example: Monolog\Logger::DEBUG, Monolog\Logger::INFO, Monolog\Logger::WARNING, etc.
    'usemongo' => true,
    'usevault' => false,
    'envvault' => true,
    'cachevault' => true,
];
