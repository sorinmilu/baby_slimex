<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class BSHelper {
    public static function fetchBackgroundImage($size, $imgBase, $log) {

        $imgDir = $imgBase. '/backgrounds/';
    
        if (!is_dir($imgDir)) {
            $log->debug("| Creating target directory for images: ". $imgDir);
            mkdir($imgDir, 0755, true);
        }
    
        $client = new Client(['allow_redirects' => true]);
        $imageUrl = "https://picsum.photos/{$size}";
    
        try {
    
            $log->debug("| Fetching image from URL: $imageUrl");
    
            // Send a GET request to the image URL
            $response = $client->request('GET', $imageUrl, ['stream' => true]);
    
            // Track the final URL after redirects
            $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
            $finalUrl = end($redirectHistory) ?: $imageUrl;
    
            $log->debug("| Final URL after redirection: $finalUrl");
    
            // Get the image content from the final URL
            $imageResponse = $client->request('GET', $finalUrl, ['stream' => true]);
            $imageContent = $imageResponse->getBody();
    
            // Generate a unique filename with correct naming convention
            $filename = 'bg_' . uniqid() . '.jpg';
            $filePath = $imgDir . $filename;
    
            // Save the image content to the file
            file_put_contents($filePath, $imageContent);
    
            $log->debug("| Image saved to: $filePath");
    
    
            return $filename; // Return the filename to be used in the URL
    
        } catch (RequestException $e) {
            $log->error("Error fetching image: " . $e->getMessage());
            return null;
        }
    }
    
        // Function to get or fetch a background image
    public static function getBackgroundImage($config, $log) {
            $cachedImages = glob($config['img_dir']. '/backgrounds/' . 'bg_*.jpg');
       
            $log->debug("| we have ". count($cachedImages) . " images in cache");
        
            // Use cache if there are more than n images
            if (count($cachedImages) > $config['cached_images'] -1) {
                $log->debug("| Selecting a random cached background image.");
                $randomImage = $cachedImages[array_rand($cachedImages)];
                return basename($randomImage);
            }
        
            // No sufficient cached images, fetch a new one
            return self::fetchBackgroundImage($config['background_image_size'], $config['img_dir'], $log);
        }

        // Function to get a joke from the API
    public static function getJokeFromApi($log, $ip) {
        $client = new Client();
        try {
            $jokeApiResponse = $client->request('GET', 'https://official-joke-api.appspot.com/random_joke');
            $joke = json_decode($jokeApiResponse->getBody());
            $log->debug($ip . ' | Joke retrieved from API: ' . $joke->setup);
            return $joke;
        } catch (RequestException $e) {
            $log->error("Error getting joke from API: " . $e->getMessage());
            $emptyJoke = new stdClass();
            $emptyJoke->setup = "What if there's no joke?";
            $emptyJoke->punchline = 'This is no joke, man';
            $emptyJoke->type = 'error';
            return $emptyJoke; 
        }
    }

    public static function getCocktailFromApi($log, $ip) {
        $client = new Client();
        try {
            $cocktailApiResponse = $client->request('GET', 'https://www.thecocktaildb.com/api/json/v1/1/random.php');
            $cocktail = json_decode($cocktailApiResponse->getBody())->drinks[0];
            return $cocktail;
        } catch (RequestException $e) {
            $log->error( $ip . "| | Error getting recipe: " . $e->getMessage());
            $emptyCocktail = new stdClass();
            $emptyCocktail->strCategory = 'error';
            $emptyCocktail->strInstructions = "Mix nothing with nothing";
            return emptyCocktail; 
        }
    }

    public static function getThumbLocalUrl($array, $config) {
        if (!isset($array->idDrink, $array->strDrinkThumb)) {
            return null; // or handle error as needed
        }

        // Prepare the local directory and file path
        $drinkId = $array->idDrink;
        $thumbUrl = $array->strDrinkThumb;    
        $localDir =  $config['img_dir'] ."/cocktails" ."/$drinkId";
        $localFilePath = "$localDir/{$drinkId}_thumb.jpg";

        if (!file_exists($localFilePath)) {

            // Create the directory if it doesn't exist
            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }

            // Download and save the image
            try {
                $imageContent = file_get_contents($thumbUrl);
                if ($imageContent === false) {
                    error_log('failed download');
                    throw new Exception("Failed to download image from $thumbUrl");
                }

                file_put_contents($localFilePath, $imageContent);
            } catch (Exception $e) {
                return null; // or return an error message
            }
        }

        return $config['img_path']."/cocktails/$drinkId/{$drinkId}_thumb.jpg"; 
    }

	public static function fibonacci($n) {
            if ($n < 0) {
            throw new InvalidArgumentException("Negative arguments are not allowed.");
	    }
    	if ($n <= 1) {
            return $n; // Return 0 for n=0 and 1 for n=1
        }
        return self::fibonacci($n - 1) + self::fibonacci($n - 2); // Recursive call
    }

    public static function getDbName($config) {
        if ($config['usevault']) {
            return self::getSecretFromKeyVault($config, 'cosmodb');
        }  elseif ($config['envvault']) {
            return $_ENV['APPSETTING_cosmodb'];   
        } else {
            return $_ENV['cosmodb'] ?? 'baby_slimex';       
        }
    }
    public static function getMongoDbUri($config) {

        if ($config['usevault']) {

            $username = self::getSecretFromKeyVault($config, 'cosmouser');
            $password = self::getSecretFromKeyVault($config, 'cosmopasswd');
            $hostname = self::getSecretFromKeyVault($config, 'cosmohost');

            // Construct the MongoDB URI
            $mongoUri = "mongodb+srv://{$username}:{$password}@{$hostname}.mongocluster.cosmos.azure.com/?tls=true&authMechanism=SCRAM-SHA-256&retrywrites=false&maxIdleTimeMS=120000";


        } elseif ($config['envvault']) {
            $username = $_ENV['APPSETTING_cosmouser'];
            $password = $_ENV['APPSETTING_cosmopasswd'];
            $hostname = $_ENV['APPSETTING_cosmohost'];

            // Construct the MongoDB URI
            $mongoUri = "mongodb+srv://{$username}:{$password}@{$hostname}.mongocluster.cosmos.azure.com/?tls=true&authMechanism=SCRAM-SHA-256&retrywrites=false&maxIdleTimeMS=120000";
            return $mongoUri;

        } else {
            return $_ENV['MONGODB_URI'];
        }

    }

    public static function getSecretFromKeyVault($config, $secretName) {
        if ($config['cachevault']) {
            $cacheKey = 'bslm_'.$secretName;

            if (isset($_ENV['CACHEPREFIX'])) {
                $cacheKey = $_ENV['CACHEPREFIX'].$secretName;
            }

            $cachedSecret = apcu_fetch($cacheKey);
            if ($cachedSecret !== false) {
                // Return the cached URI
                return $cachedSecret;
            }
        }

        $accessToken = self::getVaultAccessToken($config);
        $vaultname = $_ENV['VAULTNAME'];

        $url = "https://$vaultname.vault.azure.net/secrets/$secretName?api-version=7.2";

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ]);

        // Execute the request
        $response = curl_exec($ch);

        // Handle errors
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        // Close cURL session
        curl_close($ch);

        // Decode the response
        $data = json_decode($response, true);
	$secret = $data['value'] ?? null;
    
	if ($config['cachevault']) {
            apcu_store($cacheKey, $secret, 3600); // Cache for 1 hour (3600 seconds)
	}
        return $secret;
    }


    private static function getVaultAccessToken($config) {
        $url = 'http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01&resource=https://vault.azure.net';

        // Append client_id if provided (for user-assigned identity)
        if (isset($_ENV['CLIENTID'])) {
            $url .= '&client_id=' . $_ENV['CLIENTID'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Metadata: true'
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        $data = json_decode($response, true);

        return $data['access_token'] ?? null;
    }



}
