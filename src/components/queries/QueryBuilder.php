<?php
namespace mirocow\elasticsearch\components\queries;

use mirocow\elasticsearch\components\queries\helpers\QueryHelper;
use yii\helpers\ArrayHelper;

class QueryBuilder
{
    protected $query = null;

    /**
     * @var array
     */
    public $body = [];

    /**
     * Can accept parameters:
     * '*', false - итд
     * @var array|string|bool
     */
    private $withSource = false;

    /**
     * @var array
     */
    private $filter = [];

    /**
     * @var array
     */
    private $aggs = [];

    /**
     * @var array
     */
    private $highlight = [];

    /**
     * @var array
     */
    private $_source = [];

    /**
     * @var int
     */
    private $from = 0;

    /**
     * @var int
     */
    private $size = 10;

    /**
     * @var array
     */
    private $sort = [];

    /**
     * @var bool
     */
    private $release = true;

    /**
     * @var bool
     */
    private $store = false;

    /**
     * @var array
     */
    private $result = [];

    /**
     *
     */
    private function init()
    {
        if(!$this->query){
            $this->query = new Query;
        }
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function set($key, $value)
    {
        $this->init();
        ArrayHelper::setValue($this->query, $key, $value);

        return $this;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        $this->init();
        return ArrayHelper::getValue($this->query, $key);
    }

    /**
     * @param $value
     * @return $this
     */
    public function add($value)
    {
        $this->init();
        $this->query = ArrayHelper::merge($this->query, $value);

        return $this;
    }

    /**
     * @param string|array $query
     * @return $this
     */
    public function query($query = '')
    {
        $this->query = QueryHelper::query($query);

        return $this;
    }

    /**
     * @param int $size
     * @param int $from
     * @return $this
     */
    public function limit(int $size = 0, int $from = 0)
    {
        $this->from = $from;
        $this->size = $size;

        return $this;
    }

    /**
     * @param array $fieldsName
     * @return $this
     */
    public function sort(array $fieldsName = [])
    {
        if($fieldsName) {
            $this->sort = $fieldsName;
        }

        return $this;
    }

    /**
     * @param array $aggregations
     * @return $this
     */
    public function aggregations(array $aggregations = [])
    {
        $this->aggs = $aggregations;

        return $this;
    }

    /**
     * @param array $highlight
     * @return $this
     */
    public function highlight(array $highlight = [])
    {
        $this->highlight = $highlight;

        return $this;
    }

    /**
     * @param array $source
     * @return $this
     */
    public function source(array $source = [])
    {
        $this->_source = $source;

        return $this;
    }

    /**
     * @param array $filter
     * @return $this
     */
    public function filter(array $filter = [])
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * @param array|string|bool $data
     */
    public function withSource($data = '*')
    {
        $this->withSource = $data;

        return $this;
    }

    /**
     * @return array Elasticsearch DSL body
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-body.html
     */
    public function generateQuery()
    {

        $fields = [

            'query', // @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/query-dsl-match-query.html
            'filter', // @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-post-filter.html
            'from', // @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-from-size.html
            'size', // @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-from-size.html
            'aggs', // @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations.html
            'highlight', // @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-highlighting.html
            'sort', // @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-sort.html
            '_source', // @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-source-filtering.html
            'stored_fields', // TODO: @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-stored-fields.html
            'script_fields', // TODO: @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-script-fields.html
            'docvalue_fields', // TODO: @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-docvalue-fields.html
            'rescore', // TODO: @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-rescore.html
            'explain', // TODO: @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-explain.html
            'min_score', // TODO: @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-min-score.html
            'collapse', // TODO: @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-collapse.html

        ];

        foreach ($fields as $param) {
            if (!empty($this->{$param})) {
                $this->body[ $param ] = $this->{$param};
            }
        }

        /**
         * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/mapping-source-field.html
         */
        if (!$this->_source) {
            $this->body[ '_source' ] = $this->withSource;
        }

        /**
         * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/mapping-id-field.html
         * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-request-sort.html
         */
        if (!$this->sort) {
            $this->body[ 'sort' ] = QueryHelper::sortBy([ '_id' => [ 'order' => 'asc' ] ]);
        }

        return $this->body;
    }
}