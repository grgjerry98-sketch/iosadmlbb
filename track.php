<?php
// track.php
// Simple receiver: writes events to a JSON file outside webroot
// SECURITY: configure $SECRET_TOKEN and DATA_PATH for your server

// === CONFIG ===
$SECRET_TOKEN = 'REPLACE_WITH_STRONG_TOKEN'; // change to a long random token, also used in frontend
// Recommended: store data outside public_html. Example: if this file is public_html/track.php,
// place data in public_html/../data/clicks.json  -> ../data relative path (create that folder)
$DATA_DIR = __DIR__ . '/../data'; // adjust if needed
$DATA_FILE = $DATA_DIR . '/clicks.json';
// Maximum number of log entries to keep in logs array
$MAX_LOGS = 2000;
// =============

header('Content-Type: application/json');

// check token: X-Tracker-Token header
$headers = getallheaders();
$token = '';
if(isset($headers['X-Tracker-Token'])) $token = $headers['X-Tracker-Token'];
elseif(isset($headers['x-tracker-token'])) $token = $headers['x-tracker-token'];

if(!$token || $token !== $SECRET_TOKEN){
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

// read raw input
$raw = file_get_contents('php://input');
if(!$raw){
    http_response_code(400);
    echo json_encode(['error' => 'no payload']);
    exit;
}

$payload = json_decode($raw, true);
if(json_last_error() !== JSON_ERROR_NONE){
    http_response_code(400);
    echo json_encode(['error' => 'invalid json']);
    exit;
}

// ensure data dir exists
if(!is_dir($DATA_DIR)){
    @mkdir($DATA_DIR, 0755, true);
}

// read existing file
$data = ['total'=>0, 'actions'=>[], 'logs'=>[]];
if(file_exists($DATA_FILE)){
    $txt = @file_get_contents($DATA_FILE);
    if($txt){
        $tmp = json_decode($txt, true);
        if(json_last_error() === JSON_ERROR_NONE && is_array($tmp)){
            $data = $tmp;
        }
    }
}

// normalize incoming
$action = isset($payload['action']) ? preg_replace('/[^a-z0-9_\\-]/i', '', $payload['action']) : 'unknown';
$meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];
$ts = isset($payload['ts']) ? $payload['ts'] : date('c');

// update aggregates
$data['total'] = (isset($data['total']) ? (int)$data['total'] : 0) + 1;
if(!isset($data['actions'][$action])) $data['actions'][$action] = 0;
$data['actions'][$action]++;

// append log (bounded)
$logEntry = [
    'ts' => $ts,
    'action' => $action,
    'meta' => $meta,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200)
];
if(!isset($data['logs']) || !is_array($data['logs'])) $data['logs'] = [];
$data['logs'][] = $logEntry;
if(count($data['logs']) > $MAX_LOGS){
    $data['logs'] = array_slice($data['logs'], -$MAX_LOGS);
}

// safe write: write to temp and rename
$tmpFile = $DATA_FILE . '.tmp';
if(@file_put_contents($tmpFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))){
    @rename($tmpFile, $DATA_FILE);
    echo json_encode(['ok' => true]);
    exit;
} else {
    http_response_code(500);
    echo json_encode(['error' => 'write_failed']);
    exit;
}