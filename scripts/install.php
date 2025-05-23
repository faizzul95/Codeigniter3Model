<?php

// Create directories
@mkdir('application/language/malay', 0755, true);

// Copy files
copy('vendor/onlyphp/codeigniter3-model/src/MY_Model.php', 'application/core/MY_Model.php');
copy('vendor/onlyphp/codeigniter3-model/src/language/malay/form_validation_lang.php', 'application/language/malay/form_validation_lang.php');

// Update composer.json
$jsonFile = 'composer.json';
if (file_exists($jsonFile)) {
    $json = json_decode(file_get_contents($jsonFile), true);
    $json['autoload']['psr-4']['App\\'] = 'application/';
    $json['config']['process-timeout'] = 3000;
    file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// Regenerate autoload files
exec('composer dump-autoload');

echo "Installation script executed successfully.\n";