<?php
/**
 * AlchemyAPI Client
 * @author david
 */
namespace Latotzky\Alchemynews;

/**
 * AlchemyNewsService
 */
class AlchemyApiNewsService
{

    protected $apikey = '';
    protected $options = array();
    /**
     * AlchemyNewsService constructor.
     * @param string $apikey
     * @param array $options
     */
    public function __construct($apikey, $options = [])
    {
        $this->apikey = $apikey;
        $this->options = $options;
    }

    /**
     * @param int $start timestamp
     * @param int $end timestamp
     * @param string $company
     * @return mixed
     */
    function getCompanyNews($start, $end, $company)
    {
        //$src = '../data/api/response_fixture2.json';return json_decode(file_get_contents($src));
        $src = "https://access.alchemyapi.com/calls/data/GetNews?apikey=$this->apikey"
            . "&return=enriched.url.title"
            . ",enriched.url.url"
            . ",enriched.url.publicationDate"
            . ",enriched.url.docSentiment"
            . ",enriched.url.entities"
            . ",enriched.url.concepts"
            . "&start=$start&end=$end"
            . "&q.enriched.url.entities.entity=|text=$company,type=company"
            . "|&count=50&outputMode=json";
        return json_decode(file_get_contents($src));
    }
}
