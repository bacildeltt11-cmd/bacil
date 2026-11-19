<?php
// Kode di bawah digunakan untuk memastikan file koneksi MongoDB hanya dimuat satu kali
if (defined('KONEKSI_MONGODB_LOADED')) {
    return;
}
define('KONEKSI_MONGODB_LOADED', true);

// Kode di bawah digunakan untuk memuat file konfigurasi URI dan nama database
require_once 'config.php';

// Kode di bawah digunakan untuk menginisialisasi driver client MongoDB Manager
$client = new MongoDB\Driver\Manager($mongodb_uri);

// Kode di bawah digunakan untuk menguji koneksi ke MongoDB Atlas jika diakses dengan parameter ?test_connection=1
if (isset($_GET['test_connection']) && $_GET['test_connection'] == '1') {
    try {
        $client->executeCommand($database, new MongoDB\Driver\Command(['ping' => 1]));
        echo "Koneksi MongoDB OK";
        exit;
    } catch (MongoDB\Driver\Exception\ConnectionException $e) {
        echo "Koneksi gagal: " . $e->getMessage();
        exit;
    }
}

/**
 * Class Session Handler Berbasis MongoDB
 * Menyimpan data session PHP ke dalam collection 'sessions' di MongoDB agar persistensi session tetap terjaga (serverless ready).
 */
class MongoDBSessionHandler implements SessionHandlerInterface {
    private $client;
    private $database;

    // Konstruktor untuk menerima objek client manager dan nama database
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

    // Kode di bawah digunakan untuk membaca data session dari collection 'sessions' berdasarkan session ID
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

    // Kode di bawah digunakan untuk menyimpan atau memperbarui data session (upsert) ke MongoDB
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

    // Kode di bawah digunakan untuk menghapus data session dari MongoDB saat user logout
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

    // Kode di bawah digunakan untuk pembersihan berkala (Garbage Collection) session yang sudah kedaluwarsa
    public function gc(int $max_lifetime): int|false {
        try {
            $bulk = new MongoDB\Driver\BulkWrite;
            $cutoff = new MongoDB\BSON\UTCDateTime((time() - $max_lifetime) * 1000);
            $bulk->delete(['updated_at' => ['$lt' => $cutoff]]);
            $this->client->executeBulkWrite("{$this->database}.sessions", $bulk);
        } catch (\Throwable $e) {
            // Abaikan jika ada error pembersihan
        }
        return 1;
    }
}

// Kode di bawah digunakan untuk mendaftarkan MongoDBSessionHandler sebagai session save handler sistem jika session belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 0);
    $sessionHandler = new MongoDBSessionHandler($client, $database);
    session_set_save_handler($sessionHandler, true);
}

// Kode di bawah adalah fungsi pembantu untuk menyisipkan satu dokumen baru ke dalam collection MongoDB
function insertDocument($collection, $document) {
    global $client, $database;
    $bulk = new MongoDB\Driver\BulkWrite;
    
    // Memberikan ObjectId unik jika dokumen belum memilikinya
    if (!isset($document['_id'])) {
        $document['_id'] = new MongoDB\BSON\ObjectId;
    }
    
    $bulk->insert($document);
    $client->executeBulkWrite("{$database}.{$collection}", $bulk);
    
    return (string) $document['_id'];
}

// Kode di bawah adalah fungsi pembantu untuk memperbarui dokumen di collection MongoDB berdasarkan filter
function updateDocument($collection, $filter, $update) {
    global $client, $database;
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->update($filter, $update, ['multi' => false, 'upsert' => false]);
    $result = $client->executeBulkWrite("{$database}.{$collection}", $bulk);
    return $result->getModifiedCount();
}

// Kode di bawah adalah fungsi pembantu untuk menghapus dokumen dari collection MongoDB berdasarkan filter
function deleteDocument($collection, $filter) {
    global $client, $database;
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->delete($filter);
    $result = $client->executeBulkWrite("{$database}.{$collection}", $bulk);
    return $result->getDeletedCount();
}

