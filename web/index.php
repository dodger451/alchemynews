<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../src/views',
));

// add a PDO connection
$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(
    new Herrera\Pdo\PdoServiceProvider(),
    array(
        'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"], '/').';host='.$dbopts["host"],
        'pdo.port' => $dbopts["port"],
        'pdo.username' => $dbopts["user"],
        'pdo.password' => $dbopts["pass"]
    )
);

// Our web handlers

$app->get('/', function () use ($app) {
    $app['monolog']->addDebug('logging output.');
    return $app['twig']->render('index.twig');
});

$app->get('/cowsay', function () use ($app) {
    $app['monolog']->addDebug('cowsay');
    return "<pre>".\League\Cowsayphp\Cow::say("Cool beans")."</pre>";
});
/*
$app->get('/db/', function () use ($app) {

    $st = $app['pdo']->prepare('SELECT name FROM test_table');
    $st->execute();

    $names = array();
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $app['monolog']->addDebug('Row ' . $row['name']);
        $names[] = $row;
    }

    return $app['twig']->render('database.twig', array(
        'names' => $names
    ));
});*/

$app->get('/demoapiread/', function () use ($app) {
    $src = '../data/api/response_fixture.json';
    $response = json_decode(file_get_contents($src));
    if (!isset($response['status']) || 'ok' != $response['status']
        || !isset($response['result'], $response['result']['docs']) || !is_array($response['result']['docs'])) {
        $app['monolog']->addNotice('Response status not ok: ' . empty($response['status']) ? '' : $response['status']);
        return '';
    }
    $docs = $response['result']['docs'];
    $app['monolog']->addDebug("Response status ok, results: " . count($docs));
    foreach ($docs as $doc) {
        $app['monolog']->addDebug($doc['id'] . " - " .$doc['source']['enriched']['url']['docSentiment']['type'] . ': ' . $doc['source']['enriched']['url']['title']);
    }
    return $app['twig']->render('results.twig', array(
        'docs' => $docs
    ));
    return '<pre>' . print_r($response, true);
});

$app->run();
