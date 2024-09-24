<?php


use MongoDB\Client as MongoClient; 
use MongoDB\BSON\UTCDateTime; 

class JokeModel {
    protected $collection;
    private $collectionName = 'jokes';

    public function __construct(MongoClient  $mongoClient, $databaseName) {
        $this->collection = $mongoClient->{$databaseName}->{$this->collectionName};
	$this->collection->createIndex(['id' => 1]);
        $this->collection->createIndex(['created_at' => 1]); // Index for efficient date-based queries

    }

    public function findOrCreateJoke($log, $jokeData) {

        $jokeId = $jokeData->id; 
        $existingJoke = $this->collection->findOne(['id' => $jokeId]);
        
        if ($existingJoke) {
            $log->debug(' | Mongo has joke');    
            $this->collection->updateOne(
                ['id' => $jokeId],
                ['$inc' => ['hit' => 1]]
            );
            return $existingJoke; 
        } else {
            $jokeDataToInsert = [
                'id' => $jokeId,
                'setup' => $jokeData->setup,
                'punchline' => $jokeData->punchline,
                'created_at' => new MongoDB\BSON\UTCDateTime(), 
                'hit' => 1 
            ];
            $this->collection->insertOne($jokeDataToInsert);
            $log->debug(' | Mongo got joke inserted');    
            return $jokeDataToInsert; 
        }
    }
    
    function getRandomJoke() {
        $errorJoke = ['type' => 'error', 'setup' => "What is a mongo that isn't?", 'punchline' => "A NonGo", "id" => 0];
    
	$count = $this->collection->countDocuments();
	$random = rand(0, $count - 1);
	$randomJoke = $this->collection->findOne([], ['skip' => $random]);
    
        return $randomJoke ?? $errorJoke; 
    }
    
    function getLastHourCount() {
    
        $currentDate = new UTCDateTime();
        $oneHourAgo = new UTCDateTime((new DateTime('-1 hour', new DateTimeZone('UTC')))->getTimestamp() * 1000);
    
        $count = $this->collection->countDocuments([
            'created_at' => ['$gte' => $oneHourAgo] 
        ]);
    
        return $count;
    }
    function getAllCount() {
    
        $count = $this->collection->countDocuments([]);
        return $count;
    }


}
