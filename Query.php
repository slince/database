<?php
/**
 * slince database library
 * @author Tao <taosikai@yeah.net>
 */
namespace Slince\Database;

use Slince\Database\Expression\CompositeExpression;
use Slince\Database\Expression\QueryExpression;

class Query
{
    /**
     * query类型select
     */
    const SELECT = 1;

    /**
     * query类型delete
     */
    const DELETE = 2;

    /**
     * query类型update
     */
    const UPDATE = 3;

    /**
     * query类型insert
     */
    const INSERT = 4;

    /**
     * 当前query类型
     * @var int
     */
    protected $type;

    /**
     * sql片段
     * @var array
     */
    protected $sqlParts = [
        'select' => [],
        'from' => [],
        'where' => null,
        'order' => [],
        'offset' => null,
        'limit' => null,
        'distinct' => [],
        'group' => [],
        'having' => null,
        'insert' => [],
        'into' => null,
        'values' => [],
        'update' => [],
        'set' => [],
    ];

    /**
     * 数据库连接对象
     * @var Connection
     */
    protected $connection;

    /**
     * 参数类型绑定
     * @var ValueBinder
     */
    protected $valueBinder;

    public function __construct(Connection $connection)
    {
        $this->setConnection($connection);
    }

    /**
     * 设置连接对象
     * @param Connection $connection
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * 获取连接对象
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * 获取参数绑定器
     * @return ValueBinder
     */
    public function getValueBinder()
    {
        if (is_null($this->valueBinder)) {
            $this->valueBinder = new ValueBinder();
        }
        return $this->valueBinder;
    }

    /**
     * 获取当前query类型
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * 获取指定sql片段
     * @param $name
     * @return mixed
     */
    public function getSqlPart($name)
    {
        return $this->sqlParts[$name] ?: null;
    }

    /**
     * 获取所有的数据库片段
     * @return array
     */
    public function getSqlParts()
    {
        return $this->sqlParts;
    }

    /**
     * 设置参数
     * @param $name
     * @param $value
     * @param mixed $type
     * @return $this
     */
    public function setParameter($name, $value, $type = null)
    {
        $this->getValueBinder()->setParameter($name, $value, $type);
        return $this;
    }

    /**
     * 批量设置参数
     * @param array $parameters
     * @param array $parameterTypes
     * @return $this
     */
    public function setParameters(array $parameters, array $parameterTypes = [])
    {
        $this->getValueBinder()->setParameters($parameters, $parameterTypes);
        return $this;
    }

    /**
     * 创建query expression
     * @param string $tie
     * @return QueryExpression
     */
    public function newExpr($tie = 'AND')
    {
        return new QueryExpression($this, $tie);
    }

    /**
     * 插入query
     * @param $table
     * @return $this
     */
    public function insert($table)
    {
        $this->type = self::INSERT;
        $this->sqlParts['insert'] = $table;
        return $this;
    }

    /**
     * 插入query值部分
     * @param $data
     * @return $this
     */
    public function values($data)
    {
        $this->sqlParts['values'] = array_fill(0, count($data), '?');;
        $this->addParameters(array_values($data));
        return $this;
    }

    /**
     * 更新query
     * @param $table
     * @param null $alias
     * @return $this
     */
    public function update($table, $alias = null)
    {
        $this->type = self::UPDATE;
        $this->sqlParts['update'] = [
            'table' => $table,
            'alias' => $alias
        ];
        return $this;
    }

    /**
     * update set
     * @param $data
     * @return $this
     */
    public function set($data)
    {
        $this->sqlParts['set'] = array_fill(0, count($data), '?');
        $this->addParameters(array_values($data));
        return $this;
    }

    /**
     * delete
     * @param $table
     * @param null $alias
     * @return $this
     */
    public function delete($table, $alias = null)
    {
        $this->type = self::DELETE;
        $this->sqlParts['delete'] = [
            'table' => $table,
            'alias' => $alias
        ];
        return $this;
    }

    /**
     * select
     * @param null $fields
     * @return $this
     */
    public function select($fields = null)
    {
        $this->type = self::SELECT;
        if (!is_array($fields)) {
            $fields = func_get_args();
        }
        $this->sqlParts['select'] = $fields;
        return $this;
    }

