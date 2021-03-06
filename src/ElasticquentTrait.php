<?php namespace Elasticquent;

use Elasticsearch\Client;

/**
 * Elasticquent Trait
 *
 * Functionality extensions for Elequent that
 * makes working with Elasticsearch easier.
 */
trait ElasticquentTrait
{

    public static function boot()
    {

        parent::boot();

        $client = new Client();
        $instance = new static;

        static::created(function ($model) use ($client, $instance) {
            $client->index([
                'index' => $instance->getIndexName(),
                'type'  => $instance->getTypeName(),
                'id'    => $model->getKey(),
                'body'  => $model->toArray()
            ]);
        });

        static::updated(function ($model) use ($client, $instance) {
            $client->index([
                'index' => $instance->getIndexName(),
                'type'  => $instance->getTypeName(),
                'id'    => $model->getKey(),
                'body'  => $model->toArray()
            ]);
        });

        static::deleted(function ($model) use ($client, $instance) {
            $client->index([
                'index' => $instance->getIndexName(),
                'type'  => $instance->getTypeName(),
                'id'    => $model->getKey(),
            ]);
        });
    }

    /**
     * Uses Timestamps In Index
     *
     * @var bool
     */
    protected $usesTimestampsInIndex = true;

    /**
     * Is ES Document
     *
     * Set to true when our model is
     * populated by a
     *
     * @var bool
     */
    protected $isDocument = false;

    /**
     * Document Score
     *
     * Hit score when using data
     * from Elasticsearch results.
     *
     * @var null|int
     */
    protected $documentScore = null;

    /**
     * Document Version
     *
     * Elasticsearch document version.
     *
     * @var null|int
     */
    protected $documentVersion = null;

    /**
     * Get ElasticSearch Client
     *
     * @return \Elasticsearch\Client
     */
    public function getElasticSearchClient()
    {
        $config = array();

        if (\Config::has('elasticquent.config')) {
            $config = \Config::get('elasticquent.config');
        }

        return new \Elasticsearch\Client($config);
    }

    /**
     * New Collection
     *
     * @param array $models
     * @return Collection
     */
    public function newCollection(array $models = array())
    {
        return new ElasticquentCollection($models);
    }

    /**
     * Get Index Name
     *
     * @return string
     */
    public function getIndexName()
    {
        // The first thing we check is if there
        // is an elasticquery config file and if there is a
        // default index.
        if (\Config::has('elasticquent.default_index')) {
            return \Config::get('elasticquent.default_index');
        }

        // Otherwise we will just go with 'default'
        return 'default';
    }

    /**
     * Get option to return Eloquent Models
     *
     * @return bool
     */
    protected function getEloquentModelReturnOption()
    {
        if (\Config::has('elasticquent.return_eloquent_models')) {
            return \Config::get('elasticquent.return_eloquent_models');
        }

        return false;
    }

    /**
     * Get Type Name
     *
     * @return string
     */
    public function getTypeName()
    {
        return $this->getTable();
    }

    /**
     * Uses Timestamps In Index
     *
     * @return bool
     */
    public function usesTimestampsInIndex()
    {
        return $this->usesTimestampsInIndex;
    }

    /**
     * Use Timestamps In Index
     *
     * @return void
     */
    public function useTimestampsInIndex()
    {
        $this->usesTimestampsInIndex = true;
    }

    /**
     * Don't Use Timestamps In Index
     *
     * @return void
     */
    public function dontUseTimestampsInIndex()
    {
        $this->usesTimestampsInIndex = false;
    }

    /**
     * Get Mapping Properties
     *
     * @return array
     */
    public function getMappingProperties()
    {
        return $this->mappingProperties;
    }

    /**
     * Set Mapping Properties
     *
     * @param $mapping
     * @internal param array $mappingProperties
     */
    public function setMappingProperties($mapping)
    {
        $this->mappingProperties = $mapping;
    }

    /**
     * Is Elasticsearch Document
     *
     * Is the data in this module sourced
     * from an Elasticsearch document source?
     *
     * @return bool
     */
    public function isDocument()
    {
        return $this->isDocument;
    }

