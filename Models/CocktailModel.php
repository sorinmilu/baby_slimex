<?php


use MongoDB\Client as MongoClient; 
use MongoDB\BSON\UTCDateTime; 

class CocktailModel {
    protected $collection;
    private $collectionName = 'cocktails';

    public function __construct(MongoClient  $mongoClient, $databaseName) {
        $this->collection = $mongoClient->{$databaseName}->{$this->collectionName};
	$this->collection->createIndex(['id' => 1]);
        $this->collection->createIndex(['created_at' => 1]); 
    }

    public function findOrCreateCocktail($log, $data) {

        $ckId = $data->idDrink; 

        $existingCocktail = $this->collection->findOne(['idDrink' => $ckId]);
        if ($existingCocktail) {
            $log->debug(' | Mongo has cocktail');  
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
            $log->debug(' | Mongo got cocktail inserted');    
            return $dataArray; 
        }
    }
    
    function getRandomCocktail() {
        $errorCocktail = ['strCategory' => 'error', 'strInstructions' => "Mix nothing with nothing", "id" => 0];

	$count = $this->collection->countDocuments();
	$random = rand(0, $count - 1);
	$randomCocktail = $this->collection->findOne([], ['skip' => $random]);
        return $randomCocktail ?? $errorCocktail;
    }
    
    function getLastHourCount() {
    
        $currentDate = new UTCDateTime();
        $oneHourAgo = new UTCDateTime((new DateTime('-1 hour', new DateTimeZone('UTC')))->getTimestamp() * 1000);
    
        $count = $this->collection->countDocuments([
            'created_at' => ['$gte' => $oneHourAgo] 
        ]);
    
        return $count;
    }

    function getLastMinuteCount() {
    
        $currentDate = new UTCDateTime();
        $oneMinAgo = new UTCDateTime((new DateTime('-1 minute', new DateTimeZone('UTC')))->getTimestamp() * 1000);
    
        $count = $this->collection->countDocuments([
            'created_at' => ['$gte' => $oneMinAgo] 
        ]);
    
        return $count;
    }


    function getAllCount() {
        $count = $this->collection->countDocuments([]);
    
        return $count;
    }
    

}
