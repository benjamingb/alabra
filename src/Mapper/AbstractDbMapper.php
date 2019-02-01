<?php

declare(strict_types = 1);

/**
 * GnBit  (http://www.gnbit.com/)
 *
 * @author Benjamín Gonzales B. (benjamin@gnbit.com)
 * @Copyright (c) 2017 GnBit.SAC
 *
 */

namespace Alabra\Mapper;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\TableIdentifier;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Adapter\Driver\StatementInterface;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Paginator\Paginator;
use Zend\Paginator\Adapter\DbSelect;
use Alabra\Exception;
use Alabra\Type\TypeInterfece;

abstract class AbstractDbMapper
{

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * @var string
     */
    const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var Adapter
     */
    protected $dbAdapter;

    /**
     * @var Select
     */
    protected $selectPrototype;

    /**
     * @var Sql
     */
    private $sql;

    /**
     * @var string|TableIdentifier
     */
    protected $tableName;

    /**
     * primary key table
     * @var string
     */
    protected $primaryKey;

    /**
     * Associative array map of declarative referential integrity rules.
     * This array has one entry per foreign key in the current table.
     * Each key is a mnemonic name for one reference rule.
     *
     * Each value is also an associative array, with the following keys:
     * - columns       = array of names of column(s) in the child table.
     * - refTableClass = class name of the parent table.
     * - refColumns    = array of names of column(s) in the parent table,
     *
     * @var array
     */
    protected $referenceMap = [];

    /**
     * If is true, search in the Db before persist
     *
     * @var boolean
     */
    protected $securePersist = false;

    /**
     * @var boolean
     */
    private $isInitialized = false;

    /**
     * Last insert or update Id
     *
     * @var string|int
     */
    private $lastPersistId = 0;

    /**
     * Performs some basic initialization setup and checks before running a query
     * @throws \Exception
     */
    protected function initialize()
    {
        if ($this->isInitialized) {
            return;
        }

        if (!$this->dbAdapter instanceof Adapter) {
            throw new \Exception('No db adapter present');
        }

        if (!$this->tableName) {
            throw new \Exception('The tableName is not defined');
        }

        $this->isInitialized = true;
    }

    /**
     * @return Adapter
     */
    public function getDbAdapter()
    {
        return $this->dbAdapter;
    }

    /**
     * @param Adapter $dbAdapter
     * @return AbstractDbMapper
     */
    public function setDbAdapter(Adapter $dbAdapter)
    {
        $this->dbAdapter = $dbAdapter;
        return $this;
    }

    /**
     * @return Sql
     */
    protected function getSql()
    {
        if (!$this->sql instanceof Sql) {
            $this->sql = new Sql($this->getDbAdapter());
        }

        return $this->sql;
    }

    /**
     * @param Sql $sql
     * @return AbstractDbMapper
     */
    protected function setSql(Sql $sql)
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * @param $securePersist
     * @return AbstractDbMapper
     */
    public function setSecurePersist($securePersist)
    {
        $this->securePersist = $securePersist;
        return $this;
    }

    /**
     * Returns the name of primary_key in the table
     *
     * @return string Id
     * @throws \Exception
     */
    protected function getPrimaryKey()
    {
        if (!$this->primaryKey) {
            throw new \Exception('PrimaryKey is not defined in the mapper ' . get_class($this));
        }

        return $this->primaryKey;
    }

    /**
     * @param string|TableIdentifier|null $table
     * @return Select
     */
    protected function getSelect($table = null)
    {
        $this->initialize();
        return $this->getSql()->select($table ?: $this->tableName);
    }

    /**
     * Add an alias to the table
     * @param string $alias
     * @return Select
     */
    protected function getSelectAlias($alias = 't1')
    {
        return $this->getSelect([$alias => $this->tableName]);
    }

    protected function resultSet(Select $select)
    {
        $this->initialize();

        $statement = $this->getSql()->prepareStatementForSqlObject($select);
        $rowset    = $statement->execute();

        $resultSet = new ResultSet();
        $resultSet->initialize($rowset);
        return $resultSet;
    }

    /**
     * Paginate Query
     *
     * $result->setCurrentPageNumber($page);
     * $result->setItemCountPerPage($count);
     *
     * @param Select $select
     * @return Paginator
     */
    protected function resultSetPagination(Select $select)
    {
        $this->initialize();
        $paginatorAdapter = new DbSelect($select, $this->getDbAdapter(), new ResultSet());
        return new Paginator($paginatorAdapter);
    }

    /**
     * @return string|int
     */
    public function getLastpersistId()
    {
        return $this->lastPersistId;
    }

    /**
     * @param string|array|\Closure $where
     * @param string|TableIdentifier|null $tableName
     * @return StatementInterface
     */
    protected function delete($where, $tableName = null)
    {
        $this->initialize();

        $sql    = $this->getSql();
        $delete = $sql->delete($tableName ?: $this->tableName);

        $delete->where($where);

        $statement = $sql->prepareStatementForSqlObject($delete);
        return $statement->execute();
    }

    /**
     * remove by ID
     * @param string $id
     */
    public function remove($id)
    {
        return $this->delete([$this->getPrimaryKey() => $id]);
    }

