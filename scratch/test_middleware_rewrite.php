<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Http\Request;

$server = [
    'REQUEST_URI' => '/api/users?foo=bar',
    'SCRIPT_NAME' => '/index.php',
];

$request = new Request([], [], [], [], [], $server);

echo "Original URI: " . $request->getRequestUri() . "\n";
echo "Original PathInfo: " . $request->getPathInfo() . "\n";

$uri = $request->getRequestUri();
if (str_starts_with($uri, '/api/') && !str_starts_with($uri, '/api/v1/')) {
    $newUri = '/api/v1/' . substr($uri, 5);
    $request->server->set('REQUEST_URI', $newUri);
    // Reinitialize to force path info recalculation
    $request->initialize(
        $request->query->all(),
        $request->request->all(),
        $request->attributes->all(),
        $request->cookies->all(),
        $request->files->all(),
        $request->server->all(),
        $request->getContent()
    );
}

echo "New URI: " . $request->getRequestUri() . "\n";
echo "New PathInfo: " . $request->getPathInfo() . "\n";
echo "New Query: " . json_encode($request->query->all()) . "\n";
