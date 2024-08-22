<?php

return [
    'log_file' => __DIR__ . '/logs/baby_slimex.log',
    'app_name' => 'Baby Slimex',
    'assets_dir' => __DIR__ . 'assets/',
    'templates_dir' => __DIR__ . '/templates/',
    'background_image_size' => '1920/1080', // Set the size of the background image for api call, w / h
    'tmp_dir' => __DIR__ . '/tmp/',        // Directory for temporary files (background images)
    'cached_images' => 10,
    'log_level' => Monolog\Logger::DEBUG, // Example: Monolog\Logger::DEBUG, Monolog\Logger::INFO, Monolog\Logger::WARNING, etc.
];