// Kode di bawah adalah fungsi pembantu untuk mengambil banyak dokumen (SELECT) dari collection MongoDB
function findDocuments($collection, $filter = [], $options = []) {
    global $client, $database;
    $query = new MongoDB\Driver\Query($filter, $options);
    $cursor = $client->executeQuery("{$database}.{$collection}", $query);
    return $cursor->toArray();
}

// Kode di bawah adalah fungsi pembantu untuk mengambil satu dokumen pertama yang cocok dari collection MongoDB
function findOneDocument($collection, $filter, $options = []) {
    global $client, $database;
    $query = new MongoDB\Driver\Query($filter, $options);
    $cursor = $client->executeQuery("{$database}.{$collection}", $query);
    $arr = $cursor->toArray();
    return count($arr) > 0 ? $arr[0] : null;
}

// Kode di bawah adalah fungsi pembantu untuk menjalankan query agregasi (pipeline) di MongoDB
function aggregate($collection, $pipeline) {
    global $client, $database;
    $command = new MongoDB\Driver\Command([
        'aggregate' => $collection,
        'pipeline' => $pipeline,
        'cursor' => new stdClass()
    ]);
    $cursor = $client->executeCommand($database, $command);
    return $cursor->toArray();
}

// Kode di bawah adalah fungsi pembantu untuk menghitung jumlah total dokumen dalam collection
function countDocuments($collection, $filter = []) {
    global $client, $database;
    $query = empty($filter) ? new stdClass() : $filter;
    $command = new MongoDB\Driver\Command([
        'count' => $collection,
        'query' => $query
    ]);
    $cursor = $client->executeCommand($database, $command);
    $result = $cursor->toArray();
    return $result[0]->n ?? 0;
}

// Kode di bawah digunakan untuk memastikan akun pengguna Boss default sudah ada di dalam database
function ensureBossUserExists() {
     global $client, $database;
     $existing = findOneDocument("pengguna", ["username" => "boss"]);
     if (!$existing) {
         // Membuat password acak yang kuat
         $random_pass = bin2hex(random_bytes(6));
         $password = password_hash($random_pass, PASSWORD_DEFAULT);
         insertDocument("pengguna", [
             "username" => "boss",
             "nama_pengguna" => "Boss",
             "password" => $password,
             "must_change_password" => true,
             "created_at" => new MongoDB\BSON\UTCDateTime()
         ]);
         // Mencatat password awal ke error log
         error_log("Initial boss password: $random_pass");
     }
}

// Kode di bawah digunakan untuk membuat indeks (indexing) pada MongoDB demi mempercepat performa pencarian query
function createIndexes() {
    global $client, $database;

    $indexes = [
        // Indeks untuk collection manifest
        ["manifest", ["created_by" => 1], "created_by_idx"],
        ["manifest", ["tanggal" => -1], "tanggal_idx"],
        ["manifest", ["kapal" => 1], "kapal_idx"],
        ["manifest", ["nopol" => 1], "nopol_idx"],
        ["manifest", ["created_by" => 1, "tanggal" => -1], "created_by_tanggal_idx"],

        // Indeks untuk collection muatan
        ["muatan", ["id_manifest" => 1], "id_manifest_idx"],

        // Indeks unik untuk master collections
        ["master_barang", ["nama" => 1], "nama_idx", true],
        ["master_kapal", ["nama" => 1], "nama_idx", true],
        ["master_jenis", ["kode" => 1], "kode_idx", true],
        ["master_nopol", ["nopol" => 1], "nopol_idx", true],

        // Indeks unik untuk username akun pengguna
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
            // Melewati error jika index sudah ada sebelumnya
            error_log("Index creation skipped for {$idx[0]}: " . $e->getMessage());
        }
    }
}

// Inisialisasi otomatis jika endpoint diakses dengan parameter khusus ?init_db=1
if (isset($_GET['init_db']) && $_GET['init_db'] == '1') {
    try {
        ensureBossUserExists();
        createIndexes();
        echo "DB Initialized successfully";
        exit;
    } catch (Exception $e) {
        error_log("DB init error: " . $e->getMessage());
    }
}
?>
