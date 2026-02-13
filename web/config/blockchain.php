<?php
// blockchain.php (FIXED)
// Secure PHP → Node → Smart Contract bridge (owner-only writes)

ob_start();
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void {
    if (ob_get_length()) { ob_clean(); }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function is_bytes32(string $hex): bool {
    return (bool) preg_match('/^0x[0-9a-fA-F]{64}$/', $hex);
}

function clean_label(string $s, int $maxLen = 50): string {
    $s = trim($s);
    $s = strip_tags($s);
    $s = preg_replace('/[\x00-\x1F]/', '', $s);
    $s = preg_replace('/[^a-zA-Z0-9 _\-.]/', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    if (mb_strlen($s) > $maxLen) $s = mb_substr($s, 0, $maxLen);
    return $s;
}

try {
    // ---- 1) Read inputs (JSON or form POST) ----
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
        $input = $_POST;
    }

    $reportId = isset($input['report_id']) ? (int)$input['report_id'] : null;
    $dataHash = isset($input['data_hash']) ? trim((string)$input['data_hash']) : '';
    $category = isset($input['category']) ? clean_label((string)$input['category']) : '';
    $modelVersion = isset($input['model_version']) ? clean_label((string)$input['model_version']) : 'v1';

    // ---- 2) Validate ----
    if ($reportId === null || $reportId < 0) {
        respond(400, ["ok" => false, "error" => "report_id must be non-negative integer"]);
    }

    if (!is_bytes32($dataHash)) {
        respond(400, ["ok" => false, "error" => "data_hash must be bytes32 (0x + 64 hex chars)"]);
    }

    if ($category === '') {
        respond(400, ["ok" => false, "error" => "category required"]);
    }

    if ($modelVersion === '') $modelVersion = 'v1';

    // ---- 3) Load private key from Apache env ----
    $ownerPk = getenv("BLOCKCHAIN_OWNER_PK");
    if (!$ownerPk) {
        respond(500, ["ok" => false, "error" => "Missing BLOCKCHAIN_OWNER_PK"]);
    }

    $ownerPk = trim($ownerPk);
    if (!preg_match('/^0x[0-9a-fA-F]{64}$/', $ownerPk)) {
        respond(500, ["ok" => false, "error" => "Invalid BLOCKCHAIN_OWNER_PK"]);
    }

    // ---- 4) Paths ----
    $workDir = "D:/Mark Codes/AI-BlockChain/AI-Based-CommunityIssue-Classification-w-BlockChain-Verification/blockchain";
    $verifyPath = $workDir . "/verify.mjs";

    if (!is_dir($workDir)) {
        respond(500, ["ok" => false, "error" => "Blockchain folder not found", "path" => $workDir]);
    }

    if (!file_exists($verifyPath)) {
        respond(500, ["ok" => false, "error" => "verify.mjs not found", "path" => $verifyPath]);
    }

    // ---- 5) Execute Node (FINAL WORKING VERSION) ----
    // Use pushd/popd + set "VAR=..." + quoted paths. No double-double-quotes.
    $cmd = 'cmd.exe /V:ON /C '
         . '"pushd "' . $workDir . '"'
         . ' && set "PRIVATE_KEY=' . $ownerPk . '"'
         . ' && node "' . $verifyPath . '"'
         . ' ' . $reportId
         . ' ' . $dataHash
         . ' "' . $category . '"'
         . ' "' . $modelVersion . '"'
         . ' && popd"';

    $output = [];
    $exitCode = 0;
    exec($cmd . " 2>&1", $output, $exitCode);

    $outText = trim(implode("\n", $output));

    if ($exitCode !== 0) {
        respond(502, [
            "ok" => false,
            "error" => "Blockchain verification failed",
            "exit_code" => $exitCode,
            "details" => $outText,
            "debug_cmd" => $cmd
        ]);
    }

    // ---- 6) Extract tx hash ----
    $txHash = null;
    if (preg_match('/tx hash:\s*(0x[a-fA-F0-9]{64})/i', $outText, $m)) {
        $txHash = $m[1];
    } elseif (preg_match('/0x[a-fA-F0-9]{64}/', $outText, $m)) {
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
