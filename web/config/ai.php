<?php
function ai_classify_text(string $text): array {
    $url = "http://127.0.0.1:8000/classify";

    $payload = json_encode(["text" => $text]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ["ok" => false, "error" => $err ?: "cURL failed"];
    }
    if ($status < 200 || $status >= 300) {
        return ["ok" => false, "error" => "AI HTTP $status: $response"];
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data["category"])) {
        return ["ok" => false, "error" => "Bad AI response: $response"];
    }

    return ["ok" => true, "data" => $data];
}