    /**
     * Get Document Score
     *
     * @return null|float
     */
    public function documentScore()
    {
        return $this->documentScore;
    }

    /**
     * Document Version
     *
     * @return null|int
     */
    public function documentVersion()
    {
        return $this->documentVersion;
    }

    /**
     * Get Index Document Data
     *
     * Get the data that Elasticsearch will
     * index for this particular document.
     *
     * @return  array
     */
    public function getIndexDocumentData()
    {
        return $this->toArray();
    }

    /**
     * Index Documents
     *
     * Index all documents in an Eloquent model.
     *
     * @return  array
     */
    public static function addAllToIndex()
    {
        $instance = new static;

        $all = $instance->newQuery()->get(array('*'));

        return $all->addToIndex();
    }

    /**
     * Re-Index All Content
     *
     * @return array
     */
    public static function reindex()
    {
        $instance = new static;

        $all = $instance->newQuery()->get(array('*'));

        return $all->reindex();
    }

    /**
     * Search By Query
     *
     * Search with a query array
     *
     * @param array $query
     * @param array $options
     *              ['limit']
     *              ['offset']
     *              ['sourceFields']
     *              ['aggregations']
     *
     * @return  ElasticquentResultCollection
     */
    public static function searchByQuery($query = null, array $options = [])
    {
        $instance = new static;

        if(!isset($options['offset'])){
            $options['offset'] = null;
        }

        if(!isset($options['limit'])){
            $options['limit'] = null;
        }

        $params = $instance->getBasicEsParams(true, true, true, $options['limit'], $options['offset']);

        if (isset($options['sourceFields'])) {
            $params['body']['_source']['include'] = $options['sourceFields'];
        }

        if ($query) {
            $params['body']['query'] = $query;
        }

        if (isset($options['aggregations'])) {
            $params['body']['aggs'] = $options['aggregations'];
        }

        $result = $instance->getElasticSearchClient()->search($params);

        return $instance->newResultCollection($result);
    }

    /**
     * Search
     *
     * Simple search using a match _all query
     *
     * @param   string $term
     * @return  ElasticquentResultCollection
     */
    public static function search($term = null)
    {
        $instance = new static;

        $params = $instance->getBasicEsParams();

        $params['body']['query']['match']['_all'] = $term;

        $result = $instance->getElasticSearchClient()->search($params);

        return $instance->newResultCollection($result);
    }

    /**
     * Add to Search Index
     *
     * @throws Exception
     * @return array
     */
    public function addToIndex()
    {
        if (!$this->exists) {
            throw new Exception('Document does not exist.');
        }

        $params = $this->getBasicEsParams();

        // Get our document body data.
        $params['body'] = $this->getIndexDocumentData();

        // The id for the document must always mirror the
        // key for this model, even if it is set to something
        // other than an auto-incrementing value. That way we
        // can do things like remove the document from
        // the index, or get the document from the index.
        $params['id'] = $this->getKey();

        return $this->getElasticSearchClient()->index($params);
    }

    /**
     * Remove From Search Index
     *
     * @return array
     */
    public function removeFromIndex()
    {
        return $this->getElasticSearchClient()->delete($this->getBasicEsParams());
    }

    /**
     * Get Search Document
     *
     * Retrieve an ElasticSearch document
     * for this enty.
     *
     * @return array
     */
    public function getIndexedDocument()
    {
        return $this->getElasticSearchClient()->get($this->getBasicEsParams());
    }

    /**
     * Get Basic Elasticsearch Params
     *
     * Most Elasticsearch API calls need the index and
     * type passed in a parameter array.
     *
     * @param     bool $getIdIfPossible
     * @param     bool $getSourceIfPossible
     * @param     bool $getTimestampIfPossible
     * @param     int  $limit
     * @param     int  $offset
     *
     * @return    array
     */
    public function getBasicEsParams($getIdIfPossible = true, $getSourceIfPossible = false, $getTimestampIfPossible = false, $limit = null, $offset = null)
    {
        $params = array(
            'index' => $this->getIndexName(),
            'type'  => $this->getTypeName()
        );

        if ($getIdIfPossible and $this->getKey()) {
            $params['id'] = $this->getKey();
        }

        $fieldsParam = array();

        if ($getSourceIfPossible) {
            array_push($fieldsParam, '_source');
        }

        if ($getTimestampIfPossible) {
            array_push($fieldsParam, '_timestamp');
        }

        if ($fieldsParam) {
            $params['fields'] = implode(",", $fieldsParam);
        }

        if (is_numeric($limit)) {
            $params['size'] = $limit;
        }

        if (is_numeric($offset)) {
            $params['from'] = $offset;
        }

        return $params;
    }

