<?php
// web/config/blockchain.php

function make_report_hash_bytes32(int $reportId, string $title, string $description, string $category, string $modelVersion, string $createdAt): string {
    $canonical = implode("|", [
        $reportId,
        $title,
        $description,
        $category,
        $modelVersion,
        $createdAt,
    ]);

    return "0x" . hash('sha256', $canonical); // bytes32
}

function run_blockchain_verify(int $reportId, string $dataHashBytes32, string $category, string $modelVersion): array {
    // ✅ CHANGE THIS to your actual blockchain folder path
    $blockchainDir = 'D:\\Mark Codes\\AI-BlockChain\\AI-Based-CommunityIssue-Classification-w-BlockChain-Verification\\blockchain';

    $cmd = 'cd /d ' . escapeshellarg($blockchainDir)
         . ' && node verify.mjs '
         . escapeshellarg((string)$reportId) . ' '
         . escapeshellarg($dataHashBytes32) . ' '
         . escapeshellarg($category) . ' '
         . escapeshellarg($modelVersion);

    $output = shell_exec($cmd);

    if ($output === null) {
        return ["ok" => false, "error" => "shell_exec failed or disabled", "raw" => ""];
    }

    if (preg_match('/tx hash:\s*(0x[a-fA-F0-9]{64})/', $output, $m)) {
        return ["ok" => true, "tx_hash" => $m[1], "raw" => $output];
    }

    return ["ok" => false, "error" => "tx hash not found in node output", "raw" => $output];
}
