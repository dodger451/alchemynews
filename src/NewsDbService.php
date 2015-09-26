<?php
/**
 * AlchemyAPI Client
 * @author david
 */
namespace Latotzky\Alchemynews;

/**
 * AlchemyNewsService
 */
class NewsDbService
{

    protected $options = array();
    protected $pdo = array();
    /**
     * NewsDbService constructor.
     * @param array $options
     */
    public function __construct($pdo, $options = [])
    {
        $this->pdo = $pdo;
        $this->options = $options;
    }

    /**
     * @param $app
     * @param $doc
     */
    function insertIfNotExists( $doc)
    {
        $st = $this->pdo->prepare(<<<SQL
INSERT INTO news
    (alchemyid, original_timestamp, sentiment, url, title, doc)
SELECT :alchemyid, :original_timestamp, :sentiment, :url, :title, :doc
WHERE
    NOT EXISTS (
        SELECT id FROM news WHERE alchemyid = :alchemyid or url = :url
        );
SQL
        );
        $data = [
            'alchemyid' => $doc->id,
            'original_timestamp' => date('Y-m-d G:i:s', $doc->timestamp),
            'sentiment' => $doc->source->enriched->url->docSentiment->type,
            'url' => $doc->source->enriched->url->url,
            'title' => $doc->source->enriched->url->title,
            'doc' => json_encode($doc),
        ];
        $res = $st->execute($data);
    }

    /**
     * @return array
     */
    function getLatest()
    {
        $st = $this->pdo->prepare(<<<SQL
SELECT * FROM news WHERE original_timestamp>now()- interval '30 day' ORDER BY original_timestamp DESC
SQL
);
        $st->execute();

        $docs = array();
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $docs[] = json_decode($row['doc']);
        }
        return $docs;
    }

}
