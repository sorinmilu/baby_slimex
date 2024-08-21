<?php

require __DIR__ . '/vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/config.php';

use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

// Create Container using PHP-DI
$container = new Container();

// Set up logging
$log = new Logger('slim_app');
$log->pushHandler(new StreamHandler($config['log_file'], Logger::DEBUG));

// Add logger to container
$container->set(LoggerInterface::class, function() use ($log) {
    return $log;
});

// Set up Twig
$container->set('view', function() use ($config) {
    return Twig::create($config['templates_dir'], ['cache' => false]);
});

// Create App with Container
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add Twig-View Middleware
$app->add(TwigMiddleware::createFromContainer($app));

// Function to fetch and save a random background image from Picsum
function fetchBackgroundImage($size, $tmpDir, LoggerInterface $log) {
    $client = new Client(['allow_redirects' => true]);
    $imageUrl = "https://picsum.photos/{$size}";

    try {
        $log->info("Fetching image from URL: $imageUrl");

        // Send a GET request to the image URL
        $response = $client->request('GET', $imageUrl, ['stream' => true]);

        // Track the final URL after redirects
        $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
        $finalUrl = end($redirectHistory) ?: $imageUrl;

        $log->info("Final URL after redirection: $finalUrl");

        // Get the image content from the final URL
        $imageResponse = $client->request('GET', $finalUrl, ['stream' => true]);
        $imageContent = $imageResponse->getBody();

        // Generate a unique filename with correct naming convention
        $filename = 'bg_' . uniqid() . '.jpg';
        $filePath = $tmpDir . $filename;

        // Save the image content to the file
        file_put_contents($filePath, $imageContent);

        $log->info("Image saved to: $filePath");

        return $filename; // Return the filename to be used in the URL

    } catch (RequestException $e) {
        // Log any exceptions
        $log->error("Error fetching image: " . $e->getMessage());
        return null;
    }
}

// Function to get or fetch a background image
function getBackgroundImage($config, LoggerInterface $log) {
    $cachedImages = glob($config['tmp_dir'] . 'bg_*.jpg');
    $log->info("we have ". count($cachedImages) . " images in cache");
    
    // Use cache if there are more than 10 images
    if (count($cachedImages) > $config['cached_images'] -1) {
        $log->info("Selecting a random cached background image.");
        $randomImage = $cachedImages[array_rand($cachedImages)];
        return basename($randomImage);
    }

    // No sufficient cached images, fetch a new one
    return fetchBackgroundImage($config['background_image_size'], $config['tmp_dir'], $log);
}

// Home Route
$app->get('/', function ($request, $response, $args) use ($config) {
    return $this->get('view')->render($response, 'home.twig', [
        'appName' => $config['app_name'],
        'backgroundImage' => getBackgroundImage($config, $this->get(LoggerInterface::class))
    ]);
});

// Joke Route
$app->get('/joke', function ($request, $response, $args) use ($container, $config) {
    $log = $container->get(LoggerInterface::class);
    $log->info('Joke route accessed');
    
    $backgroundImageFilename = getBackgroundImage($config, $log);
    $backgroundImageUrl = $backgroundImageFilename ? '/tmp/' . $backgroundImageFilename : '';

    $client = new Client();
    $jokeApiResponse = $client->request('GET', 'https://official-joke-api.appspot.com/random_joke');
    $joke = json_decode($jokeApiResponse->getBody());

    $log->info('Joke retrieved: ' . $joke->setup);
    
    return $this->get('view')->render($response, 'joke.twig', [
        'joke' => $joke,
        'backgroundImage' => $backgroundImageUrl
    ]);
});

// Cocktail Route
$app->get('/cocktail', function ($request, $response, $args) use ($container, $config) {
    $log = $container->get(LoggerInterface::class);
    $log->info('Cocktail route accessed');
    
    $backgroundImageFilename = getBackgroundImage($config, $log);
    $backgroundImageUrl = $backgroundImageFilename ? '/tmp/' . $backgroundImageFilename : '';

    $client = new Client();
    $cocktailApiResponse = $client->request('GET', 'https://www.thecocktaildb.com/api/json/v1/1/random.php');
    $cocktail = json_decode($cocktailApiResponse->getBody())->drinks[0];
    
    $log->info('Cocktail retrieved: ' . $cocktail->strDrink);

    return $this->get('view')->render($response, 'cocktail.twig', [
        'cocktail' => $cocktail,
        'backgroundImage' => $backgroundImageUrl
    ]);
});

// Serve static files from the tmp directory
$app->get('/tmp/{filename:.+}', function ($request, $response, $args) use ($config) {
    $filename = $args['filename'];
    $filePath = $config['tmp_dir'] . $filename;
    
    if (file_exists($filePath)) {
        $response->getBody()->write(file_get_contents($filePath));
        return $response->withHeader('Content-Type', mime_content_type($filePath));
    } else {
        return $response->withStatus(404)->write('File not found');
    }
});

// Run the app
$app->run();
