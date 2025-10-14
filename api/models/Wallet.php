<?php
require_once __DIR__ . '/../config/db.php';
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class WalletModel {
    private $collection;

    public function __construct() {
        $this->collection = Database::getCollection('wallets');
    }

    public function findByUserId(string $userId) {
        return $this->collection->findOne(['user_id' => new ObjectId($userId)]);
    }

    public function ensureForUser(string $userId) {
        $existing = $this->findByUserId($userId);
        if (!$existing) {
            $this->collection->insertOne([
                'user_id' => new ObjectId($userId),
                'diamonds' => 0,
                'usdt' => 0.0,
                'created_at' => new UTCDateTime(),
                'updated_at' => new UTCDateTime(),
            ]);
        }
    }

    public function increment(string $userId, array $inc, array $set = []) {
        $set['updated_at'] = new UTCDateTime();
        return $this->collection->updateOne(
            ['user_id' => new ObjectId($userId)],
            ['$inc' => $inc, '$set' => $set]
        );
    }
}
