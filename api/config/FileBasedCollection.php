<?php
// Simple file-based collection for Render free tier
class FileBasedCollection {
    private $name;
    private $dataDir;
    
    public function __construct($name) {
        $this->name = $name;
        $this->dataDir = __DIR__ . '/../../data';
        
        // Create data directory if it doesn't exist
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    private function getFilePath() {
        return $this->dataDir . '/' . $this->name . '.json';
    }
    
    private function loadData() {
        $file = $this->getFilePath();
        if (!file_exists($file)) {
            return [];
        }
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }
    
    private function saveData($data) {
        $file = $this->getFilePath();
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public function findOne($query) {
        $data = $this->loadData();
        foreach ($data as $item) {
            $match = true;
            foreach ($query as $key => $value) {
                if (!isset($item[$key]) || $item[$key] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return (object)$item;
            }
        }
        return null;
    }
    
    public function insertOne($document) {
        $data = $this->loadData();
        $document['_id'] = uniqid();
        $document['created_at'] = date('Y-m-d H:i:s');
        $data[] = $document;
        $this->saveData($data);
        return (object)['insertedId' => $document['_id']];
    }
    
    public function updateOne($query, $update) {
        $data = $this->loadData();
        foreach ($data as $index => $item) {
            $match = true;
            foreach ($query as $key => $value) {
                if (!isset($item[$key]) || $item[$key] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                if (isset($update['$set'])) {
                    $data[$index] = array_merge($item, $update['$set']);
                }
                $this->saveData($data);
                return (object)['modifiedCount' => 1];
            }
        }
        return (object)['modifiedCount' => 0];
    }
    
    public function updateMany($query, $update) {
        $data = $this->loadData();
        $modifiedCount = 0;
        foreach ($data as $index => $item) {
            $match = true;
            foreach ($query as $key => $value) {
                if (!isset($item[$key]) || $item[$key] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                if (isset($update['$set'])) {
                    $data[$index] = array_merge($item, $update['$set']);
                    $modifiedCount++;
                }
            }
        }
        $this->saveData($data);
        return (object)['modifiedCount' => $modifiedCount];
    }
    
    public function countDocuments($query = []) {
        $data = $this->loadData();
        if (empty($query)) {
            return count($data);
        }
        
        $count = 0;
        foreach ($data as $item) {
            $match = true;
            foreach ($query as $key => $value) {
                if (!isset($item[$key]) || $item[$key] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $count++;
            }
        }
        return $count;
    }
    
    public function aggregate($pipeline) {
        // Simple aggregation for basic operations
        $data = $this->loadData();
        
        foreach ($pipeline as $stage) {
            if (isset($stage['$match'])) {
                $filtered = [];
                foreach ($data as $item) {
                    $match = true;
                    foreach ($stage['$match'] as $key => $value) {
                        if (is_array($value) && isset($value['$in'])) {
                            if (!in_array($item[$key] ?? null, $value['$in'])) {
                                $match = false;
                                break;
                            }
                        } elseif (!isset($item[$key]) || $item[$key] !== $value) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        $filtered[] = $item;
                    }
                }
                $data = $filtered;
            } elseif (isset($stage['$group'])) {
                $grouped = [];
                foreach ($data as $item) {
                    $key = $stage['$group']['_id'] ?? 'default';
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [];
                    }
                    $grouped[$key][] = $item;
                }
                
                $result = [];
                foreach ($grouped as $key => $items) {
                    $groupResult = ['_id' => $key];
                    foreach ($stage['$group'] as $field => $operation) {
                        if ($field === '_id') continue;
                        
                        if (is_array($operation) && isset($operation['$sum'])) {
                            $sumField = $operation['$sum'];
                            $total = 0;
                            foreach ($items as $item) {
                                $total += $item[$sumField] ?? 0;
                            }
                            $groupResult[$field] = $total;
                        }
                    }
                    $result[] = (object)$groupResult;
                }
                return $result;
            }
        }
        
        return array_map(function($item) { return (object)$item; }, $data);
    }
}
?>
