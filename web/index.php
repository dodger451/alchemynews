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
/*
$dbopts["user"]='postgres';
$dbopts["pass"]='postgres';
$dbopts["path"]='alchemynews';
$dbopts["host"]='localhost';
$dbopts["port"]='5432';
*/

$app->register(
    new Herrera\Pdo\PdoServiceProvider(),
    array(
        'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"], '/').';host='.$dbopts["host"],
        'pdo.port' => $dbopts["port"],
        'pdo.username' => $dbopts["user"],
        'pdo.password' => $dbopts["pass"]
    )
);
// Register NewsDb Service
$app->register(new Latotzky\Alchemynews\NewsDbServiceProvider(), array());

// Register the ALCHEMYAPI service
$apikey = getenv('ALCHEMYAPI_KEY');
$app->register(new Latotzky\Alchemynews\AlchemyApiNewsServiceProvider(), array(
    'alchemynews.apikey' => $apikey,
));


// Our web handlers
$app->get('/', function () use ($app) {
    $docs = $app['newsdb']->getLatest();

    return $app['twig']->render('results.twig', array('docs' => $docs));
});


$app->get('/apiread/', function () use ($app) {
    // get latest api results
    $days = 2;
    $end = time();
    if (!empty($_GET['end']) && false !== strtotime($_GET['end'])) {
        $end = strtotime($_GET['end']);
    }
    $start = $end - 60*60*24*$days;
    $company = urlencode('Rocket Internet');

    $response = $app['alchemyapinews']->getCompanyNews($start, $end, $company);

    if (!is_object($response) || 'OK' != $response->status) {
        $app['monolog']->addNotice('Response not ok: ' . print_r($response, true));
        return ('something is wrong: ' .$response. print_r($response, true)) ;
    }
    $newdocs = $response->result->docs;

    $app['monolog']->addDebug("Response status ok, results: " . count($newdocs));
    echo("Response status ok, results: " . count($newdocs));



    foreach ($newdocs as $doc) {
        $app['newsdb']->insertIfNotExists($doc);
        $app['monolog']->addDebug(
            $doc->id  . " - " .$doc->source->enriched->url->docSentiment->type
            . ': ' . $doc->source->enriched->url->title
        );
    }

    //return '<pre>' . print_r($docs, true);
    return $app['twig']->render('results.twig', array(
        'docs' => $newdocs
    ));

});

$app->run();
