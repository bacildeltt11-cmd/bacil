<?php
require_once 'config.php';

$client = new MongoDB\Driver\Manager($mongodb_uri);

try {
    $client->executeCommand($database, new MongoDB\Driver\Command(['ping' => 1]));
} catch (MongoDB\Driver\Exception\ConnectionException $e) {
    echo "Koneksi gagal: " . $e->getMessage();
    exit;
}

function insertDocument($collection, $document) {
    global $client, $database;
    $bulk = new MongoDB\Driver\BulkWrite;
    
    if (!isset($document['_id'])) {
        $document['_id'] = new MongoDB\BSON\ObjectId;
    }
    
    $bulk->insert($document);
    $client->executeBulkWrite("{$database}.{$collection}", $bulk);
    
    return (string) $document['_id'];
}

function updateDocument($collection, $filter, $update) {
    global $client, $database;
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->update($filter, $update, ['multi' => false, 'upsert' => false]);
    $result = $client->executeBulkWrite("{$database}.{$collection}", $bulk);
    return $result->getModifiedCount();
}

function deleteDocument($collection, $filter) {
    global $client, $database;
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->delete($filter);
    $result = $client->executeBulkWrite("{$database}.{$collection}", $bulk);
    return $result->getDeletedCount();
}

function findDocuments($collection, $filter = [], $options = []) {
    global $client, $database;
    $query = new MongoDB\Driver\Query($filter, $options);
    $cursor = $client->executeQuery("{$database}.{$collection}", $query);
    return $cursor->toArray();
}

function findOneDocument($collection, $filter, $options = []) {
    global $client, $database;
    $query = new MongoDB\Driver\Query($filter, $options);
    $cursor = $client->executeQuery("{$database}.{$collection}", $query);
    $arr = $cursor->toArray();
    return count($arr) > 0 ? $arr[0] : null;
}

function aggregate($collection, $pipeline) {
    global $client, $database;
    $command = new MongoDB\Driver\Command([
        'aggregate' => $collection,
        'pipeline' => $pipeline, // Pipeline is already array of arrays, should work
        'cursor' => new stdClass()
    ]);
    $cursor = $client->executeCommand($database, $command);
    return $cursor->toArray();
}

function countDocuments($collection, $filter = []) {
    global $client, $database;
    // MongoDB expects BSON document for query, not PHP array
    $query = empty($filter) ? new stdClass() : $filter;
    $command = new MongoDB\Driver\Command([
        'count' => $collection,
        'query' => $query
    ]);
    $cursor = $client->executeCommand($database, $command);
    $result = $cursor->toArray();
    return $result[0]->n ?? 0;
}

function ensureBossUserExists() {
     global $client, $database;
     $existing = findOneDocument("pengguna", ["username" => "boss"]);
     if (!$existing) {
         // Generate strong random password (12 characters)
         $random_pass = bin2hex(random_bytes(6));
         $password = password_hash($random_pass, PASSWORD_DEFAULT);
         insertDocument("pengguna", [
             "username" => "boss",
             "nama_pengguna" => "Boss",
             "password" => $password,
             "must_change_password" => true,
             "created_at" => new MongoDB\BSON\UTCDateTime()
         ]);
         // Log or display the password once (only on first setup)
         error_log("Initial boss password: $random_pass");
     }
 }

function createIndexes() {
    global $client, $database;

    $indexes = [
        // manifest collection
        ["manifest", ["created_by" => 1], "created_by_idx"],
        ["manifest", ["tanggal" => -1], "tanggal_idx"],
        ["manifest", ["kapal" => 1], "kapal_idx"],
        ["manifest", ["nopol" => 1], "nopol_idx"],
        ["manifest", ["created_by" => 1, "tanggal" => -1], "created_by_tanggal_idx"],

        // muatan collection
        ["muatan", ["id_manifest" => 1], "id_manifest_idx"],

        // master collections (unique)
        ["master_barang", ["nama" => 1], "nama_idx", true],
        ["master_kapal", ["nama" => 1], "nama_idx", true],
        ["master_jenis", ["kode" => 1], "kode_idx", true],
        ["master_nopol", ["nopol" => 1], "nopol_idx", true],

        // pengguna collection (unique)
        ["pengguna", ["username" => 1], "username_idx", true]
    ];

    foreach ($indexes as $idx) {
        try {
            $collection = $idx[0];
            $keys = $idx[1];
            $name = $idx[2];
            $unique = $idx[3] ?? false;

            $command = new MongoDB\Driver\Command([
                'createIndexes' => $collection,
                'indexes' => [
                    [
                        'key' => $keys,
                        'name' => $name,
                        'unique' => $unique
                    ]
                ]
            ]);
            $client->executeCommand($database, $command);
        } catch (Exception $e) {
            // Skip index creation errors (index might already exist)
            error_log("Index creation skipped for {$idx[0]}: " . $e->getMessage());
        }
    }
}

// Pastikan user boss default ada
ensureBossUserExists();

// Call createIndexes when the application initializes (once)
try {
    createIndexes();
} catch (Exception $e) {
    error_log("Index creation skipped: " . $e->getMessage());
}

?>