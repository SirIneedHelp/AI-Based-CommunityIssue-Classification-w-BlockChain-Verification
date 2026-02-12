<?php
// blockchain.php
// Purpose: Server-side bridge to write verification proofs to blockchain safely.
// Security goals:
// - Only server can sign transactions (PRIVATE_KEY comes from env, not user input)
// - Validate inputs (reportId, bytes32 hash, category/modelVersion)
// - Use escapeshellarg to prevent command injection
// - Return consistent JSON responses

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function is_bytes32(string $hex): bool {
    return (bool) preg_match('/^0x[0-9a-fA-F]{64}$/', $hex);
}

function clean_label(string $s, int $maxLen = 40): string {
    // Allow letters, numbers, spaces, underscore, dash, dot
    $s = trim($s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    $s = preg_replace('/[^a-zA-Z0-9 _\-.]/', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    if (mb_strlen($s) > $maxLen) $s = mb_substr($s, 0, $maxLen);
    return $s;
}

try {
    // ---- 1) Read inputs (support POST form or JSON body) ----
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $input = [];

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            respond(400, ["ok" => false, "error" => "Invalid JSON body"]);
        }
        $input = $json;
    } else {
        // form-data / x-www-form-urlencoded
        $input = $_POST;
    }

    $reportId = isset($input['report_id']) ? (int)$input['report_id'] : null;
    $dataHash = isset($input['data_hash']) ? trim((string)$input['data_hash']) : '';
    $category = isset($input['category']) ? clean_label((string)$input['category'], 50) : '';
    $modelVersion = isset($input['model_version']) ? clean_label((string)$input['model_version'], 50) : 'v1';

    // ---- 2) Validate inputs ----
    if ($reportId === null || $reportId < 0) {
        respond(400, ["ok" => false, "error" => "report_id must be a non-negative integer"]);
    }

    if (!is_bytes32($dataHash)) {
        respond(400, ["ok" => false, "error" => "data_hash must be bytes32 (0x + 64 hex chars)"]);
    }

    if ($category === '') {
        respond(400, ["ok" => false, "error" => "category is required"]);
    }

    if ($modelVersion === '') {
        $modelVersion = 'v1';
    }

    // ---- 3) Load server-only private key (Access Control) ----
    $ownerPk = getenv("BLOCKCHAIN_OWNER_PK");
    if (!$ownerPk) {
        respond(500, [
            "ok" => false,
            "error" => "Missing BLOCKCHAIN_OWNER_PK environment variable (server wallet key not configured)"
        ]);
    }

    // Basic sanity check for private key format (0x + 64 hex)
    $ownerPk = trim($ownerPk);
    if (!preg_match('/^0x[0-9a-fA-F]{64}$/', $ownerPk)) {
        respond(500, [
            "ok" => false,
            "error" => "BLOCKCHAIN_OWNER_PK is not a valid 0x + 64 hex private key"
        ]);
    }

    // ---- 4) Determine blockchain scripts directory & verify.mjs path ----
    // Adjust this if your verify.mjs is in a different folder.
    $projectDir = realpath(__DIR__); // current folder
    $verifyPath = $projectDir . DIRECTORY_SEPARATOR . "verify.mjs";

    if (!file_exists($verifyPath)) {
        respond(500, ["ok" => false, "error" => "verify.mjs not found at: " . $verifyPath]);
    }

    // ---- 5) Execute Node script safely ----
    // We pass PRIVATE_KEY through environment for this command only.
    // Use cmd.exe /C so it works on Windows (XAMPP) too.
    $envPrefix = "set PRIVATE_KEY=" . $ownerPk . " && ";

    $cmd = $envPrefix
        . "node " . escapeshellarg($verifyPath) . " "
        . escapeshellarg((string)$reportId) . " "
        . escapeshellarg($dataHash) . " "
        . escapeshellarg($category) . " "
        . escapeshellarg($modelVersion);

    // Run inside cmd.exe on Windows; on Linux/Mac, you can run $cmd directly
    // Since you're likely on Windows (MINGW/XAMPP), this is safest:
    $fullCmd = "cmd.exe /C " . escapeshellarg($cmd);

    $output = [];
    $exitCode = 0;
    exec($fullCmd . " 2>&1", $output, $exitCode);

    $outText = trim(implode("\n", $output));

    if ($exitCode !== 0) {
        // Common: "Not authorized" revert if wrong key (means access control is working)
        respond(502, [
            "ok" => false,
            "error" => "Blockchain verification failed",
            "exit_code" => $exitCode,
            "details" => $outText
        ]);
    }

    // ---- 6) Try to extract tx hash from output (optional) ----
    $txHash = null;
    if (preg_match('/tx hash:\s*(0x[a-fA-F0-9]{64})/i', $outText, $m)) {
        $txHash = $m[1];
    } elseif (preg_match('/0x[a-fA-F0-9]{64}/', $outText, $m)) {
        // fallback
        $txHash = $m[0];
    }

    respond(200, [
        "ok" => true,
        "report_id" => $reportId,
        "data_hash" => $dataHash,
        "category" => $category,
        "model_version" => $modelVersion,
        "tx_hash" => $txHash,
        "raw" => $outText
    ]);

} catch (Throwable $e) {
    respond(500, ["ok" => false, "error" => "Server error", "details" => $e->getMessage()]);
}
