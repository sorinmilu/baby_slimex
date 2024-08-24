<?php

require __DIR__ . '/vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/config.php';

use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;


// Create Container using PHP-DI
$container = new Container();

// Create a custom log format with pipe separators
$logFormat = "%datetime% | %level_name% | %message%\n";

$dateFormat = "Y-m-d H:i:s";

// Create a LineFormatter with the custom format
$formatter = new LineFormatter($logFormat, $dateFormat, true, true);

// Create a logger instance
$log = new Logger('slim_app');


$logLevel = $config['log_level'] ?? Logger::DEBUG;

// Create a StreamHandler to write logs to a file
$streamhandler = new StreamHandler($config['log_file'], $logLevel);

// Assign the custom formatter to the StreamHandler
$streamhandler->setFormatter($formatter);

// Set up logging
$log->pushHandler($streamhandler);


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
$app->getContainer()->get('view')->getEnvironment()->addGlobal('asset_path', $config['asset_path']);
// Function to fetch and save a random background image from Picsum


function fetchBackgroundImage($size, $tmpDir, LoggerInterface $log) {
    $client = new Client(['allow_redirects' => true]);
    $imageUrl = "https://picsum.photos/{$size}";

    if (!is_dir($tmpDir)) {
        $log->debug("| | Creating target directory for images: ". $tmpDir);
        mkdir($tmpDir, 0755, true);
    }

    try {

        $log->debug("| | Fetching image from URL: $imageUrl");

        // Send a GET request to the image URL
        $response = $client->request('GET', $imageUrl, ['stream' => true]);

        // Track the final URL after redirects
        $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
        $finalUrl = end($redirectHistory) ?: $imageUrl;

        $log->debug("| | Final URL after redirection: $finalUrl");

        // Get the image content from the final URL
        $imageResponse = $client->request('GET', $finalUrl, ['stream' => true]);
        $imageContent = $imageResponse->getBody();

        // Generate a unique filename with correct naming convention
        $filename = 'bg_' . uniqid() . '.jpg';
        $filePath = $tmpDir . $filename;

        // Save the image content to the file
        file_put_contents($filePath, $imageContent);

        $log->debug("| | Image saved to: $filePath");

        return $filename; // Return the filename to be used in the URL

    } catch (RequestException $e) {
        $log->error("Error fetching image: " . $e->getMessage());
        return null;
    }
}

// Function to get or fetch a background image
function getBackgroundImage($config, LoggerInterface $log) {
    $cachedImages = glob($config['tmp_dir'] . 'bg_*.jpg');

    $log->debug("| | we have ". count($cachedImages) . " images in cache");
    
    // Use cache if there are more than 10 images
    if (count($cachedImages) > $config['cached_images'] -1) {
        $log->debug("| | Selecting a random cached background image.");
        $randomImage = $cachedImages[array_rand($cachedImages)];
        return basename($randomImage);
    }

    // No sufficient cached images, fetch a new one
    return fetchBackgroundImage($config['background_image_size'], $config['tmp_dir'], $log);
}

// Home Route
$app->get('/', function ($request, $response, $args) use ($container,$config) {
    $log = $container->get(LoggerInterface::class);
    $backgroundImageFilename = getBackgroundImage($config, $log);
    $backgroundImageUrl = $backgroundImageFilename ? '/tmp/' . $backgroundImageFilename : '';

    return $this->get('view')->render($response, 'home.twig', [
        'appName' => $config['app_name'],
        'backgroundImage' => $backgroundImageUrl
    ]);
});

// Joke Route
$app->get('/joke', function ($request, $response, $args) use ($container, $config) {
    $log = $container->get(LoggerInterface::class);
    $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    $log->info($ip . '|  Joke route accessed');

    $backgroundImageFilename = getBackgroundImage($config, $log);
    $backgroundImageUrl = $backgroundImageFilename ? '/tmp/' . $backgroundImageFilename : '';

    $client = new Client();
    try {
        $jokeApiResponse = $client->request('GET', 'https://official-joke-api.appspot.com/random_joke');
	$joke = json_decode($jokeApiResponse->getBody());

        $log->debug($ip . ' | Joke retrieved: ' . $joke->setup);
    } catch (RequestException $e) {
        $log->error("Error getting joke: " . $e->getMessage());
        return $this->get('view')->render($response, 'err.twig', []);
    }

    return $this->get('view')->render($response, 'joke.twig', [
        'joke' => $joke,
        'backgroundImage' => $backgroundImageUrl
    ]);

});

// Cocktail Route
$app->get('/cocktail', function ($request, $response, $args) use ($container, $config) {
    $log = $container->get(LoggerInterface::class);
    $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';

    $log->info($ip. ' |  Cocktail route accessed');

    $backgroundImageFilename = getBackgroundImage($config, $log);
    $backgroundImageUrl = $backgroundImageFilename ? '/tmp/' . $backgroundImageFilename : '';

    $client = new Client();

    try {
        $cocktailApiResponse = $client->request('GET', 'https://www.thecocktaildb.com/api/json/v1/1/random.php');
        $cocktail = json_decode($cocktailApiResponse->getBody())->drinks[0];
    } catch (RequestException $e) {
        $log->error($this->get('clientIp'). "| $this->get('clientIp'). "| "Error getting recipe: " . $e->getMessage());
        return $this->get('view')->render($response, 'err.twig', []);
    }
    

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
        $log->error('File not found: ' . $filePath);
        return $response->withStatus(404)->write('File not found');
    }
});

// Run the app
$app->run();
