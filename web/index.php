<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
// CONFIGURATION
$app['entityname'] = 'Rocket Internet';
$app['entityTypeHierarchy'] = '/industries/media/internet/rocket internet';
$app['blacklist'] = [
    '%crunchbase%',
    '%linkedin%',
    '%techmoran.com%',
    '%economictimes.indiatimes.com%',
    '%www.scoop.it%',
    '%techmeme.com%',
];
$app['debug'] = true;

// SERVICES
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

//$dbopts["user"]='postgres'; $dbopts["pass"]='postgres';$dbopts["path"]='alchemynews';$dbopts["host"]='localhost';$dbopts["port"]='5432';

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
$app->register(new Latotzky\Alchemynews\NewsDbServiceProvider(), array(
    'entityTypeHierarchy' => $app['entityTypeHierarchy']
));
// Register the ALCHEMYAPI service
$apikey = getenv('ALCHEMYAPI_KEY');
$app->register(new Latotzky\Alchemynews\AlchemyApiNewsServiceProvider(), array(
    'alchemyapinews.apikey' => $apikey,
));

// ROUTES
/**
 * GET '/'
 * Shows latest news.
 */
$app->get('/', function () use ($app) {
    $docs = $app['newsdb']->getLatest();
    $concepts = extractConcepts($docs);

    return $app['twig']->render(
        'results.twig',
        array('docs' => $docs, 'concepts' => $concepts, 'entityname' => $app['entityname'])
    );
});

/**
 * GET '/apiread/'
 * Reads news from alchemyapi to local db, removes blacklisted, PRint number of articles received from alchemyAPI.
 */
$app->get('/apiread/', function () use ($app) {
    // get latest api results
    $company = urlencode($app['entityname']);
    $days = 2;

    $end = time();
    if (!empty($_GET['end']) && false !== strtotime($_GET['end'])) {
        $end = strtotime($_GET['end']);
    }
    $start = $end - 60*60*24*$days;
    $response = $app['alchemyapinews']->getCompanyNews($start, $end, $company);

    if (!is_object($response) || 'OK' != $response->status) {
        echo ('Response not ok: ' . print_r($response, true));
        return ('something is wrong: ' .$response. print_r($response, true)) ;
    }
    $newdocs = $response->result->docs;

    foreach ($newdocs as $doc) {
        $app['newsdb']->insertIfNotExists($doc);
    }
    $app['newsdb']->deleteBlacklisted($app['blacklist']);

    return count($newdocs);

});

// HELPER
/**
 * @param array $docs
 * @param string $sort
 * @return array
 */
function extractConcepts(array $docs, $sort = 'count')
{
    $concepts = array();
    foreach ($docs as $doc) {
        if (!empty($doc->source->enriched->url->concepts)) {
            foreach ($doc->source->enriched->url->concepts as $concept) {
                if (!isset($concepts[$concept->knowledgeGraph->typeHierarchy])) {
                    $concepts[$concept->knowledgeGraph->typeHierarchy]['count'] = 0;
                    $concepts[$concept->knowledgeGraph->typeHierarchy]['relevance'] = 0;
                    $concepts[$concept->knowledgeGraph->typeHierarchy]['text'] = $concept->text;
                    $concepts[$concept->knowledgeGraph->typeHierarchy]['typeHierarchy'] =
                        $concept->knowledgeGraph->typeHierarchy;
                }
                $concepts[$concept->knowledgeGraph->typeHierarchy]['relevance'] += $concept->relevance;
                $concepts[$concept->knowledgeGraph->typeHierarchy]['count']++;;
            }
        }
    }
    usort($concepts, function ($a, $b) use ($sort){
        if ($a[$sort] == $b[$sort]) {
            return 0;
        }
        return ($a[$sort] > $b[$sort]) ? -1 : 1;
    });

    $concepts = array_slice($concepts, 0, 20);
    return $concepts;
}

// RUN APP
$app->run();
