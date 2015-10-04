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
        $st->execute($data);
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

        $entityTypeHierarchy = $this->options['entityTypeHierarchy'];
        $docs = array();
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $preparedDoc = json_decode($row['doc']);
            $preparedDoc->extra->entity = $this->findEntityByTypeHierachy($preparedDoc, $entityTypeHierarchy);
            $preparedDoc->extra->domain = parse_url($preparedDoc->source->enriched->url->url, PHP_URL_HOST);
            $preparedDoc->extra->sentiment_class = ' disabled';
            $preparedDoc->extra->sentiment_icon = '';
            //if (null != $preparedDoc->extra->entity) $preparedDoc->extra->sentiment_class .= $preparedDoc->extra->entity->sentiment->type . ' mixed:"'.print_r($preparedDoc->extra->entity->sentiment->mixed, true).'"';
            if (null != $preparedDoc->extra->entity) {
                $preparedDoc->extra->sentiment_class = 'info';
                $preparedDoc->extra->sentiment_icon = 'hand-right';

                if (('1' != $preparedDoc->extra->entity->sentiment->mixed)) {
                    if ('positive' == $preparedDoc->extra->entity->sentiment->type) {
                        $preparedDoc->extra->sentiment_class = 'success';
                        $preparedDoc->extra->sentiment_icon = 'hand-up';
                    } elseif ('negative' == $preparedDoc->extra->entity->sentiment->type) {
                        $preparedDoc->extra->sentiment_class = 'danger';
                        $preparedDoc->extra->sentiment_icon = 'hand-down';
                    }
                }
            }
            $docs[] = $preparedDoc;
        }
        return $docs;
    }

    function deleteBlacklisted($blacklist = array())
    {
        foreach ($blacklist as $black) {
            $st = $this->pdo->prepare(<<<SQL
DELETE FROM news WHERE url LIKE (:black)
SQL
            );
            $st->execute(['black' => $black]);

        }
    }

    /**
     * @param $preparedDoc
     * @param $entityTypeHierarchy
     * @return null
     */
    protected function findEntityByTypeHierachy($preparedDoc, $entityTypeHierarchy)
    {
        $entityObject = null;
        if (!empty($preparedDoc->source->enriched->url->entities)) {
            foreach ($preparedDoc->source->enriched->url->entities as $entity) {
                if ($entity->knowledgeGraph->typeHierarchy == $entityTypeHierarchy) {
                    $entityObject = $entity;
                    break;
                }
            }
        }
        return $entityObject;
    }

}
