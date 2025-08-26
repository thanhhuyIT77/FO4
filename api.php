<?php
// Simple PHP API for saving/listing results in MySQL (XAMPP)
// NOTE: For GitHub Pages, expose this API publicly (e.g., ngrok/Cloudflare Tunnel)

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'scoreboard';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbPort = getenv('DB_PORT') ?: '3307';

try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'error' => 'DB connect failed', 'detail' => $e->getMessage() ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  home_name VARCHAR(100) NOT NULL,
  home_score INT NOT NULL,
  away_name VARCHAR(100) NOT NULL,
  away_score INT NOT NULL,
  saved_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$action = $_GET['action'] ?? '';

if ($action === 'save_result') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }
    $homeName = trim((string)($payload['home']['name'] ?? ''));
    $homeScore = (int)($payload['home']['score'] ?? 0);
    $awayName = trim((string)($payload['away']['name'] ?? ''));
    $awayScore = (int)($payload['away']['score'] ?? 0);
    $savedAt = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO results (home_name, home_score, away_name, away_score, saved_at) VALUES (?,?,?,?,?)');
    $stmt->execute([$homeName, $homeScore, $awayName, $awayScore, $savedAt]);
    echo json_encode(['status'=>'ok']);
    exit;
}

if ($action === 'list_history') {
    $stmt = $pdo->query('SELECT home_name, home_score, away_name, away_score, saved_at FROM results ORDER BY id DESC LIMIT 100');
    $rows = $stmt->fetchAll();
    $data = array_map(function($r){
        return [
            'home' => [ 'name'=>$r['home_name'], 'score'=>(int)$r['home_score'] ],
            'away' => [ 'name'=>$r['away_name'], 'score'=>(int)$r['away_score'] ],
            'savedAt' => $r['saved_at'],
        ];
    }, $rows);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error'=>'Unknown action']);


