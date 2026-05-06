<?php
$installed = json_decode(file_get_contents('vendor/composer/installed.json'), true);
$require = [];

foreach ($installed['packages'] as $package) {
    $require[$package['name']] = $package['version'];
}

$composerJson = [
    "name" => "the-jat/TheAnalytics",
    "description" => "Link Tracking Project",
    "require" => $require,
    "autoload" => [
        "psr-4" => [
            "Altum\\" => "app/"
        ]
    ],
    "minimum-stability" => "dev",
    "prefer-stable" => true
];

file_put_contents('composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));


// How to Run
/*
php generate_composer.php
composer install

It might ask for the personal-access-token for the github in between, generate one and provide it for the public repository access only.
*/