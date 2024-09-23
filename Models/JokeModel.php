<?php


use MongoDB\Client as MongoClient; 
use MongoDB\BSON\UTCDateTime; 

class JokeModel {
    protected $collection;
    private $collectionName = 'jokes';

    public function __construct(MongoClient  $mongoClient, $databaseName) {
        $this->collection = $mongoClient->{$databaseName}->{$this->collectionName};
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
    
        $randomJoke = $this->collection->aggregate([
            ['$sample' => ['size' => 1]] 
        ]);
    
        return iterator_to_array($randomJoke, false)[0] ?? $errorJoke; 
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
