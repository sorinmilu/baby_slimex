<?php


use MongoDB\Client as MongoClient; 
use MongoDB\BSON\UTCDateTime; 

class CocktailModel {
    protected $collection;
    private $collectionName = 'cocktails';

    public function __construct(MongoClient  $mongoClient, $databaseName) {
        $this->collection = $mongoClient->{$databaseName}->{$this->collectionName};
    }

    public function findOrCreateCocktail($data) {

        $ckId = $data->idDrink; 

        $existingCocktail = $this->collection->findOne(['idDrink' => $ckId]);
        
        if ($existingCocktail) {
            error_log('updating increment');
            $this->collection->updateOne(
                ['idDrink' => $ckId],
                ['$inc' => ['hit' => 1]]
            );
            return $existingCocktail; 
        } else {
            $dataArray = json_decode(json_encode($data), true);
            $dataArray['created_at'] = new MongoDB\BSON\UTCDateTime();    
            $dataArray['hit'] = 1;    

            $this->collection->insertOne($dataArray);
            return $dataArray; 
        }
    }
    
    function getRandomCocktail() {
        $errorCocktail = ['strCategory' => 'error', 'strInstructions' => "Mix nothing with nothing", "id" => 0];
    
        $random = $this->collection->aggregate([
            ['$sample' => ['size' => 1]] 
        ]);
    
        return iterator_to_array($randomCocktail, false)[0] ?? $errorCocktail; 
    }
    
    function getLastHourCount() {
    
        $currentDate = new UTCDateTime();
        $oneHourAgo = new UTCDateTime((new DateTime('-1 hour', new DateTimeZone('UTC')))->getTimestamp() * 1000);
    
        $count = $this->collection->countDocuments([
            'created_at' => ['$gte' => $oneHourAgo] 
        ]);
    
        return $count;
    }
    
    

}