    /**
     * @param object|array $entity
     * @param string|TableIdentifier|null $tableName
     * @return ResultInterface
     */
    public function insert($entity, $tableName = null): ResultInterface
    {
        $this->initialize();

       //remove null values
        $entityValuesfilter = array_filter($this->entityToArray($entity), function($value) {
            return !is_null($value);
        });

        $entityValues = array_map(function($value) {
            if ($value instanceof TypeInterfece) {
                return $value->getValue();
            }
            return $value;
        }, $entityValuesfilter);


        $sql    = $this->getSql();
        $insert = $sql->insert($tableName ?: $this->tableName);


        $insert->values($entityValues);

        $statement = $sql->prepareStatementForSqlObject($insert);

        $result = $statement->execute();

        $this->lastPersistId = $result->getGeneratedValue();

        return $result;
    }

    /**
     * @param object|array $entity
     * @param string|array|\Closure $where
     * @param string|TableIdentifier|null $tableName
     * @return ResultInterface
     */
    public function update($entity, $where, $tableName = null): ResultInterface
    {
        $this->initialize();

        //remove null values
        $entityValuesfilter = array_filter($this->entityToArray($entity), function($value) {
            return !is_null($value);
        });

        $entityValues = array_map(function($value) {
            if ($value instanceof TypeInterfece) {
                return $value->getValue();
            }
            return $value;
        }, $entityValuesfilter);

        if (isset($entityValues[self::CREATED_AT])) {
            unset($entityValues[self::CREATED_AT]);
        }

        $sql    = $this->getSql();
        $update = $sql->update($tableName ?: $this->tableName);

        $update->set($entityValues)->where($where);

        $statement = $sql->prepareStatementForSqlObject($update);

        return $statement->execute();
    }

    /**
     * persist entity in DataBase
     *
     * @param object|array $entity
     * @return ResultInterface
     */
    public function persist($entity): ResultInterface
    {
        if ($this->securePersist) {
            return $this->securePersist($entity);
        }

        $this->initialize();

        $entityValues = $this->entityToArray($entity);

        if (isset($entityValues[$this->getPrimaryKey()])) {

            $id = $entityValues[$this->getPrimaryKey()];
            unset($entityValues[$this->getPrimaryKey()]);

            $this->lastPersistId = $id;

            return $this->update($entityValues, [
                    $this->getPrimaryKey() => $id,
            ]);
        } else {
            return $this->insert($entityValues);
        }
    }

    /**
     * Search before the perists database
     *
     * @param object|array $entity
     * @return ResultInterface
     */
    protected function securePersist($entity): ResultInterface
    {

        $this->initialize();

        $entityValues = $this->entityToArray($entity);

        $id = $entityValues[$this->getPrimaryKey()];

        $row = $this->find($id);

        if ($row) {

            unset($entityValues[$this->getPrimaryKey()]);

            return $this->update($entityValues, [
                    $this->getPrimaryKey() => $id,
            ]);
        } else {
            return $this->insert($entityValues);
        }
    }

    /**
     * Search by id tabla
     * @param int|string $id
     * @return \ArrayObject
     */
    public function find($id)
    {

        $select = $this->getSelect();
        $select->where->equalTo($this->getPrimaryKey(), $id);
        return $this->resultSet($select)->current();
    }

    /**
     * Search in table
     *
     * example
     *
     * $mapper->findBy(['col1'=>'my value','col2'=>'my other value']);
     *
     * $ammper->findBy(
     *   new \Zend\Db\Sql\Predicate\PredicateSet(
     *   array(
     *       new \Zend\Db\Sql\Predicate\Operator('column1', '=', 'value1'),
     *      new \Zend\Db\Sql\Predicate\Operator('column2', '=', 'value2')
     *   ),
     *
     *   // optional; OP_AND is default
     *   \Zend\Db\Sql\Predicate\PredicateSet::OP_AND
     *  ),
     *
     *  // optional; OP_AND is default
     *  \Zend\Db\Sql\Predicate\PredicateSet::OP_OR
     * )
     *
     * @param array $where
     * @return ResultInterface|null
     */
    public function findBy(array $where)
    {
        $select = $this->getSelect();
        if ($where) {
            $select->where($where);
            return $this->resultSet($select);
        }

        return null;
    }

    /**
     * FetchAll table
     *
     * @param bool $options
     * @param array $paginate
     * @return ResultInterface
     */
    public function fetchAll(array $options = [], $paginate = false)
    {
        $select = $this->getSelect();

        if ($options['columns'] ?? false) {
            $select->columns($options['columns']);
        }
        if ($options['where'] ?? false) {
            $select->where($options['where']);
        }
        if ($options['having'] ?? false) {
            $select->having($options['having']);
        }
        if ($options['order'] ?? false) {
            $select->order($options['order']);
        }
        if ($options['limit'] ?? false) {
            $select->limit($options['limit']);
        }
        if ($options['offset'] ?? false) {
            $select->offset($options['offset']);
        }

        if ($paginate) {
            return $this->resultSetPagination($select);
        }

        return $this->resultSet($select);
    }

    /**
     * Get last row insert
     *
     * @param type $persistId
     * @return ResultInterface|null
     */
    public function getRowLastPersist()
    {
        return $this->find($this->lastPersistId);
    }

    /**
     * Convert the entity to an array
     *
     * @param  AbstractDbMapper |array $entity
     * @return Array
     * @throws Exception\InvalidArgumentException
     */
    protected function entityToArray($entity)
    {
        if (is_array($entity)) {
            return $entity;
        } elseif (is_object($entity)) {
            if (is_callable([$entity, 'getArrayCopy'])) {
                return $entity->getArrayCopy();
            } else {
                throw new Exception\BadMethodCallException(
                sprintf('%s expects the provided object to implement getArrayCopy()', __METHOD__)
                );
            }
        }
        throw new Exception\InvalidArgumentException('Entity passed to db mapper should be an array or object.');
    }
}
