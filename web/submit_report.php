<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/config/blockchain.php";
require_once __DIR__ . "/config/ai.php";


// TEMP: fake AI result (we’ll replace this later)
function fake_ai_classify($text) {
    return [
        "category" => "Garbage",
        "confidence" => 0.92,
        "model_version" => "v1"
    ];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = $_POST["title"] ?? "";
    $description = $_POST["description"] ?? "";

    if (!$title || !$description) {
        die("Missing fields");
    }

    // 1️⃣ Save report
    $stmt = $conn->prepare(
        "INSERT INTO reports (title, description) VALUES (?, ?)"
    );
    $stmt->bind_param("ss", $title, $description);
    $stmt->execute();

    $reportId = $stmt->insert_id;
    $createdAt = date("Y-m-d H:i:s");

    $aiRes = ai_classify_text($title . " " . $description);
if (!$aiRes["ok"]) {
    die("AI error: " . $aiRes["error"]);
}
$ai = $aiRes["data"];

    $stmt = $conn->prepare(
        "INSERT INTO ai_results (report_id, category, confidence, model_version)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "isds",
        $reportId,
        $ai["category"],
        $ai["confidence"],
        $ai["model_version"]
    );
    $stmt->execute();

    // 3️⃣ Create blockchain hash
    $dataHash = make_report_hash_bytes32(
        $reportId,
        $title,
        $description,
        $ai["category"],
        $ai["model_version"],
        $createdAt
    );

    // 4️⃣ Send to blockchain
    $bc = run_blockchain_verify(
        $reportId,
        $dataHash,
        $ai["category"],
        $ai["model_version"]
    );

    if (!$bc["ok"]) {
        die("Blockchain error: " . $bc["error"]);
    }

    // 5️⃣ Save blockchain verification
    $stmt = $conn->prepare(
        "INSERT INTO chain_verifications (report_id, data_hash, tx_hash)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iss", $reportId, $dataHash, $bc["tx_hash"]);
    $stmt->execute();

    // 6️⃣ Update report status
    $conn->query(
        "UPDATE reports SET status='verified' WHERE id=$reportId"
    );

    echo "✅ Report submitted and verified on blockchain.<br>";
    echo "TX HASH: " . $bc["tx_hash"];
}
