<?php

require __DIR__ . '/vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/config.php';
require __DIR__ . '/Models/JokeModel.php';
require __DIR__ . '/Models/CocktailModel.php';
require __DIR__ . '/Helpers/BSHelper.php';



use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use DI\Container;
use DI\ContainerBuilder;

use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use MongoDB\Client as MongoClient;
#use Dotenv;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    Client::class => function () use ($config) {
	$mongoUri = BSHelper::getMongoDbUri($config);
        return new MongoClient($mongoUri);
    },
    JokeModel::class => function ($container) use ($config) {
        $mongoClient = $container->get(Client::class);
        $databaseName = BSHelper::getSecretFromKeyVault($config, 'cosmodb');
        return new JokeModel($mongoClient, $databaseName);
    },
    CocktailModel::class => function ($container) use ($config) {
        $mongoClient = $container->get(Client::class);
        $databaseName = $config['mongo_database'] ?? 'baby_slimex';
        return new CocktailModel($mongoClient, $databaseName);
    },
]);

$container = $containerBuilder->build();


// Create a custom log format with pipe separators
$logFormat = "%datetime% | %level_name% | %message%\n";
$dateFormat = "Y-m-d H:i:s";
$formatter = new LineFormatter($logFormat, $dateFormat, true, true);
$log = new Logger('slim_app');
$logLevel = $config['log_level'] ?? Logger::DEBUG;
$streamhandler = new StreamHandler($config['log_file'], $logLevel);
$streamhandler->setFormatter($formatter);
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

$myfile = 'assets'.'/'.$config['name_file'];
$myname = file_get_contents($myfile);
$app->getContainer()->get('view')->getEnvironment()->addGlobal('myname', $myname);


$app->getContainer()->get('view')->getEnvironment()->addGlobal('usemongo', $config['usemongo']);

$cocktailModel = $container->get(CocktailModel::class);
$cocktailcount = $cocktailModel->getAllCount();
$app->getContainer()->get('view')->getEnvironment()->addGlobal('mongococktails', $cocktailcount);


$jokeModel = $container->get(JokeModel::class);
$jokecount = $jokeModel->getAllCount();
$app->getContainer()->get('view')->getEnvironment()->addGlobal('mongojokes', $jokecount);



// Home Route
$app->get('/', function ($request, $response, $args) use ($container,$config) {
    $log = $container->get(LoggerInterface::class);
    $backgroundImageFilename = BSHelper::getBackgroundImage($config, $log);
    $backgroundImageUrl = $config['img_path'].'/backgrounds/'. $backgroundImageFilename;
    return $this->get('view')->render($response, 'home.twig', [
        'appName' => $config['app_name'],
        'backgroundImage' => $backgroundImageUrl
    ]);
});

//Joke route
$app->get('/joke', function ($request, $response, $args) use ($container, $config) {
    $log = $container->get(LoggerInterface::class);
    $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    $log->info($ip . '|  Monjoke route accessed');

    $backgroundImageFilename = BSHelper::getBackgroundImage($config, $log);
    $backgroundImageUrl = $config['img_path'].'/backgrounds/'. $backgroundImageFilename;

    $joke = null;

    if ($config['usemongo']) {

        $jokeModel = $container->get(JokeModel::class);
        $count = $jokeModel->getLastHourCount();

        if ($count > 20) {
            $joke = $jokeModel->getRandomJoke($jokesCollection);    
            $log->debug($ip . ' | Joke retrieved from MongoDB: throttle' . $joke['setup']);
        } else {
            try {
                $joke = BSHelper::getJokeFromApi($log, $ip);
                $log->debug($ip . ' | Joke retrieved from API - non throttle');
                if ($joke->type != 'error') {
                    $storedJoke = $jokeModel->findOrCreateJoke($log, $joke);                    
                }
            } catch (RequestException $e) {
                $log->error("Error getting joke: " . $e->getMessage());
                return $this->get('view')->render($response, 'err.twig', []);
            }
        }
    } else {
        $log->debug("Getting joke from api - no persistance");
        $joke = BSHelper::getJokeFromApi($log, $ip);
    }

    return $this->get('view')->render($response, 'joke.twig', [
        'joke' => $joke,
        'backgroundImage' => $backgroundImageUrl
    ]);
});

$app->get('/fibo/{fnumber}', function($request, $response, $args) use ($container, $config) {

    $fnumber = (int)$args['fnumber'];
    $log = $container->get(LoggerInterface::class);
    $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';

    $log->info($ip. ' |  Fibo route accessed with number: ' . $fnumber);

    $fibonacci = BSHelper::fibonacci($fnumber);

    $backgroundImageFilename = BSHelper::getBackgroundImage($config, $log);
    $backgroundImageUrl = $config['img_path'].'/backgrounds/'. $backgroundImageFilename;

    return $this->get('view')->render($response, 'fibonacci.twig', [
        'fnumber' => $fnumber,
        'fibonacci' => $fibonacci,
        'backgroundImage' => $backgroundImageUrl
    ]);


});


// Cocktail Route
$app->get('/cocktail', function ($request, $response, $args) use ($container, $config) {
    $log = $container->get(LoggerInterface::class);
    $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';

    $log->info($ip. ' |  Cocktail route accessed');

    $backgroundImageFilename = BSHelper::getBackgroundImage($config, $log);
    $backgroundImageUrl = $config['img_path'].'/backgrounds/'. $backgroundImageFilename;

    $cocktail = null;    

    if ($config['usemongo']) {
        $cocktailModel = $container->get(CocktailModel::class);
        $count = $cocktailModel->getLastHourCount();

        if ($count > 20) {
            $log->debug("Getting cocktail from mongo - throttle");
            $cocktail = $cocktailModel->getRandomCocktail();    
        } else {
            try {
                $cocktail = BSHelper::getCocktailFromApi($log, $ip);
                $log->debug("Getting cocktail from api - non throttle");
                if ($cocktail->strCategory != 'error') {
                    $storedCocktail = $cocktailModel->findOrCreateCocktail($log, $cocktail);                    
                }
            } catch (RequestException $e) {
                $log->error("Error getting cocktail: " . $e->getMessage());
                return $this->get('view')->render($response, 'err.twig', []);
            }
        }
    } else {
        $log->debug("Getting cocktail from api - no persistance");
        $cocktail = BSHelper::getCocktailFromApi($log, $ip);
    }

    //image download
    $thumbnail = BSHelper::getThumbLocalUrl($cocktail, $config);
    return $this->get('view')->render($response, 'cocktail.twig', [
        'cocktail' => $cocktail,
        'thumbnail' => $thumbnail,
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




