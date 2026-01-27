<?php
require_once __DIR__ . '/config/blockchain.php';

// sample “report”
$reportId = 999;
$title = "Test Title";
$description = "Test Description";
$category = "Garbage";
$modelVersion = "v1";
$createdAt = date("Y-m-d H:i:s");

$dataHash = make_report_hash_bytes32($reportId, $title, $description, $category, $modelVersion, $createdAt);

$result = run_blockchain_verify($reportId, $dataHash, $category, $modelVersion);

header("Content-Type: text/plain");

echo "Hash: $dataHash\n\n";

if ($result["ok"]) {
    echo "✅ TX HASH: " . $result["tx_hash"] . "\n\n";
    echo $result["raw"];
} else {
    echo "❌ ERROR: " . $result["error"] . "\n\n";
    echo "RAW OUTPUT:\n" . $result["raw"];
}
