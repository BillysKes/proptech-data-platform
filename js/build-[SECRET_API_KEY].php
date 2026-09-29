<?php

$src_dir = __DIR__ . '/src/';
$output_file = __DIR__ . '/child-custom.js';

if (!is_dir($src_dir)) {
    exit('Error: The /src/ directory does not exist.');
}

$files = glob($src_dir . '*.js');

sort($files);

$combined_js = "/* ==========================================================================\n";
$combined_js .= "   AUTO-GENERATED FILE. DO NOT EDIT DIRECTLY.\n";
$combined_js .= "   To make changes, edit the individual files in the /js/src/ directory,\n";
$combined_js .= "   then run your secret build script to recompile this file.\n";
$combined_js .= "   ========================================================================== */\n\n";

foreach ($files as $file) {
    $combined_js .= "/* --- Module: " . basename($file) . " --- */\n";
    $combined_js .= trim(file_get_contents($file)) . "\n\n";
}

file_put_contents($output_file, $combined_js);

echo "<h2>Build Successful!</h2>";
echo "<p>Combined <strong>" . count($files) . "</strong> files into <code>child-custom.js</code>.</p>";
echo "<ul>";
foreach ($files as $file) {
    echo "<li>" . basename($file) . "</li>";
}
echo "</ul>";
?>