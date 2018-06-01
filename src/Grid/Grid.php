<?php
declare(strict_types=1);

/**
 * GnBit  (http://www.gnbit.com/)
 *
 * @author Benjamín Gonzales B. (benjamin@gnbit.com)
 * @Copyright (c) 2017 GnBit.SAC
 *
 */

namespace Alabra\Grid;

use Zend\Paginator\Adapter\DbSelect;
use Zend\Paginator\Paginator;
use Zend\Db\Sql\Select;

class Grid extends DbSelect
{
    private $ignoreFields = [];

    public function resulset(array $params = [])
    {

        $page = (int) $params['page'] ?? 1; //page number
        $rows = (int) $params['rows'] ?? 20;
        //
        // Filter items
        if ($params['_search'] == 'true') {
            $filters = json_decode($params['filters'], true);
            $this->filter($filters);
        }
        //Sort items by column
        if ($sidx = $params['sidx']) {
            $this->sort($sidx, $params['sord']);
        }


        $paginator = new Paginator($this);
        $paginator->setCurrentPageNumber($page);
        $paginator->setItemCountPerPage($rows);


        $rowsetGrid            = [];
        $rowsetGrid['page']    = $paginator->getCurrentPageNumber();
        $rowsetGrid['total']   = $paginator->count();
        $rowsetGrid['records'] = $paginator->getTotalItemCount();


        $items = $paginator->getCurrentItems();

        foreach ($items as $index => $column) {
            /* $rowsetGrid['rows'][$index]['id']   = $index;
              /*$rowsetGrid['rows'][$index]['cell'] = $column; */
            $rowsetGrid['rows'][] = $column;
        }
        $rowsetGrid['userdata']['q'] = base64_encode(gzcompress(serialize($this->select)));

        $rowsetGrid['pagination']    = [
            'page'    => $rowsetGrid['page'],
            'total'   => $rowsetGrid['total'],
            'records' => $rowsetGrid['records'],
        ];

        return $rowsetGrid;
    }

    public function setIgnoreFields(array $fields = [])
    {
        $this->ignoreFields = $fields;
    }

    public function sort($column = null, $order = "ASC")
    {
        $order = strtoupper($order);
        if (!empty($column)) {
            $this->select->reset(Select::ORDER);
            $this->select->order("$column $order");
        }
    }

    public function filter(array $filters = [])
    {
        if (empty($filters)) {
            return false;
        }

        foreach ($filters['rules'] as $rule) {
            if (count($this->ignoreFields) > 0 && in_array($rule['field'], $this->ignoreFields)) {
                continue;
            }

            if (!$this->isExpresssion($rule['field'])) {
                $where     = $this->select->getRawState('where');
                $predicate = $this->operator($rule, $where);
                if ($filters['groupOp'] == 'AND') {
                    $this->select->where($predicate);
                } else {
                    $this->select->where($predicate->or);
                }
            } else {
                $having    = $this->select->getRawState('having');
                $predicate = $this->operator($rule, $having);
                if ($filters['groupOp'] == 'AND') {
                    $this->select->having($predicate);
                } else {
                    $this->select->having($predicate->or);
                }
            }
        }
    }

    protected function isExpresssion($value)
    {
        $columns = $this->select->getRawState('columns');

        if (array_key_exists($value, $columns)) {
            return true;
        }

        $joins = $this->select->getRawState('joins');
        foreach ($joins as $table) {
            if (array_key_exists($value, $table['columns'])) {
                return true;
            }
        }

        return false;
    }

    protected function operator($rules, $where)
    {
        $op    = $rules['op'];
        $field = $rules['field'];
        $data  = $rules['data'];

        switch ($op) {
            case 'eq':
                return $where->equalTo($field, $data);
            case 'ne':
                return $where->notEqualTo($field, $data);
            case 'lt':
                return $where->lessThan($field, $data);
            case 'le':
                return $where->lessThanOrEqualTo($field, $data);
            case 'gt':
                return $where->greaterThan($field, $data);
            case 'ge':
                return $where->greaterThanOrEqualTo($field, $data);
            case 'bw':
                return $where->like($field, "$data%");
            case 'bn':
                return $where->addPredicate(new NotLike($field, "$data%"));
            case 'in':
                return $where->in($field, [$data]);
            case 'ni':
                return $where->notIn($field, [$data]);
            case 'ew':
                return $where->like($field, "%$data");
            case 'en':
                return $where->notLike($field, "%$data");
            case 'cn':
                return $where->like($field, "%$data%");
            case 'nc':
                return $where->notLike($field, "%$data%");
            default:
                return false;
        }
    }
}