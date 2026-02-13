<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/ai.php";

// ✅ Don't include the endpoint directly:
// require_once __DIR__ . "/config/blockchain.php";

function make_report_hash_bytes32($reportId, $title, $description, $category, $modelVersion, $createdAt) {
    // Deterministic hash (bytes32): sha256 -> 0x + 64 hex
    $payload = implode("|", [
        (string)$reportId,
        (string)$title,
        (string)$description,
        (string)$category,
        (string)$modelVersion,
        (string)$createdAt
    ]);

    return "0x" . hash("sha256", $payload);
}

function call_blockchain_endpoint($reportId, $dataHash, $category, $modelVersion) {
    $url = "http://localhost/community-system/config/blockchain.php";

    $payload = json_encode([
        "report_id" => (int)$reportId,
        "data_hash" => (string)$dataHash,
        "category" => (string)$category,
        "model_version" => (string)$modelVersion
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ["ok" => false, "error" => "cURL error: " . $err];
    }

    // Sometimes you had a weird prefix before JSON; try to extract JSON safely
    $jsonStart = strpos($resp, "{");
    if ($jsonStart !== false) {
        $resp = substr($resp, $jsonStart);
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        return ["ok" => false, "error" => "Invalid JSON from blockchain.php", "raw" => $resp, "http_code" => $code];
    }

    if (($data["ok"] ?? false) !== true) {
        return ["ok" => false, "error" => $data["error"] ?? "Blockchain failed", "details" => $data["details"] ?? null, "raw" => $data];
    }

    return ["ok" => true, "data" => $data];
}

// -----------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "This endpoint expects POST (submit from the form).";
    exit;
}

$title = $_POST["title"] ?? "";
$description = $_POST["description"] ?? "";

if (!$title || !$description) {
    die("Missing fields");
}

// 1) Save report
$stmt = $conn->prepare("INSERT INTO reports (title, description) VALUES (?, ?)");
$stmt->bind_param("ss", $title, $description);
$stmt->execute();

$reportId = (int)$conn->insert_id;
$createdAt = date("Y-m-d H:i:s");

// 2) AI classify
$aiRes = ai_classify_text($title . " " . $description);
if (!($aiRes["ok"] ?? false)) {
    die("AI error: " . ($aiRes["error"] ?? "Unknown"));
}
$ai = $aiRes["data"];

// Save AI result
$stmt = $conn->prepare(
    "INSERT INTO ai_results (report_id, category, confidence, model_version) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("isds", $reportId, $ai["category"], $ai["confidence"], $ai["model_version"]);
$stmt->execute();

// 3) Create blockchain hash
$dataHash = make_report_hash_bytes32(
    $reportId, $title, $description, $ai["category"], $ai["model_version"], $createdAt
);

// 4) Send to blockchain (HTTP call)
$bcRes = call_blockchain_endpoint($reportId, $dataHash, $ai["category"], $ai["model_version"]);
if (!($bcRes["ok"] ?? false)) {
    die("Blockchain error: " . ($bcRes["error"] ?? "Unknown"));
}

$bc = $bcRes["data"];
$txHash = $bc["tx_hash"] ?? null;

if (!$txHash) {
    die("Blockchain error: Missing tx_hash in response");
}

// 5) Save blockchain verification
$stmt = $conn->prepare(
    "INSERT INTO chain_verifications (report_id, data_hash, tx_hash) VALUES (?, ?, ?)"
);
$stmt->bind_param("iss", $reportId, $dataHash, $txHash);
$stmt->execute();

// 6) Update report status
$stmt = $conn->prepare("UPDATE reports SET status='verified' WHERE id=?");
$stmt->bind_param("i", $reportId);
$stmt->execute();

echo "✅ Report submitted and verified on blockchain.<br>";
echo "Report ID: " . $reportId . "<br>";
echo "TX HASH: " . htmlspecialchars($txHash);
