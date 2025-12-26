<?php

function cleanJsonControlCharacters(string $json): string
{
    // Remove description fields which contain control characters
    $json = preg_replace('/"description":\s*"[^"]*(?:\\.[^"]*)*",?\s*/s', '', $json) ?? $json;

    // Clean up trailing commas
    $json = preg_replace('/,(\s*[}\]])/', '$1', $json) ?? $json;

    return $json;
}

$json = file_get_contents("datas/currency.json");
echo "Original file size: " . strlen($json) . " bytes\n";

$cleaned = cleanJsonControlCharacters($json);
echo "Cleaned file size: " . strlen($cleaned) . " bytes\n";

$data = json_decode($cleaned, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error: " . json_last_error_msg() . "\n";
    echo "Error code: " . json_last_error() . "\n";
} else {
    echo "SUCCESS! JSON parsed correctly\n";
    echo "Characters count: " . count($data["characters"]) . "\n";
    if (isset($data["characters"][0])) {
        echo "First character: " . $data["characters"][0]["name"] . "\n";
        echo "Currencies count: " . count($data["characters"][0]["currencies"] ?? []) . "\n";
        if (isset($data["characters"][0]["currencies"][0])) {
            echo "First currency: " . $data["characters"][0]["currencies"][0]["name"] . "\n";
        }
    }
}