    /**
     * select from
     * @param $table
     * @param null $alias
     * @return $this
     */
    public function from($table, $alias = null)
    {
        $this->sqlParts['from'][] = [
            'table' => $table,
            'alias' => $alias
        ];
        return $this;
    }

    /**
     *  设置where条件
     * $query->select('id', 'name')->from('posts')
     *
     *  // 1、
     *  $query->where('id = 8', "name = 'hello'");
     *
     * // 2、
     * $query->where(['id = 8', "name = 'hello'"]);
     *
     * // 3、
     * $query->where(['id => 8, 'name' => 'hello']);
     *
     * // 4、
     * $query->where(function(QueryExpression $qe){
     *      return $qe->eq('id', 8)->eq('name', 'hello');
     * })
     * @param $expressions
     * @return $this
     */
    public function where($expressions)
    {
        if (!$expressions instanceof QueryExpression) {
            if (!is_array($expressions)) {
                $expressions = func_get_args();
            }
            $expressions = $this->newExpr()->addMultiple($expressions);
        }
        $this->sqlParts['where'] = $expressions;
        return $this;
    }

    /**
     *  and where
     * @param $expressions
     * @return $this
     */
    public function andWhere($expressions)
    {
        $where = $this->getSqlPart('where');
        if (!$where instanceof QueryExpression) {
            $where = $this->newExpr(CompositeExpression::TYPE_AND);
        }
        $where = call_user_func_array([$where, 'andX'], func_get_args());
        $this->sqlParts['where'] = $where;
        return $this;
    }

    /**
     * or where
     * @param $expressions
     * @return $this
     */
    public function orWhere($expressions)
    {
        $where = $this->getSqlPart('where');
        if (!$where instanceof QueryExpression) {
            $where = $this->newExpr(CompositeExpression::TYPE_OR);
        }
        $where = call_user_func_array([$where, 'orX'], func_get_args());
        $this->sqlParts['where'] = $where;
        return $this;
    }

    /**
     *  group
     * @param $field
     * @return $this
     */
    public function group($field)
    {
        $group = is_array($field) ? $field : func_get_args();
        $this->sqlParts['group'] = $group;
        return $this;
    }

    /**
     * having
     * @param $expressions
     * @return $this
     */
    public function having($expressions)
    {
        if (!$expressions instanceof QueryExpression) {
            if (!is_array($expressions)) {
                $expressions = func_get_args();
            }
            $expressions = $this->newExpr()->addMultiple($expressions);
        }
        $this->sqlParts['having'] = $expressions;
        return $this;
    }

    /**
     * and having
     * @param $expressions
     * @return $this
     */
    public function andHaving($expressions)
    {
        $having = $this->getSqlPart('having');
        if (!$having instanceof QueryExpression) {
            $having = $this->newExpr(CompositeExpression::TYPE_AND);
        }
        $having = call_user_func_array([$having, 'andX'], func_get_args());
        $this->sqlParts['having'] = $having;
        return $this;
    }

    /**
     *  or having
     * @param $expressions
     * @return $this
     */
    public function orHaving($expressions)
    {
        $having = $this->getSqlPart('where');
        if (!$having instanceof QueryExpression) {
            $having = $this->newExpr(CompositeExpression::TYPE_OR);
        }
        $having = call_user_func_array([$having, 'orX'], func_get_args());
        $this->sqlParts['having'] = $having;
        return $this;
    }

    /**
     * order
     * @param $sort
     * @param null $direction
     * @return $this
     */
    public function order($sort, $direction = null)
    {
        if (!is_array($sort)) {
            $order[] = [
                'sort' => $sort,
                'direction' => $direction
            ];
        } else {
            $order = $sort;
        }
        $this->sqlParts['order'] = $order;
        return $this;
    }

    /**
     * limit
     * @param $num
     * @return $this
     */
    public function limit($num)
    {
        $this->sqlParts['limit'] = $num;
        return $this;
    }

    /**
     * offset
     * @param $num
     * @return $this
     */
    public function offset($num)
    {
        $this->sqlParts['offset'] = $num;
        return $this;
    }

    /**
     * 编译当前query为原生的sql
     * @return string
     */
    public function toSql()
    {
        return $this->getConnection()->compileQuery($this);
    }

    /**
     * 执行当前query
     * @return int|\PDOStatement
     */
    public function execute()
    {
        return $this->connection->run($this);
    }
}