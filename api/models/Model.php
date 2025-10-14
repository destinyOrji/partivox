<?php
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\Regex;

abstract class Model {
    protected $collection;
    protected $db;
    protected $collectionName;

    public function __construct() {
        $this->db = Database::getDB();
        $this->collection = $this->db->{$this->collectionName};
    }

    /**
     * Find a single document by ID
     */
    public function findById($id) {
        try {
            if (class_exists('MongoDB\\BSON\\ObjectId')) {
                $id = new ObjectId($id);
            }
            return $this->collection->findOne(['_id' => $id]);
        } catch (Exception $e) {
            throw new Exception("Error finding document: " . $e->getMessage());
        }
    }

    /**
     * Find documents by filter
     */
    public function find($filter = [], $options = []) {
        try {
            return $this->collection->find($filter, $options);
        } catch (Exception $e) {
            throw new Exception("Error finding documents: " . $e->getMessage());
        }
    }

    /**
     * Create a new document
     */
    public function create($data) {
        try {
            if (!isset($data['createdAt'])) {
                $data['createdAt'] = class_exists('MongoDB\\BSON\\UTCDateTime') ? new UTCDateTime() : date('c');
            }
            if (!isset($data['updatedAt'])) {
                $data['updatedAt'] = class_exists('MongoDB\\BSON\\UTCDateTime') ? new UTCDateTime() : date('c');
            }

            $result = $this->collection->insertOne($data);
            return $result->getInsertedId();
        } catch (Exception $e) {
            throw new Exception("Error creating document: " . $e->getMessage());
        }
    }

    /**
     * Update a document by ID
     */
    public function updateById($id, $data) {
        try {
            $data['updatedAt'] = class_exists('MongoDB\\BSON\\UTCDateTime') ? new UTCDateTime() : date('c');
            if (class_exists('MongoDB\\BSON\\ObjectId')) {
                $id = new ObjectId($id);
            }
            $result = $this->collection->updateOne(
                ['_id' => $id],
                ['$set' => $data]
            );
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) {
            throw new Exception("Error updating document: " . $e->getMessage());
        }
    }

    /**
     * Delete a document by ID
     */
    public function deleteById($id) {
        try {
            if (class_exists('MongoDB\\BSON\\ObjectId')) {
                $id = new ObjectId($id);
            }
            $result = $this->collection->deleteOne(['_id' => $id]);
            return $result->getDeletedCount() > 0;
        } catch (Exception $e) {
            throw new Exception("Error deleting document: " . $e->getMessage());
        }
    }
}