    /**
     * Mapping Exists
     *
     * @return bool
     */
    public static function mappingExists()
    {
        $instance = new static;

        $mapping = $instance->getMapping();

        return (empty($mapping)) ? false : true;
    }

    /**
     * Get Mapping
     *
     * @return array
     */
    public static function getMapping()
    {
        $instance = new static;

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->getMapping($params);
    }

    /**
     * Put Mapping
     *
     * @param    bool $ignoreConflicts
     * @return   array
     */
    public static function putMapping($ignoreConflicts = false)
    {
        $instance = new static;

        $mapping = $instance->getBasicEsParams();

        $params = array(
            '_source'    => array('enabled' => true),
            'properties' => $instance->getMappingProperties()
        );

        $mapping['body'][$instance->getTypeName()] = $params;

        return $instance->getElasticSearchClient()->indices()->putMapping($mapping);
    }

    /**
     * Delete Mapping
     *
     * @return array
     */
    public static function deleteMapping()
    {
        $instance = new static;

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->deleteMapping($params);
    }

    /**
     * Rebuild Mapping
     *
     * This will delete and then re-add
     * the mapping for this model.
     *
     * @return array
     */
    public static function rebuildMapping()
    {
        $instance = new static;

        // If the mapping exists, let's delete it.
        if ($instance->mappingExists()) {
            $instance->deleteMapping();
        }

        // Don't need ignore conflicts because if we
        // just removed the mapping there shouldn't
        // be any conflicts.
        return $instance->putMapping();
    }

    /**
     * Create Index
     *
     * @param int $shards
     * @param int $replicas
     * @return array
     */
    public static function createIndex($shards = null, $replicas = null)
    {
        $instance = new static;

        $client = $instance->getElasticSearchClient();

        $index = array(
            'index' => $instance->getIndexName()
        );

        if ($shards) {
            $index['body']['settings']['number_of_shards'] = $shards;
        }

        if ($replicas) {
            $index['body']['settings']['number_of_replicas'] = $replicas;
        }

        return $client->indices()->create($index);
    }

    /**
     * Type Exists
     *
     * Does this type exist?
     *
     * @return bool
     */
    public static function typeExists()
    {
        $instance = new static;

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->existsType($params);
    }

    /**
     * New FRom Hit Builder
     *
     * Variation on newFromBuilder. Instead, takes
     *
     * @param  array $hit
     * @return static
     */
    public function newFromHitBuilder($hit = array())
    {
        $instance = $this->newInstance(array(), true);

        $attributes = $hit['_source'];

        // Add fields to attributes
        if (isset($hit['fields'])) {
            foreach ($hit['fields'] as $key => $value) {
                $attributes[$key] = $value;
            }
        }

        $instance->setRawAttributes((array)$attributes, true);

        // In addition to setting the attributes
        // from the index, we will set the score as well.
        $instance->documentScore = $hit['_score'];

        // This is now a model created
        // from an Elasticsearch document.
        $instance->isDocument = true;

        // Set our document version if it's
        if (isset($hit['_version'])) {
            $instance->documentVersion = $hit['_version'];
        }

        return $instance;
    }

    /**
     *
     *
     * @param $hit
     * @return mixed
     */
    public function eloquentHitBuilder($hit)
    {

        $model = $this->find($hit['_id']);
        $model->score = $hit['_score'];

        return $model;
    }

    /**
     * New Collection
     *
     * @param array $results
     * @return ElasticquentResultCollection
     */
    protected function newResultCollection(array $results = array())
    {
        return new ElasticquentResultCollection($results, new static, $this->getEloquentModelReturnOption());
    }

}