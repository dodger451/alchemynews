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

// Our web handlers

$app->get('/', function () use ($app) {
    $st = $app['pdo']->prepare('SELECT * FROM news ORDER BY original_timestamp DESC');
    $st->execute();

    $docs = array();
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $docs[] = json_decode($row['doc']);
    }

    //return '<pre>' . print_r($docs, true);
    return $app['twig']->render('results.twig', array(
        'docs' => $docs
    ));
});


$app->get('/apiread/', function () use ($app) {
    // get latest api results
    $start = time() - 60*60*24*2;
    $end = time();

    $apikey = getenv('ALCHEMYAPI_KEY');
    $company = urlencode('Rocket Internet');

    $src = '../data/api/response_fixture.json';
    /*$src = "https://access.alchemyapi.com/calls/data/GetNews?apikey=$apikey"
        ."&return=enriched.url.title,enriched.url.url,enriched.url.publicationDate,enriched.url.docSentiment,enriched.url.concepts"
        ."&start=$start&end=$end"
        ."&q.enriched.url.entities.entity="
        ."|text=$company,type=company"
        ."|&count=50&outputMode=json";
    */$response = json_decode(file_get_contents($src));
    if (!is_object($response) || 'OK' != $response->status) {
        $app['monolog']->addNotice('Response not ok: ' . print_r($response, true));
        var_dump($response);
        return ('something is wrong: ' .$response. print_r($response, true)) ;
    }
    $newdocs = $response->result->docs;
    //add latest results to db


    $app['monolog']->addDebug("Response status ok, results: " . count($newdocs));
    echo("Response status ok, results: " . count($newdocs));

    foreach ($newdocs as $doc) {
        $st = $app['pdo']->prepare(<<<SQL
INSERT INTO news
    (alchemyid, original_timestamp, sentiment, url, title, doc)
SELECT :alchemyid, :original_timestamp, :sentiment, :url, :title, :doc
WHERE
    NOT EXISTS (
        SELECT id FROM news WHERE alchemyid = :alchemyid
        );
SQL
        );
        $data =  [
            'alchemyid' => $doc->id,
            'original_timestamp' => date('Y-m-d G:i:s', $doc->timestamp),
            'sentiment' => $doc->source->enriched->url->docSentiment->type,
            'url' => $doc->source->enriched->url->url,
            'title' => $doc->source->enriched->url->title,
            'doc' => json_encode($doc),
        ];
        $res = $st->execute($data);
        $app['monolog']->addDebug($doc->id  . " - " .$doc->source->enriched->url->docSentiment->type  . ': ' . $doc->source->enriched->url->title);
    }

    //return '<pre>' . print_r($docs, true);
    return $app['twig']->render('results.twig', array(
        'docs' => $newdocs
    ));

});

$app->run();
