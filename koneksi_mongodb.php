<?php
require_once 'config.php';

$client = new MongoDB\Driver\Manager($mongodb_uri);

try {
    $client->executeCommand($database, new MongoDB\Driver\Command(['ping' => 1]));
} catch (MongoDB\Driver\Exception\ConnectionException $e) {
    echo "Koneksi gagal: " . $e->getMessage();
    exit;
}

/**
 * MongoDB-backed Session Handler
 * Stores session data in MongoDB 'sessions' collection for seamless persistence across Vercel serverless containers.
 */
class MongoDBSessionHandler implements SessionHandlerInterface {
    private $client;
    private $database;

    public function __construct($client, $database) {
        $this->client = $client;
        $this->database = $database;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        try {
            $query = new MongoDB\Driver\Query(['_id' => $id], ['limit' => 1]);
            $cursor = $this->client->executeQuery("{$this->database}.sessions", $query);
            $arr = $cursor->toArray();
            if (!empty($arr) && isset($arr[0]->data)) {
                return (string)$arr[0]->data;
            }
        } catch (\Throwable $e) {
            error_log("Session read error: " . $e->getMessage());
        }
        return '';
    }

    public function write(string $id, string $data): bool {
        try {
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update(
                ['_id' => $id],
                ['$set' => [
                    'data' => (string)$data,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]],
                ['upsert' => true]
            );
            $this->client->executeBulkWrite("{$this->database}.sessions", $bulk);
            return true;
        } catch (\Throwable $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool {
        try {
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->delete(['_id' => $id]);
            $this->client->executeBulkWrite("{$this->database}.sessions", $bulk);
        } catch (\Throwable $e) {
            error_log("Session destroy error: " . $e->getMessage());
        }
        return true;
    }

    public function gc(int $max_lifetime): int|false {
        try {
            $bulk = new MongoDB\Driver\BulkWrite;
            $cutoff = new MongoDB\BSON\UTCDateTime((time() - $max_lifetime) * 1000);
            $bulk->delete(['updated_at' => ['$lt' => $cutoff]]);
            $this->client->executeBulkWrite("{$this->database}.sessions", $bulk);
        } catch (\Throwable $e) {
            // ignore
        }
        return 1;
    }
}

// Aktifkan MongoDB Session Handler jika session belum berjalan
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 0);
    $sessionHandler = new MongoDBSessionHandler($client, $database);
    session_set_save_handler($sessionHandler, true);
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

// Pastikan user boss default ada (hanya jika belum)
try {
    ensureBossUserExists();
} catch (Exception $e) {
    error_log("Boss check skipped: " . $e->getMessage());
}

// createIndexes hanya jika dipanggil via query param ?init_db=1
if (isset($_GET['init_db']) && $_GET['init_db'] == '1') {
    try {
        createIndexes();
    } catch (Exception $e) {
        error_log("Index creation skipped: " . $e->getMessage());
    }
}

?>