<?php
namespace mirocow\elasticsearch\components\indexes;

use yii\db\ActiveQuery;
use yii\db\ActiveQueryTrait;
use yii\db\ActiveRecord;
use yii\db\ActiveRecordInterface;
use yii\helpers\ArrayHelper;

class ModelPopulate
{

    use ActiveQueryTrait;

    public $indexBy = 'id';

    private $select = '';

    protected $result = [];

    public function __construct(array &$result = [])
    {
        $this->result = $result;
    }

    /**
     * Converts the raw query results into the format as specified by this query.
     * This method is internally used to convert the data fetched from database
     * into the format as required by this query.
     * @return array the converted query result
     * @since 2.0.4
     */
    public function populate()
    {
        $rows = &$this->result;

        if ($this->indexBy === null) {
            return $rows;
        }
        $models = [];
        $i = 0;
        foreach ($rows as $key => $row) {
            if ($this->indexBy !== null) {
                if (is_string($this->indexBy)) {
                    if (isset($row['fields'][$this->indexBy])) {
                        $key = reset($row['fields'][$this->indexBy]);
                    } elseif (isset($row['_source'][$this->indexBy])){
                        $key = $row['_source'][$this->indexBy];
                    } elseif (isset($row[$this->indexBy])){
                        $key = $row[$this->indexBy];
                    } else {
                        $key = $i++;
                    }
                } else {
                    $key = call_user_func($this->indexBy, $row);
                }
            }

            if(!empty($row['_source'])){
                $row = $row['_source'];
            }

            $models[$key] = $row;
        }
        return $models;
    }

    /**
     * Converts found rows into model instances
     * @param array $rows
     * @return array|ActiveRecord[]
     * @since 2.0.4
     */
    protected function createModels($rows)
    {
        $models = [];
        if ($this->asArray) {
            if ($this->indexBy === null) {
                return $rows;
            }
            foreach ($rows as $row) {
                if (is_string($this->indexBy)) {
                    $key = '_id';
                    if (isset($row['fields'][$this->indexBy])) {
                        $key = reset($row['fields'][$this->indexBy]);
                    } elseif (isset($row['_source'][$this->indexBy])){
                        $key = $row['_source'][$this->indexBy];
                    } elseif (isset($row[$key])){
                        $key = $row[$key];
                    }
                } else {
                    $key = call_user_func($this->indexBy, $row);
                }
                $models[$key] = $row;
            }
        } else {
            /* @var $class ActiveRecord */
            $class = $this->modelClass;

            if($this->indexBy === null){
                $this->indexBy = '_id';
            }

            foreach ($rows as $row) {
                $model = $class::instantiate($row);

                /** @var ActiveRecord $modelClass */
                $modelClass = get_class($model);

                if(isset($row['_source'])) {
                    $row = $row['_source'];
                }

                if(is_numeric($row)){
                    $row = ['_id' => $row];
                }

                // Fill attributes from _source
                $modelClass::populateRecord($model, $row);

                // We haven`t all model`s attributes
                if(count($model->attributes) <> count($row)) {

                    // If exists use special elasticserch field
                    if(!empty($row['_id'])){
                        $id = $row['_id'];
                    } elseif(!empty($row[$this->indexBy])) {
                        $id = $row[$this->indexBy];
                    } else {
                        continue;
                    }

                    $model = $modelClass::findOne(['id' => $id]);

                    if(!$model){
                        continue;
                    }

                }

                if (is_string($this->indexBy)) {
                    $key = $model->{$this->indexBy};
                } else {
                    $key = call_user_func($this->indexBy, $model);
                }

                $models[$key] = $model;
            }
        }

        return $models;
    }

    /**
     * Finds records corresponding to one or multiple relations and populates them into the primary models.
     * @param array $with a list of relations that this query should be performed with. Please
     * refer to [[with()]] for details about specifying this parameter.
     * @param array|ActiveRecord[] $models the primary models (can be either AR instances or arrays)
     */
    public function findWith($with, &$models)
    {
        $primaryModel = reset($models);
        if (!$primaryModel instanceof ActiveRecordInterface) {
            $primaryModel = new $this->modelClass();
        }
        $relations = $this->normalizeRelations($primaryModel, $with);
        /* @var $relation ActiveQuery */
        foreach ($relations as $name => $relation) {
            if ($relation->asArray === null) {
                // inherit asArray from primary query
                $relation->asArray($this->asArray);
            }
            $relation->populateRelation($name, $models);
        }
    }

    /**
     * Executes the query and returns all results as an array.
     * If this parameter is not given, the `elasticsearch` application component will be used.
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all()
    {
        $this->search();

        if (empty($this->result['hits']['hits'])) {
            return [];
        }
        $this->result = $this->result['hits']['hits'];

        return $this->populate();
    }

    /**
     * Executes the query and returns all results as an array.
     * If this parameter is not given, the `elasticsearch` application component will be used.
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
    public function one()
    {
        $return = $this->all();

        if(!$return){
            return [];
        }

        return end($return);
    }

    /**
     * @param $select
     */
    public function select($select)
    {
        $this->select = $select;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function search()
    {
        $result = &$this->result;

        if(!empty($result['hits']['hits'])) {
            if ($this->select) {
                $hits = [];
                foreach ($result['hits']['hits'] as $item) {
                    if(substr($this->select, -2) == '.*') {
                        $key = substr($this->select, 0, strlen($this->select)-2);
                        $values = ArrayHelper::getValue($item, $key);
                        if($values) {
                            $hits = array_merge($hits, $values);
                        }
                    } else {
                        $value = ArrayHelper::getValue($item, $this->select);
                        if($value) {
                            $hits[] = $value;
                        }
                    }
                }
                $result['hits']['hits'] = $hits;
            }

            if (!$this->asArray) {
                $models = $this->createModels($result['hits']['hits']);
                if (!empty($this->with)) {
                    $this->findWith($this->with, $models);
                }
                foreach ($models as $model) {
                    $model->afterFind();
                }
                $result['hits']['hits'] = $models;
            }
        }

        return $result;
    }

}
