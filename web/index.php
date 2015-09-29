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
$apikey = getenv('ALCHEMYAPI_KEY');
$dbopts = parse_url(getenv('DATABASE_URL'));
if (empty($dbopts['path'])) {
    $dbopts["user"]='postgres'; $dbopts["pass"]='postgres';$dbopts["path"]='alchemynews';$dbopts["host"]='localhost';$dbopts["port"]='5432';

}
// SERVICES
// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../src/views',
));

// register PDO connection
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
    $concepts = extractConcepts($docs, 20);

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
 * @param $limit
 * @return array
 */
function extractConcepts(array $docs, $sort = 'count', $limit = 20)
{
    $concepts = array();
    foreach ($docs as $doc) {
        if (!empty($doc->source->enriched->url->concepts)) {
            foreach ($doc->source->enriched->url->concepts as $concept) {
                if (!empty($concept->knowledgeGraph->typeHierarchy)) {
                    $id = $concept->knowledgeGraph->typeHierarchy;
                    if (!isset($concepts[$id])) {
                        $concepts[$id]['count'] = 0;
                        $concepts[$id]['relevance'] = 0;
                        $concepts[$id]['text'] = $concept->text;
                        $concepts[$id]['typeHierarchy'] = $concept->knowledgeGraph->typeHierarchy;
                    }
                    $concepts[$id]['relevance'] += $concept->relevance;
                    $concepts[$id]['count']++;;
                }
            }
        }
    }
    usort($concepts, function ($a, $b) use ($sort){
        if ($a[$sort] == $b[$sort]) {
            if ($a['relevance'] == $b['relevance']) {
                return 0;
            }
            return ($a['relevance'] > $b['relevance']) ? -1 : 1;
        }
        return ($a[$sort] > $b[$sort]) ? -1 : 1;
    });

    $concepts = array_slice($concepts, 0, $limit);
    return $concepts;
}

// RUN APP
$app->run();
