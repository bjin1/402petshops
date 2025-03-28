<?php

//header('content-type:text/html;charset=utf-8');
class PDOMySQL
{
    static $__author__ = 'Frankie';

    static $configs = array(); // 设置连接参数，配置信息(数组)
    static $links = array(); // 保存连接标识符(数组)
    static $NumberLink = 0; // 保存数据库连接数量/配置信息数量

    private $current = 0; //标识当前对应的数据库配置，可以是数字或者字符串
    private $config = array(); //保存当前模型的数据库配置
    private $link = null; //保存当前模型的数据库连接标识符

    private $dbdebug = false; //是否开启DEBUG模式

    private $table = ''; //记录操作的数据表名
    private $columns = array(); //记录表中字段名
    private $dbVersion = null; //保存数据库版本
    private $connected = false; //是否连接成功
    private $PDOStatement = null; //保存PDOStatement对象
    private $queryStr = null; //保存最后执行的操作
    private $SQLerror = null; //报错错误信息
    private $lastInsertId = null; //保存上一步插入操作产生AUTO_INCREMENT
    private $numRows = 0; //上一步操作产生受影响的记录的条数

    private $tmp_table = '';
    private $aliasString = '';
    private $fieldString = '';
    private $joinString = '';
    private $whereString = '';
    private $groupString = '';
    private $havingString = '';
    private $orderString = '';
    private $limitString = '';
    private $lockString = '';
    private $fetchSql = false;

    private $whereStringArray = array();
    private $whereValueArray = array();

    private $SQL_logic = array('AND', 'OR', 'XOR'); //SQL语句支持的逻辑运算符

    /**
     * 构造函数，连接PDO
     * @param string $dbtable
     * @param int/string $ConfigID
     * @param array $dbConfig
     * @return boolean
     * $dbConfig数组至少需要database
     * 如果想开启debug模式，指定 $dbConfig["DB_DEBUG"] = true
     * 可以通过 $dbConfig["MYSQL_LOG"] = '/path/to/mysql.log' 指定mysql的日志文件路径
     */
    public function __construct($dbtable, $ConfigID = 0, $dbConfig = null) {
        if (!class_exists("PDO")) {
            $this->throw_exception("不支持PDO，请先开启", true);
            return false;
        }
        if (!is_integer($ConfigID) && !is_string($ConfigID)) {
            $this->throw_exception("第二个参数只能是数字或字符串", true);
            return false;
        }
        # 如果数据库配置已被存在self::$configs中时
        if (isset(self::$configs[$ConfigID])) {
            if ($dbConfig != null) {
                $this->throw_exception('数据库配置编号' . $ConfigID . '已被占用', true);
                return false;
            }
            $this->init($ConfigID, $dbtable);
            return true;
        }
        # 以下为数据库配置还未被存在self::$configs中时
        if ($dbConfig == null) {
            if (!defined('CONFIG')) {
                $this->throw_exception("配置文件未定义CONFIG", true);
                return false;
            }
            # 检查配置文件中是否有对应的配置信息
            if (!isset(CONFIG[$ConfigID])) {
                $this->throw_exception("配置文件中无" . $ConfigID . "的配置信息", true);
                return false;
            }
            # 使用配置文件中对应的配置
            if ($ConfigID === 0) {
                $dbConfig = CONFIG[0];
            } else {
                $default_dbConfig = CONFIG[0];
                $dbConfig = array_merge($default_dbConfig, CONFIG[$ConfigID]);
            }
        }
        if (isset($dbConfig['DB_DEBUG']) && $dbConfig['DB_DEBUG'] === true) {
            $this->dbdebug = true;
        }
        if (empty($dbConfig['password'])) {
            if (isset(CONFIG[0]['password'])) {
                $dbConfig['password'] = CONFIG[0]['password'];
            } else {
                $this->throw_exception('数据库未设置密码');
                return false;
            }
        }
        if (empty($dbConfig['hostname'])) {
            $dbConfig['hostname'] = '127.0.0.1';
        }
        if (empty($dbConfig['username'])) {
            $dbConfig['username'] = 'root';
        }
        if (empty($dbConfig['hostport'])) {
            $dbConfig['hostport'] = '3306';
        }
        if (empty($dbConfig['dbms'])) {
            $dbConfig['dbms'] = 'mysql';
        }
        if (empty($dbConfig['params'])) {
            $dbConfig['params'] = array();
        }
        $dbConfig['dsn'] = $dbConfig['dbms'] . ':host=' . $dbConfig['hostname'] . ';port=' . $dbConfig['hostport'] . ';dbname=' . $dbConfig['database'];
        self::$configs[$ConfigID] = $dbConfig;
        $this->current = $ConfigID;
        $this->config = $dbConfig;
        if (isset($dbConfig['pconnect']) && $dbConfig['pconnect'] === true) {
            //开启长连接，添加到配置数组中
            $dbConfig['params'][constant("PDO::ATTR_PERSISTENT")] = true;
        }
        try {
            $this->link = new PDO($dbConfig['dsn'], $dbConfig['username'], $dbConfig['password'], $dbConfig['params']);
        } catch (PDOException $e) {
            $this->throw_exception($e->getMessage());
            return false;
        }
        # 设置 $this->link, $this->table, $this->dbVersion, $this->connected, 以及设置数据库编码
        if ($this->link) {
            self::$links[$ConfigID] = $this->link;
        } else {
            $this->throw_exception('PDO连接错误');
            return false;
        }
        if ($this->in_db($dbtable)) {
            $this->table = $dbtable;
        } else {
            $this->throw_exception('数据库' . $dbConfig['database'] . '中不存在' . $dbtable . '表');
            return false;
        }
        if (!empty($dbConfig['charset'])) {
            $this->link->exec('SET NAMES ' . $dbConfig['charset']);
        }
        $this->dbVersion = $this->link->getAttribute(constant("PDO::ATTR_SERVER_VERSION"));
        $this->connected = true;
        # 最后将数据库连接数 + 1
        self::$NumberLink++;
    }

    /**
     * 初始化当前模型的参数
     */
    private function init($current, $dbtable) {
        $this->current = $current;
        $this->config = self::$configs[$current];
        $this->link = self::$links[$current];
        if (isset($this->config['DB_DEBUG']) && $this->config['DB_DEBUG'] === true) {
            $this->dbdebug = true;
        }
        if ($this->in_db($dbtable)) {
            $this->table = $dbtable;
        } else {
            $this->throw_exception('数据库' . $this->config['database'] . '中不存在' . $dbtable . '表');
            return false;
        }
        $this->dbVersion = $this->link->getAttribute(constant("PDO::ATTR_SERVER_VERSION"));
        $this->connected = true;
    }

    /**
     * 判断数据表是否存在
     * @param string $dbtable
     * @return boolean
     */
    private function in_db($dbtable) {
        $stmt = $this->link->query("SHOW TABLES");
        foreach ($stmt as $row) {
            if ($dbtable == $row[0]) {
                return true;
            }
        }
        return false;
    }

    /**
     * 初始化时获取数据表字段，标注主键，存储在$this->columns中
     * @param string $dbtable
     */
    private function set_columns($dbtable) {
        $stmt = $this->link->query("SHOW COLUMNS FROM `" . $dbtable . "`");
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $array) {
            if ($array['Key'] == 'PRI') {
                $this->columns['PRI'] = $array['Field'];
            }
            $this->columns[] = $array['Field'];
        }
    }

    /**
     * 解析where子句(懒得支持传对象参数)
     * @param string/array/Variable-length_argument_lists $where
     * @return $this
     */
    public function where() {
        // 可变数量的参数列表是PHP5.6+的特性，使用func_get_args()兼容PHP5.6-的版本
        $where = func_get_args();
        $param_number = count($where);
        if (!is_string($where[0]) && !is_array($where[0])) {
            $this->throw_exception("where子句的参数只支持字符串和数组");
            return false;
        }
        if (is_string($where[0])) {
            if ($param_number == 1) {
                $whereSubString = '( ' . $where[0] . ' )';
            } elseif ($param_number > 1) {
                if (is_array($where[1])) {
                    $whereSubString = vsprintf($where[0], $where[1]);
                } else {
                    $param_array = array();
                    for ($i = 1; $i < $param_number; $i++) {
                        $param_array[] = $where[$i];
                    }
                    $whereSubString = vsprintf($where[0], $param_array);
                    // 或者 $whereSubString = sprintf($where[0], ...$param_array);
                }
                $whereSubString = '( ' . $whereSubString . ' )';
            }
        } elseif (is_array($where[0])) {
            if (count($where[0]) == 0) {
                return $this;
            }
            $whereSubString = $this->parseWhereArrayParam($where[0]);
        }
        $this->whereStringArray[] = $whereSubString;
        return $this;
    }

    /**
     * 总拼接where子句的SQL字符串
     */
    public function parseWhere() {
        $length = count($this->whereStringArray);
        if ($length == 0) {
            return;
        }
        if ($length > 1) {
            $this->whereString = ' WHERE ( ' . $this->whereStringArray[0] . ' )';
            for ($i = 1; $i < $length; $i++) {
                $this->whereString .= ' AND ( ' . $this->whereStringArray[$i] . ' )';
            }
        } else {
            $this->whereString = ' WHERE ' . $this->whereStringArray[0];
        }
    }

    /**
     * 解析table子句
     * @param string/array $table
     * @return $this
     */
    public function table($table) {
        if (is_string($table)) {
            $this->tmp_table = $table;
        } elseif (is_array($table)) {
            if (count($table) == 0) {
                $this->throw_exception('table子句参数不能传空数组');
                return false;
            }
            $this->tmp_table = '';
            foreach ($table as $key => $val) {
                if (is_string($key)) {
                    $match_times = preg_match('/\./', $key);
                    if (0 === $match_times) {
                        $this->tmp_table .= '`' . trim($key) . '` AS `' . trim($val) . '`,';
                    } elseif (1 === $match_times) {
                        $this->tmp_table .= trim($key) . ' AS `' . trim($val) . '`,';
                    } else {
                        $this->throw_exception('table子句数组参数的键值非法："' . $key . '"');
                        return false;
                    }
                } else {
                    $match_times = preg_match('/\./', $val);
                    if (0 === $match_times) {
                        $this->tmp_table .= '`' . trim($val) . '`,';
                    } elseif (1 === $match_times) {
                        $this->tmp_table .= trim($val) . ',';
                    } else {
                        $this->throw_exception('table子句数组参数的键值非法："' . $val . '"');
                        return false;
                    }
                }
            }
            $this->tmp_table = rtrim($this->tmp_table, ',');
        } else {
            $this->throw_exception('table子句的参数类型错误："' . $table . '"');
            return false;
        }
        return $this;
    }

    /**
     * 解析alias子句
     * @param string $alias
     * @return $this
     */
    public function alias($alias) {
        if (is_string($alias) && $alias != '') {
            $this->aliasString = ' AS `' . $alias . '`';
        } else {
            $this->throw_exception('alias子句的参数须是字符串');
            return false;
        }
        return $this;
    }

    /**
     * 解析field子句
     * @param string/array $field
     * @param boolean $filter
     * @return $this
     */
    public function field($field = '', $filter = false) {
        if ($field === true) {
            //显示调用所有字段
            $this->set_columns($this->tmp_table === '' ? $this->table : $this->tmp_table);
            $columns_array = $this->columns;
            unset($columns_array['PRI']);
            $this->fieldString .= ' ';
            foreach ($columns_array as $key => $val) {
                $this->fieldString .= '`' . $val . '`,';
            }
            $this->fieldString = rtrim($this->fieldString, ',');
            return $this;
        }
        if ($filter === true) {
            if (!is_string($field) && !is_array($field)) {
                $this->throw_exception("field子句的参数只支持字符串和数组");
                return false;
            }
            $this->set_columns($this->tmp_table === '' ? $this->table : $this->tmp_table);
            $columns_array = $this->columns;
            unset($columns_array['PRI']);
            $explode_array = array();
            if (is_string($field)) {
                $explode_array = preg_split('/\s{0,},\s{0,}/', trim($field));
            } elseif (is_array($field)) {
                foreach ($field as $key => $val) {
                    $explode_array[] = trim($val);
                }
            }
            foreach ($columns_array as $key => $val) {
                if (in_array($val, $explode_array)) {
                    unset($columns_array[$key]);
                }
            }
            foreach ($columns_array as $key => $val) {
                $this->fieldString .= '`' . $val . '`,';
            }
            $this->fieldString = rtrim($this->fieldString, ',');
            $this->fieldString = ' ' . $this->fieldString;
            return $this;
        }
        if ($field === '' || $field === '*') {
            $this->fieldString = ' *';
            return $this;
        }
        if (!is_string($field) && !is_array($field)) {
            $this->throw_exception("field子句的参数只支持字符串和数组");
            return false;
        }
        if (is_array($field)) {
            foreach ($field as $key => $val) {
                if (is_int($key)) {
                    $after_process_val = $this->addSpecialChar($val);
                    $this->fieldString .= $after_process_val . ',';
                } else {
                    $after_process_key = $this->addSpecialChar($key);
                    $after_process_val = $this->addSpecialChar($val);
                    $this->fieldString .= $after_process_key . ' AS ' . $after_process_val . ',';
                }
            }
            $this->fieldString = rtrim($this->fieldString, ',');
        }
        if (is_string($field)) {
            $field_array = explode(',', $field);
            $length = count($field_array);
            for ($i = 0; $i < $length; $i++) {
                $field_array[$i] = $this->addSpecialChar($field_array[$i]);
            }
            $this->fieldString = implode(',', $field_array);
        }
        $this->fieldString = ' ' . $this->fieldString;
        return $this;
    }

    /**
     * 解析order子句
     * @param string/array $order
     * @return $this
     */
    public function order($order) {
        if (!is_string($order) && !is_array($order)) {
            $this->throw_exception("order子句的参数只支持字符串和数组");
            return false;
        }
        if (is_string($order)) {
            $this->orderString = ' ORDER BY ' . $order;
        }
        if (is_array($order)) {
            $this->orderString = ' ORDER BY ';
            foreach ($order as $key => $val) {
                if (is_int($key)) {
                    $this->orderString .= '`' . trim($val) . '`,';
                } else {
                    if (strtolower($val) != 'desc' && strtolower($val) != 'asc') {
                        $this->throw_exception("order子句请使用desc或asc关键词指定排序，默认为asc，出现未知字符");
                        $this->orderString = '';
                        return false;
                    }
                    $this->orderString .= '`' . trim($key) . '` ' . $val . ',';
                }
            }
            $this->orderString = rtrim($this->orderString, ',');
        }
        return $this;
    }

    /**
     * 解析limit子句
     * @param int/string/Variable-length_argument_lists $limit
     * @return $this
     * 示例：limit(10)/limit('10,25')/limit(10,25)
     */
    public function limit() {
        // 可变数量的参数列表是PHP5.6+的特性，使用func_get_args()兼容PHP5.6-的版本
        $limit = func_get_args();
        $param_number = count($limit);
        if ($param_number == 1) {
            if (!is_int($limit[0]) && !is_string($limit[0])) {
                $this->throw_exception("limit子句的参数非法");
                return false;
            }
            if (is_string($limit[0])) {
                if (preg_match('/^\d+\s{0,},\s{0,}\d+$/', trim($limit[0])) == 0 && preg_match('/^\d+$/', trim($limit[0])) == 0) {
                    $this->throw_exception("limit子句的参数非法");
                    return false;
                }
            }
            $this->limitString = ' LIMIT ' . $limit[0];
        } elseif ($param_number == 2) {
            for ($i = 0; $i < 2; $i++) {
                if (!is_int($limit[$i])) {
                    $this->throw_exception("limit子句的参数非法");
                    return false;
                }
            }
            $this->limitString = ' LIMIT ' . $limit[0] . ',' . $limit[1];
        } else {
            $this->throw_exception("limit子句的参数数量必须为一或两个");
            return false;
        }
        return $this;
    }

    /**
     * 解析page子句
     * @param int $page_number
     * @param int $amount
     * @return $this
     * 示例：page(2,10)，只支持两个数字参数的写法，此处表示取出第11-20条数据（页码为2，单页显示量,10）
     * 不支持limit和page配合使用
     */
    public function page($page_number, $amount) {
        if (!is_numeric($page_number) || !is_numeric($amount)) {
            $this->throw_exception("page方法只支持两个数字参数的写法");
            return false;
        }
        $start = ($page_number - 1) * $amount;
        $this->limitString = ' LIMIT ' . $start . ',' . $amount;
        return $this;
    }

    /**
     * 解析group子句
     * @param string $group
     * @return $this
     */
    public function group($group) {
        if (!is_string($group)) {
            $this->throw_exception("group子句的参数只支持字符串");
            return false;
        }
        $this->groupString = ' GROUP BY ' . $group;
        return $this;
    }

    /**
     * 解析having子句
     * @param string $having
     * @return $this
     */
    public function having($having) {
        if (!is_string($having)) {
            $this->throw_exception("having子句的参数只支持字符串");
            return false;
        }
        $this->havingString = ' HAVING BY ' . $having;
        return $this;
    }

    /**
     * 解析join子句
     * 传字符串默认INNER  JOIN，传数组时第二个元素指定"LEFT""RIGHT""FULL"进行左右全连接的设置
     * 与ThinkPHP有差异
     * @param string $join
     * @return $this
     */
    public function join($join) {
        if (!is_string($join) && !is_array($join)) {
            $this->throw_exception("join子句的参数只支持字符串和数组");
            return false;
        }
        if (is_string($join)) {
            $this->joinString .= ' INNER JOIN ' . $join;
        } else {
            if (!is_string($join[0]) || !is_string($join[1])) {
                $this->throw_exception("join子句中的数组参数的前两个元素必须都是字符串");
                return false;
            }
            $this->joinString .= ' ' . $join[1] . ' JOIN ' . $join[0];
        }
        return $this;
    }

    /**
     * fetchSql用于直接返回SQL而不是执行查询,适用于任何的CURD操作方法
     * @param boolean $fetchSql
     * @return $this
     */
    public function fetchSql($fetchSql = false) {
        $this->fetchSql = $fetchSql;
        return $this;
    }

    /**
     * 加行锁（共享锁或排他锁）
     * 仅支持 InnoDB 存储引擎，仅支持 SELECT 语句，且须在事务块中才能生效
     * @param string $pattern 'S' 或 'X' 分别代表共享锁和排他锁
     */
    public function lock($pattern) {
        $pattern = strtoupper($pattern);
        if ($pattern == 'S') {
            $this->lockString = ' LOCK IN SHARE MODE';
        } elseif ($pattern == 'X') {
            $this->lockString = ' FOR UPDATE';
        }
        return $this;
    }

    /**
     * 统计查询之计数/count
     * @param string $field
     * @return number
     * 示例：SELECT COUNT(*) AS tp_count FROM `users` LIMIT 1
     *      SELECT COUNT(id) AS tp_count FROM `users` LIMIT 1
     */
    public function count($field = '*') {
        $this->fieldString = ' COUNT(' . $field . ') AS f_count';
        $this->limitString = ' LIMIT 1';
        $is_fetchSql = false;
        if ($this->fetchSql == true) {
            $is_fetchSql = true;
        }
        $res = $this->select();
        if ($is_fetchSql) {
            return $res;
        } else {
            return $res[0]['f_count'];
        }
    }

    /**
     * 统计查询之获取最大值/max
     * @param string $field
     * @return number
     * 示例：SELECT MAX(id) AS tp_max FROM `users` LIMIT 1
     */
    public function max($field) {
        $this->fieldString = ' MAX(' . $field . ') AS f_max';
        $this->limitString = ' LIMIT 1';
        $is_fetchSql = false;
        if ($this->fetchSql == true) {
            $is_fetchSql = true;
        }
        $res = $this->select();
        if ($is_fetchSql) {
            return $res;
        } else {
            return $res[0]['f_max'];
        }
    }

    /**
     * 统计查询之获取最小值/min
     * @param string $field
     * @return number
     * 示例：SELECT MIN(id) AS tp_min FROM `test` WHERE ( id>34 ) LIMIT 1
     */
    public function min($field) {
        $this->fieldString = ' MIN(' . $field . ') AS f_min';
        $this->limitString = ' LIMIT 1';
        $is_fetchSql = false;
        if ($this->fetchSql == true) {
            $is_fetchSql = true;
        }
        $res = $this->select();
        if ($is_fetchSql) {
            return $res;
        } else {
            return $res[0]['f_min'];
        }
    }

    /**
     * 统计查询之获取平均值/avg
     * @param string $field
     * @return number
     * 示例：SELECT AVG(id) AS tp_avg FROM `test` LIMIT 1
     */
    public function avg($field) {
        $this->fieldString = ' AVG(' . $field . ') AS f_avg';
        $this->limitString = ' LIMIT 1';
        $is_fetchSql = false;
        if ($this->fetchSql == true) {
            $is_fetchSql = true;
        }
        $res = $this->select();
        if ($is_fetchSql) {
            return $res;
        } else {
            return $res[0]['f_avg'];
        }
    }

    /**
     * 统计查询之求和/sum
     * @param string $field
     * @return number
     */
    public function sum($field) {
        $this->fieldString = ' SUM(' . $field . ') AS f_sum';
        $this->limitString = ' LIMIT 1';
        $is_fetchSql = false;
        if ($this->fetchSql == true) {
            $is_fetchSql = true;
        }
        $res = $this->select();
        if ($is_fetchSql) {
            return $res;
        } else {
            return $res[0]['f_sum'];
        }
    }

    /**
     * buildSql:构建select的SQL语句，用于子查询
     * @return string
     */
    public function buildSql() {
        $sqlString = '';
        if ($this->tmp_table != '') {
            $table_name = $this->addSpecialChar_for_pure_string($this->tmp_table) . $this->aliasString;
        } else {
            $table_name = '`' . $this->table . '`' . $this->aliasString;
        }
        $this->fieldString = $this->fieldString == '' ? ' *' : $this->fieldString;
        $this->parseWhere();
        $sqlString .= 'SELECT' . $this->fieldString . ' FROM ' . $table_name . $this->joinString . $this->whereString . $this->groupString . $this->havingString . $this->orderString . $this->limitString . $this->lockString;
        $buildSql = $this->replaceSpecialChar('/\?/', $this->whereValueArray, $sqlString);
        $this->clearSubString();
        return '( ' . $buildSql . ' )';
    }

    /**
     * find方法/查询数据(一条)
     * @param $primary_key_value 用于主键查询
     * @return array 查询成功返回数据(数组),查无返回NULL，查询出错返回false
     */
    public function find($primary_key_value = '') {
        $sqlString = '';
        if ($this->tmp_table != '') {
            $table_name = $this->addSpecialChar_for_pure_string($this->tmp_table) . $this->aliasString;
        } else {
            $table_name = '`' . $this->table . '`' . $this->aliasString;
        }
        if ($primary_key_value != '') {
            $this->set_columns($this->tmp_table === '' ? $this->table : $this->tmp_table);
            $this->whereStringArray[] = '`' . $this->columns['PRI'] . '` = ?';
            $this->whereValueArray[] = $primary_key_value;
        }
        $this->limitString = ' LIMIT 1';
        $this->fieldString = $this->fieldString == '' ? ' *' : $this->fieldString;
        $this->parseWhere();
        $sqlString .= 'SELECT' . $this->fieldString . ' FROM ' . $table_name . $this->joinString . $this->whereString . $this->groupString . $this->havingString . $this->orderString . $this->limitString . $this->lockString;
        $res = $this->query($sqlString, null, true);
        return $res;
    }

    /**
     * select方法/查询数据集
     * @param $query =true 是否进行查询/否则仅构建SQL
     * @return array/string 查询成功返回数据(二维数组),查无返回NULL，查询出错返回false
     */
    public function select($query = true) {
        $sqlString = '';
        if ($this->tmp_table != '') {
            $table_name = $this->addSpecialChar_for_pure_string($this->tmp_table) . $this->aliasString;
        } else {
            $table_name = '`' . $this->table . '`' . $this->aliasString;
        }
        $this->fieldString = $this->fieldString == '' ? ' *' : $this->fieldString;
        $this->parseWhere();
        $sqlString .= 'SELECT' . $this->fieldString . ' FROM ' . $table_name . $this->joinString . $this->whereString . $this->groupString . $this->havingString . $this->orderString . $this->limitString . $this->lockString;
        if (false === $query) {
            $this->fetchSql = true;
        }
        $res = $this->query($sqlString);
        return $res;
    }

    /**
     * add方法/插入一条数据
     * @param array $data
     * @return 插入成功返回id值，失败返回false
     */
    public function add($data = '') {
        $field_str = '';
        if ($data != '') {
            if (!is_array($data)) {
                $this->throw_exception('add方法只支持传入数组');
                return false;
            }
            $length = count($data);
            if ($length === 0) {
                $placeholder = '';
            } else {
                foreach ($data as $key => $val) {
                    $field_str .= '`' . $key . '`,';
                    $this->whereValueArray[] = $val;
                }
                $field_str = rtrim($field_str, ',');
                $placeholder = '?';
                for ($i = 1; $i < $length; $i++) {
                    $placeholder .= ',?';
                }
            }
        } else {
            $placeholder = '';
        }
        if ($this->tmp_table != '') {
            $table_name = $this->addSpecialChar_for_pure_string($this->tmp_table);
        } else {
            $table_name = '`' . $this->table . '`';
        }
        $sqlString = 'INSERT INTO ' . $table_name . ' (' . $field_str . ') VALUES (' . $placeholder . ')';
        $res = $this->execute($sqlString);
        if ($res === false) {
            return false;
        } elseif (is_string($res)) {
            return $res;
        }
        $this->lastInsertId = $this->link->lastInsertId();
        return $this->lastInsertId;
    }

    /**
     * addAll方法/批量写入数据
     * @param array $dataList
     * @return 插入成功返回id值(第一条插入数据的id值)，失败返回false
     * 示例：INSERT INTO `users` (`user_id`,`password`) VALUES ('thinkphp','thinkphp@gamil.com')
     *      INSERT INTO `users` (`user_id`,`password`) VALUES ('thinkphp','thinkphp@gamil.com'),('onethink','onethink@gamil.com')
     */
    public function addAll($dataList) {
        if (!is_array($dataList)) {
            $this->throw_exception('addAll方法只支持传入数组');
            return false;
        }
        $field_str = '';
        $fieldList = array();
        $number = count($dataList);
        $valueListStr = '';
        if ($number === 0) {
            $this->throw_exception('addAll方法请勿传入空数组');
            return false;
        }
        if (!isset($dataList[$number - 1])) {
            $this->throw_exception('addAll方法传入的二维数组参数非法(须是索引数组)');
            return false;
        }
        if (!is_array($dataList[0])) {
            $this->throw_exception('addAll方法传入的二维数组参数非法(数组第一个元素非数组)');
            return false;
        }
        $number_field = count($dataList[0]);
        if ($number_field == 0) {
            $valueListStr .= '()';
            for ($i = 1; $i < $number; $i++) {
                if ($dataList[$i] != array()) {
                    $this->throw_exception('addAll方法传入的二维数组参数非法');
                    return false;
                }
                $valueListStr .= ',()';
            }
        } else {
            $valueStr = '(';
            foreach ($dataList[0] as $key => $val) {
                $fieldList[] = $key;
                $this->whereValueArray[] = $val;
                $field_str .= $key . ',';
                $valueStr .= '?,';
            }
            $field_str = rtrim($field_str, ',');
            $valueStr = rtrim($valueStr, ',');
            $valueStr .= ')';
            $valueListStr .= $valueStr;
            for ($i = 1; $i < $number; $i++) {
                for ($j = 0; $j < $number_field; $j++) {
                    $this->whereValueArray[] = $dataList[$i][$fieldList[$j]];
                }
                $valueListStr .= ',' . $valueStr;
            }
        }
        if ($this->tmp_table != '') {
            $table_name = $this->addSpecialChar_for_pure_string($this->tmp_table);
        } else {
            $table_name = '`' . $this->table . '`';
        }
        $sqlString = 'INSERT INTO ' . $table_name . ' (' . $field_str . ') VALUES ' . $valueListStr;
        $res = $this->execute($sqlString);
        if ($res === false) {
            return false;
        } elseif (is_string($res)) {
            return $res;
        }
        $this->lastInsertId = $this->link->lastInsertId();
        return $this->lastInsertId;
    }

    /**
     * setField方法/更新字段
     * @param array/string/Variable-length_argument_lists $field
     * @return 更新成功返回影响的记录数，没有更新数据返回0，更新过程出错返回false
     * 示例：update users inner join test set user_id='update' where users.id = test.id;
     */
    public function setField() {
        // 可变数量的参数列表是PHP5.6+的特性，使用func_get_args()兼容PHP5.6-的版本
        $field = func_get_args();
        $param_number = count($field);
        if ($field === 0) {
            $this->throw_exception('setField子句须传入参数');
            return false;
        }
        $this->parseWhere();
        if ($this->whereString == '') {
            $this->set_columns($this->tmp_table === '' ? $this->table : $this->tmp_table);
            if (is_array($field[0]) && isset($this->columns['PRI']) && isset($field[0][$this->columns['PRI']])) {
                if (is_array($field[0][$this->columns['PRI']])) {
                    if (strtoupper($field[0][$this->columns['PRI']][0]) == 'EXP') {
                        $this->whereString = ' WHERE `' . $this->columns['PRI'] . '` = ' . trim($field[0][$this->columns['PRI']][1]);
                    } else {
                        $this->throw_exception('setField子句仅支持exp表达式更新');
                        return false;
                    }
                } else {
                    $this->whereString = ' WHERE `' . $this->columns['PRI'] . '` = ?';
                    $this->whereValueArray[] = $field[0][$this->columns['PRI']];
                }
                unset($field[0][$this->columns['PRI']]);
            } elseif (!isset($this->columns['PRI'])) {
                $this->throw_exception('没有任何更新条件，且指定数据表无主键，不被允许执行更新操作');
                return false;
            } else {
                $this->throw_exception('没有任何更新条件，数据对象本身也不包含主键字段，不被允许执行更新操作');
                return false;
            }
        }
        $setFieldStr = '';
        $updateValueArray = array();
        if (is_string($field[0])) {
            if ($param_number != 2) {
                $this->throw_exception('setField子句接收两个参数（属性名，属性值）');
                return false;
            }
            if (strpos($field[0], '.') === false) {
                $setFieldStr .= '`' . trim($field[0]) . '` = ?';
            } else {
                $setFieldStr .= trim($field[0]) . ' = ?';
            }
            $updateValueArray[] = $field[1];
        } elseif (is_array($field[0])) {
            if ($param_number != 1) {
                $this->throw_exception('setField子句只接收一个数组参数');
                return false;
            }
            foreach ($field[0] as $key => $val) {
                if (is_array($val)) {
                    if (strtoupper($val[0]) == 'EXP') {
                        if (strpos($key, '.') === false) {
                            $setFieldStr .= '`' . trim($key) . '` = ' . trim($val[1]) . ',';
                        } else {
                            $setFieldStr .= trim($key) . ' = ' . trim($val[1]) . ',';
                        }
                    } else {
                        $this->throw_exception('setField子句仅支持exp表达式更新');
                        return false;
                    }
                } else {
                    if (strpos($key, '.') === false) {
                        $setFieldStr .= '`' . trim($key) . '` = ?,';
                    } else {
                        $setFieldStr .= trim($key) . ' = ?,';
                    }
                    $updateValueArray[] = $val;
                }
            }
            $setFieldStr = rtrim($setFieldStr, ',');
        } else {
            $this->throw_exception('setField子句传入的参数类型错误：' . $field[0]);
            return false;
        }
        $this->whereValueArray = array_merge($updateValueArray, $this->whereValueArray);
        if ($this->tmp_table != '') {
            $table_name = $this->addSpecialChar_for_pure_string($this->tmp_table) . $this->aliasString;
        } else {
            $table_name = '`' . $this->table . '`' . $this->aliasString;
        }
        $sqlString = 'UPDATE ' . $table_name . $this->joinString . ' SET ' . $setFieldStr . $this->whereString . $this->orderString . $this->limitString;
        $res = $this->execute($sqlString);
        return $res;
    }

    /**
     * setInc方法/字段自增$value(默认1)
     * @param string $field
     * @param int $value
     * @return 更新成功返回影响的记录数，没有更新数据返回0，更新过程出错返回false
     * 示例：UPDATE `users` SET `id`=id+4 WHERE ( password="afad" )
     */
    public function setInc($field, $value = 1) {
        $data[$field] = array('EXP', $field . ' + ' . $value);
        return $this->save($data);
    }

    /**
     * setDec方法/字段自减$value(默认1)
     * @param string $field
     * @param int $value
     * @return 更新成功返回影响的记录数，没有更新数据返回0，更新过程出错返回false
     */
    public function setDec($field, $value = 1) {
        $data[$field] = array('EXP', $field . ' - ' . $value);
        return $this->save($data);
    }

    /**
     * save方法/更新数据
     * @param array $data
     * @return 更新成功返回影响的记录数，没有更新数据返回0，更新过程出错返回false
     */
    public function save($data) {
        if (!is_array($data)) {
            $this->throw_exception('save子句只接收数组参数');
            return false;
        }
        $this->parseWhere();
        if ($this->whereString == '') {
            $this->set_columns($this->tmp_table === '' ? $this->table : $this->tmp_table);
            if (isset($this->columns['PRI']) && isset($data[$this->columns['PRI']])) {
                if (is_array($data[$this->columns['PRI']])) {
                    if (strtoupper($data[$this->columns['PRI']][0]) == 'EXP') {
                        $this->whereString = ' WHERE `' . $this->columns['PRI'] . '` = ' . trim($data[$this->columns['PRI']][1]);
                    } else {
                        $this->throw_exception('save子句仅支持exp表达式更新');
                        return false;
                    }
                } else {
                    $this->whereString = ' WHERE `' . $this->columns['PRI'] . '` = ?';
                    $this->whereValueArray[] = $data[$this->columns['PRI']];
                }
                unset($data[$this->columns['PRI']]);
            } elseif (!isset($this->columns['PRI'])) {
                $this->throw_exception('没有任何更新条件，且指定数据表无主键，不被允许执行更新操作');
                return false;
            } else {
                $this->throw_exception('没有任何更新条件，数据对象本身也不包含主键字段，不被允许执行更新操作');
                return false;
            }
        }
        $setFieldStr = '';
        $updateValueArray = array();
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                //支持exp表达式进行数据更新
                if (strtoupper($val[0]) == 'EXP') {
                    if (strpos($key, '.') === false) {
                        $setFieldStr .= '`' . trim($key) . '` = ' . trim($val[1]) . ',';
                    } else {
                        $setFieldStr .= trim($key) . ' = ' . trim($val[1]) . ',';
                    }
                } else {
                    $this->throw_exception('save子句仅支持exp表达式更新');
                    return false;
                }
            } else {
                if (strpos($key, '.') === false) {
                    $setFieldStr .= '`' . trim($key) . '` = ?,';
                } else {
                    $setFieldStr .= trim($key) . ' = ?,';
                }
                $updateValueArray[] = $val;
            }
        }
        $setFieldStr = rtrim($setFieldStr, ',');
        $this->whereValueArray = array_merge($updateValueArray, $this->whereValueArray);
        if ($this->tmp_table != '') {
            $table_name = $this->addSpecialChar_for_pure_string($this->tmp_table) . $this->aliasString;
        } else {
            $table_name = '`' . $this->table . '`' . $this->aliasString;
        }
        $sqlString = 'UPDATE ' . $table_name . $this->joinString . ' SET ' . $setFieldStr . $this->whereString . $this->orderString . $this->limitString;
        $res = $this->execute($sqlString);
        return $res;
    }

    /**
     * delete方法/删除数据
     * @param $table 用于指定主键，删除对应数据
     * @return 删除成功返回影响的记录数，没有删除数据返回0，出错返回false
     */
    public function delete($table = '') {
        $sqlString = '';
        if ($this->tmp_table != '') {
            $table_name = $this->addSpecialChar_for_pure_string($this->tmp_table) . $this->aliasString;
        } else {
            $table_name = '`' . $this->table . '`' . $this->aliasString;
        }
        if ($table != '') {
            $table = ' ' . $table;
        }
        $this->parseWhere();
        if ($this->whereString == '') {
            if ($this->joinString == '' || stripos($this->joinString, ' on ') === false) {
                $this->throw_exception('没有传入任何条件，不被允许执行删除操作');
                return false;
            }
        }
        $sqlString = 'DELETE' . $table . ' FROM ' . $table_name . $this->joinString . $this->whereString . $this->orderString . $this->limitString;
        $res = $this->execute($sqlString);
        return $res;
    }

    /**
     * query方法/用于SQL查询
     * @param string $queryStr
     * @param null/array $paramsArray
     * @param boolean $is_find 指定是否find方法，是则只返回第一条数据
     * @return array 返回查询到的数据
     */
    public function query($queryStr, $paramsArray = null, $is_find = false) {
        if (!is_string($queryStr)) {
            $this->throw_exception('query查询须传入字符串');
            return false;
        }
        if ($paramsArray != null) {
            $this->whereValueArray = $paramsArray;
        }
        if ($this->fetchSql === true) {
            $buildSql = $this->replaceSpecialChar('/\?/', $this->whereValueArray, $queryStr);
            $this->clearSubString();
            return $buildSql;
        }
        $this->PDOStatement = $this->link->prepare($queryStr);
        if (count($this->whereValueArray) > 0) {
            $this->PDOStatement->execute($this->whereValueArray);
        } else {
            $this->PDOStatement->execute();
        }
        $this->queryStr = $this->replaceSpecialChar('/\?/', $this->whereValueArray, $queryStr);
        $this->clearSubString();
        $haveError = $this->haveErrorThrowException();
        if (false === $haveError) {
            return false;
        }
        if ($is_find === true) {
            $res = $this->PDOStatement->fetch(PDO::FETCH_ASSOC);
            if (false === $res) {
                return null;
            }
        } else {
            $res = $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);
            if (0 === count($res)) {
                return null;
            }
        }
        return $res;
    }

    /**
     * execute方法/用于SQL查询
     * @param string $execStr
     * @param null/array $paramsArray
     * @return int 返回影响的记录数
     */
    public function execute($execStr, $paramsArray = null) {
        if (!is_string($execStr)) {
            $this->throw_exception('execute查询须传入字符串');
            return false;
        }
        if ($paramsArray != null) {
            $this->whereValueArray = $paramsArray;
        }
        if ($this->fetchSql === true) {
            $buildSql = $this->replaceSpecialChar('/\?/', $this->whereValueArray, $execStr);
            $this->clearSubString();
            return $buildSql;
        }
        $this->PDOStatement = $this->link->prepare($execStr);
        if (count($this->whereValueArray) > 0) {
            $this->PDOStatement->execute($this->whereValueArray);
        } else {
            $this->PDOStatement->execute();
        }
        $this->queryStr = $this->replaceSpecialChar('/\?/', $this->whereValueArray, $execStr);
        $this->clearSubString();
        $haveError = $this->haveErrorThrowException();
        if (false === $haveError) {
            return false;
        }
        $this->numRows = $this->PDOStatement->rowCount();
        return $this->numRows;
    }

    /**
     * 开启事务
     */
    public function startTrans() {
        foreach (self::$links as $link) {
            $link->beginTransaction();
        }
    }

    /**
     * 检查是否在一个事务内
     * @return boolean
     */
    public function inTrans() {
        return $this->link->inTransaction();
    }

    /**
     * 事务回滚
     */
    public function rollback() {
        foreach (self::$links as $link) {
            $link->rollBack();
        }
    }

    /**
     * 提交事务
     */
    public function commit() {
        foreach (self::$links as $link) {
            $link->commit();
        }
    }

    /**
     * 打印封装的最后一条SQL语句（不一定准确）
     * @return string
     */
    public function getLastSql() {
        if ($this->dbdebug === false) {
            $this->throw_exception('请先开启DEBUG模式');
            return false;
        }
        return $this->queryStr;
    }

    /**
     * 打印封装的最后一条SQL语句（同getLastSql()，不一定准确）
     * @return string
     */
    public function _sql() {
        if ($this->dbdebug === false) {
            $this->throw_exception('请先开启DEBUG模式');
            return false;
        }
        return $this->queryStr;
    }

    /**
     * 从日志上读取SQL记录
     * @return string
     */
    public function getLastLog() {
        if ($this->dbdebug === false) {
            $this->throw_exception('请先开启DEBUG模式');
            return false;
        }
        if (empty($this->config['MySQL_log'])) {
            $this->throw_exception('尚未指定SQL日志文件的路径');
            return false;
        }
        $get_file_lastline = $this->get_file_lastline($this->config['MySQL_log']);
        if ($get_file_lastline === false) {
            return false;
        } else {
            $is_match = preg_match('/(?<=Query).*/', $get_file_lastline, $match);
            if ($is_match != 1) {
                $this->throw_exception('SQL日志文件最后一行无Query字符串');
                return false;
            }
            return trim($match[0]);
        }
    }

    /**
     * 解析where中的数组参数
     * @param array $whereArrayParam
     * @return string
     * _logic支持AND、OR、XOR(Thinkphp没有明确指定支持XOR)
     * _tosingle=>true表示字段对应数组元素单条件查询
     * _tomulti=>true表示字段对应数组元素多条件查询
     */
    private function parseWhereArrayParam($whereArrayParam) {
        $logic = ' AND ';
        $whereSubString = '';
        if (isset($whereArrayParam['_complex'])) {
            $whereSubString = '( ' . $this->parseWhereArrayParam($whereArrayParam['_complex']) . ' )';
            unset($whereArrayParam['_complex']);
        }
        if (isset($whereArrayParam['_logic'])) {
            if (in_array(strtoupper($whereArrayParam['_logic']), $this->SQL_logic)) {
                $logic = ' ' . strtoupper($whereArrayParam['_logic']) . ' ';
            } else {
                $this->throw_exception('_logic参数指定的逻辑运算符不被支持："' . $whereArrayParam['_logic'] . '"');
                return false;
            }
            unset($whereArrayParam['_logic']);
        }
        if (isset($whereArrayParam['_string'])) {
            $whereSubString .= $logic . '( ' . $whereArrayParam['_string'] . ' )';
            unset($whereArrayParam['_string']);
        }
        if (isset($whereArrayParam['_query'])) {
            $explode_query = explode('&', $whereArrayParam['_query']);
            $explode_array = array();
            foreach ($explode_query as $str) {
                $explode_sub_query = explode('=', $str);
                $explode_array[$explode_sub_query[0]] = $explode_sub_query[1];
            }
            if (isset($explode_array['_logic'])) {
                if (in_array(strtoupper($explode_array['_logic']), $this->SQL_logic)) {
                    $sub_logic = ' ' . strtoupper($explode_array['_logic']) . ' ';
                } else {
                    $this->throw_exception('_query中的_logic参数指定的逻辑运算符不被支持："' . $explode_array['_logic'] . '"');
                    return false;
                }
                unset($explode_array['_logic']);
            }
            $querySubString = '';
            foreach ($explode_array as $key => $val) {
                $start = strpos($key, '.');
                if ($start !== false) {
                    $querySubString .= $sub_logic . $key . " = '" . $val . "'";
                } else {
                    $querySubString .= $sub_logic . "`" . $key . "` = '" . $val . "'";
                }
            }
            $querySubString = ltrim($querySubString, $sub_logic);
            $whereSubString .= $logic . '( ' . $querySubString . ' )';
            unset($whereArrayParam['_query']);
        }
        foreach ($whereArrayParam as $key => $val) {
            $whereArraySubString = '';
            if (!is_array($val)) {
                $have_and = strpos($key, '&');
                $have_or = strpos($key, '|');
                $start = strpos($key, '.');
                if ($have_and === false && $have_or === false) {
                    //无&和|符号
                    if ($start !== false) {
                        $whereArraySubString .= $key . " = ?";
                    } else {
                        $whereArraySubString .= "`" . $key . "` = ?";
                    }
                    $this->whereValueArray[] = $val;
                } elseif (($have_and !== false && $have_or === false) || ($have_and === false && $have_or !== false)) {
                    //有&符号，无|符号 或者 无&符号，有|符号
                    if ($have_and !== false) {
                        $string_logic = '&';
                        $sub_logic = ' AND ';
                    } else {
                        $string_logic = '|';
                        $sub_logic = ' OR ';
                    }
                    $explode_array = explode($string_logic, $key);
                    $whereArraySubString = '';
                    foreach ($explode_array as $explode_val) {
                        $start = strpos($explode_val, '.');
                        if ($start !== false) {
                            $whereArraySubString .= $sub_logic . $explode_val . " = ?";
                        } else {
                            $whereArraySubString .= $sub_logic . "`" . $explode_val . "` = ?";
                        }
                        $this->whereValueArray[] = $val;
                    }
                    $whereArraySubString = ltrim($whereArraySubString, $sub_logic);
                    $whereArraySubString = '( ' . $whereArraySubString . ' )';
                } else {
                    //既有&符号，又有|符号
                    $this->throw_exception('快捷查询方式中“|”和“&”不能同时使用');
                    return false;
                }
            } else {
                $have_and = strpos($key, '&');
                $have_or = strpos($key, '|');
                if ($have_and === false && $have_or === false) {
                    //无&和|符号
                    if (isset($val['_tomulti']) && $val['_tomulti'] === true) {
                        //多条件查询
                        $get_parseMultiQuery = $this->parseMultiQuery($key, $val);
                        $whereArraySubString .= $get_parseMultiQuery;
                    } else {
                        //表达式查询
                        $get_parseExpQuery = $this->parseExpQuery($key, $val);
                        $whereArraySubString .= $get_parseExpQuery;
                    }
                } elseif (($have_and !== false && $have_or === false) || ($have_and === false && $have_or !== false)) {
                    //有&符号，无|符号 或者 无&符号，有|符号
                    if ($have_and !== false) {
                        $string_logic = '&';
                        $sub_logic = ' AND ';
                    } else {
                        $string_logic = '|';
                        $sub_logic = ' OR ';
                    }
                    $explode_array = explode($string_logic, $key);
                    $signal = 3; //1代表字段对应数组元素单条件查询，2代表字段对应数组元素多条件查询，3代表表达式查询
                    if (isset($val['_tosingle']) && isset($val['_tomulti'])) {
                        if ($val['_tosingle'] === true && $val['_tomulti'] === true) {
                            $this->throw_exception('单条件查询和多条件查询不能同时存在');
                            return false;
                        }
                        if ($val['_tosingle'] === true) {
                            $signal = 1;
                        }
                        if ($val['_tomulti'] === true) {
                            $signal = 2;
                        }
                    } elseif (isset($val['_tosingle'])) {
                        if ($val['_tosingle'] === true) {
                            $signal = 1;
                        }
                    } elseif (isset($val['_tomulti'])) {
                        if ($val['_tomulti'] === true) {
                            $signal = 2;
                        }
                    } else {
                        $signal = 3;
                    }
                    if ($signal == 1) {
                        //字段对应数组元素单条件查询
                        $index = 0;
                        foreach ($explode_array as $explode_val) {
                            if (is_array($val[$index])) {
                                if (isset($val[$index]['_tomulti']) && $val[$index]['_tomulti'] === true) {
                                    //多条件查询
                                    $get_parseMultiQuery = $this->parseMultiQuery($explode_val, $val[$index]);
                                    $whereArraySubString .= $sub_logic . $get_parseMultiQuery;
                                } else {
                                    //表达式查询
                                    $get_parseExpQuery = $this->parseExpQuery($explode_val, $val[$index]);
                                    $whereArraySubString .= $sub_logic . $get_parseExpQuery;
                                }
                            } else {
                                $start = strpos($explode_val, '.');
                                if ($start !== false) {
                                    $whereArraySubString .= $sub_logic . $explode_val . " = ?";
                                } else {
                                    $whereArraySubString .= $sub_logic . "`" . $explode_val . "` = ?";
                                }
                                $this->whereValueArray[] = $val[$index];
                            }
                            $index++;
                        }
                    } elseif ($signal == 2) {
                        //字段对应数组元素多条件查询
                        foreach ($explode_array as $explode_val) {
                            $get_parseMultiQuery = $this->parseMultiQuery($explode_val, $val);
                            $whereArraySubString .= $sub_logic . $get_parseMultiQuery;
                        }
                    } else {
                        //表达式查询
                        foreach ($explode_array as $explode_val) {
                            $get_parseExpQuery = $this->parseExpQuery($explode_val, $val);
                            $whereArraySubString .= $sub_logic . $get_parseExpQuery;
                        }
                    }
                    $whereArraySubString = ltrim($whereArraySubString, $sub_logic);
                    $whereArraySubString = '( ' . $whereArraySubString . ' )';
                } else {
                    //既有&符号，又有|符号
                    $this->throw_exception('快捷查询方式中“|”和“&”不能同时使用');
                    return false;
                }
            }
            $whereSubString .= $logic . $whereArraySubString;
        }
        $whereSubString = ltrim($whereSubString, $logic);
        return $whereSubString;
    }

    /**
     * 解析表达式查询
     * LIKE/NOTLIKE中支持AND、OR、XOR(Thinkphp没有明确指定支持XOR)
     * @param string $column
     * @param array $array
     * @return string
     */
    private function parseExpQuery($column, $array) {
        $expQueryString = '';
        $start = strpos($column, '.');
        $specialChar_index = strpos($column, '`');
        if ($specialChar_index === false && $start === false) {
            $column = '`' . $column . '`';
        }
        switch (strtoupper($array[0])) {
            case "EQ":
                $expQueryString .= $column . ' = ?';
                $this->whereValueArray[] = $array[1];
                break;
            case "NEQ":
                $expQueryString .= $column . ' <> ?';
                $this->whereValueArray[] = $array[1];
                break;
            case "GT":
                $expQueryString .= $column . ' > ?';
                $this->whereValueArray[] = $array[1];
                break;
            case "EGT":
                $expQueryString .= $column . ' >= ?';
                $this->whereValueArray[] = $array[1];
                break;
            case "LT":
                $expQueryString .= $column . ' < ?';
                $this->whereValueArray[] = $array[1];
                break;
            case "ELT":
                $expQueryString .= $column . ' <= ?';
                $this->whereValueArray[] = $array[1];
                break;
            case "LIKE":
            case "NOTLIKE":
            case "NOT LIKE":
                if (strtoupper($array[0]) == 'LIKE') {
                    $string = ' LIKE ';
                } else {
                    $string = ' NOT LIKE ';
                }
                if (is_array($array[1])) {
                    $logic = ' AND ';
                    if (isset($array[2])) {
                        if (in_array(strtoupper($array[2]), $this->SQL_logic)) {
                            $logic = ' ' . strtoupper($array[2]) . ' ';
                        } else {
                            if (!is_string($array[2])) {
                                $this->throw_exception('[NOT] LIKE查询中的数组第三个元素必须为字符串，用于指定逻辑运算符');
                                return false;
                            }
                            $this->throw_exception('[NOT] LIKE查询中的逻辑运算符"' . $array[2] . '"不被支持');
                            return false;
                        }
                    }
                    foreach ($array[1] as $val) {
                        $expQueryString .= $logic . $column . $string . ' ?';
                        $this->whereValueArray[] = (string)$val;
                    }
                    $expQueryString = ltrim($expQueryString, $logic);
                    $expQueryString = '( ' . $expQueryString . ' )';
                } else {
                    $expQueryString .= $column . $string . ' ?';
                    $this->whereValueArray[] = $array[1];
                }
                break;
            case "BETWEEN":
            case "NOTBETWEEN":
            case "NOT BETWEEN":
                //示例array('between','1,8')/array('between',1,8)/array('between',array('1','8'))
                if (strtoupper($array[0]) == 'BETWEEN') {
                    $string = ' BETWEEN ';
                } else {
                    $string = ' NOT BETWEEN ';
                }
                $expQueryString .= $column . $string . '? AND ?';
                if (is_array($array[1])) {
                    $this->whereValueArray[] = $array[1][0];
                    $this->whereValueArray[] = $array[1][1];
                } elseif (is_string($array[1])) {
                    $explode_array = explode(',', $array[1]);
                    if (count($explode_array) != 2) {
                        $this->throw_exception('表达式查询之[NOT]BETWEEN后的参数错误：' . $array[1]);
                        return false;
                    }
                    $this->whereValueArray[] = trim($explode_array[0]);
                    $this->whereValueArray[] = trim($explode_array[1]);
                } elseif (is_numeric($array[1])) {
                    if (!isset($array[2]) || !is_numeric($array[2])) {
                        $this->throw_exception('表达式查询之[NOT]BETWEEN后的参数错误(two number expected)');
                        return false;
                    }
                    $this->whereValueArray[] = $array[1];
                    $this->whereValueArray[] = $array[2];
                } else {
                    $this->throw_exception('表达式查询之[NOT]BETWEEN后的参数错误：' . $array[1]);
                    return false;
                }
                break;
            case "IN":
            case "NOTIN":
            case "NOT IN":
                //示例：array('not    in',array('a','b','c'))/array('not    in','a,b,c')
                if (strtoupper($array[0]) == 'IN') {
                    $string = ' IN ';
                } else {
                    $string = ' NOT IN ';
                }
                if (is_array($array[1])) {
                    $length = count($array[1]);
                    if ($length == 0) {
                        $this->throw_exception('表达式查询之[NOT]IN后的数组参数为空：array()');
                        return false;
                    }
                    $expQueryString .= $column . $string . '(';
                    $expQueryString .= '?';
                    $this->whereValueArray[] = $array[1][0];
                    for ($i = 1; $i < $length; $i++) {
                        $expQueryString .= ',?';
                        $this->whereValueArray[] = $array[1][$i];
                    }
                    $expQueryString .= ')';
                } elseif (is_string($array[1])) {
                    $explode_array = explode(',', $array[1]);
                    $length = count($explode_array);
                    $expQueryString .= $column . $string . '(';
                    $expQueryString .= '?';
                    $this->whereValueArray[] = $explode_array[0];
                    for ($i = 1; $i < $length; $i++) {
                        $expQueryString .= ',?';
                        $this->whereValueArray[] = $explode_array[$i];
                    }
                    $expQueryString .= ')';
                } else {
                    $this->throw_exception('表达式查询之[NOT]IN后的参数错误：' . $array[1]);
                    return false;
                }
                break;
            case "REGEXP":
                $string = ' REGEXP ';
                if (is_array($array[1])) {
                    $logic = ' AND ';
                    if (isset($array[2])) {
                        if (in_array(strtoupper($array[2]), $this->SQL_logic)) {
                            $logic = ' ' . strtoupper($array[2]) . ' ';
                        } else {
                            if (!is_string($array[2])) {
                                $this->throw_exception('[NOT] REGEXP查询中的数组第三个元素必须为字符串，用于指定逻辑运算符');
                                return false;
                            }
                            $this->throw_exception('[NOT] REGEXP查询中的逻辑运算符"' . $array[2] . '"不被支持');
                            return false;
                        }
                    }
                    foreach ($array[1] as $val) {
                        $expQueryString .= $logic . $column . $string . ' ?';
                        $this->whereValueArray[] = (string)$val;
                    }
                    $expQueryString = ltrim($expQueryString, $logic);
                    $expQueryString = '( ' . $expQueryString . ' )';
                } else {
                    $expQueryString .= $column . $string . ' ?';
                    $this->whereValueArray[] = $array[1];
                }
                break;
            case "EXP":
                if (is_string($array[1])) {
                    $expQueryString .= $column . $array[1];
                } else {
                    $this->throw_exception('表达式查询之exp后的参数错误：' . $array[1]);
                    return false;
                }
                break;
            default:
                $this->throw_exception('表达式查询之表达式错误："' . $array[0] . '"');
                return false;
        }
        return $expQueryString;
    }

    /**
     * 解析多条件查询
     * 支持AND、OR、XOR运算符(Thinkphp文档指定)
     * @param string $column
     * @param array $array
     * @return string
     */
    private function parseMultiQuery($column, $array) {
        $multiQueryString = '';
        $start = strpos($column, '.');
        $specialChar_index = strpos($column, '`');
        if ($specialChar_index === false && $start === false) {
            $column = '`' . $column . '`';
        }
        foreach ($array as $key => $val) {
            if (!is_numeric($key)) {
                unset($array[$key]);
            }
        }
        $length = count($array);
        $logic = ' AND ';
        if (is_string($array[$length - 1]) && (in_array(strtoupper($array[$length - 1]), $this->SQL_logic))) {
            $length--;
            $logic = ' ' . strtoupper($array[$length]) . ' ';
        }
        for ($i = 0; $i < $length; $i++) {
            if (is_array($array[$i])) {
                if (isset($array[$i]['_tomulti']) && $array[$i]['_tomulti'] === true) {
                    //多条件查询
                    $get_parseMultiQuery = $this->parseMultiQuery($column, $array[$i]);
                    $multiQueryString .= $logic . $get_parseMultiQuery;
                } else {
                    //表达式查询
                    $get_parseExpQuery = $this->parseExpQuery($column, $array[$i]);
                    $multiQueryString .= $logic . $get_parseExpQuery;
                }
            } else {
                $multiQueryString .= $logic . $column . ' = ?';
                $this->whereValueArray[] = $array[$i];
            }
        }
        $multiQueryString = ltrim($multiQueryString, $logic);
        $multiQueryString = '( ' . $multiQueryString . ' )';
        return $multiQueryString;
    }

    /**
     * 通过反引号引用字段，
     * @param unknown $value
     * @return string
     */
    private function addSpecialChar(&$value) {
        $value = trim($value);
        if (stripos($value, ' as ') !== false) {
            //字符串中有" as "
            $value = preg_replace('/\s+/', ' ', $value);
            // 匹配出as后面的字符串
            $match_number = preg_match('/(?<=\s{1}as\s{1})\w+$/i', $value, $match);
            if ($match_number != 1) {
                $this->throw_exception('"' . $value . '"匹配错误，请合法输入');
                return false;
            }
            $value = preg_replace('/(?<=\s{1}as\s{1})\w+$/i', '`' . $match[0] . '`', $value);
            // 匹配出as前面的字符串
            $match_number = preg_match('/^.*(?=\s{1}as\s{1}`)/i', $value, $match);
            if (preg_match('/^\w+$/', $match[0]) == 1) {
                $value = preg_replace('/\w+(?=\s{1}as\s{1}`)/i', '`' . $match[0] . '`', $value);
            }
        } elseif (1 === preg_match('/^\w+\.\w+$/', $value)) {
            //字符串是dbname.tablename，不做任何处理
        } else {
            //其他
            if (0 === preg_match('/\W+/', $value)) {
                $value = '`' . $value . '`';
            }
        }
        return $value;
    }

    /**
     * 对于纯字母或数字或下划线的字符串，两边加上反引号
     */
    private function addSpecialChar_for_pure_string(&$value) {
        if (preg_match('/^\w+$/', $value)) {
            $value = '`' . $value . '`';
        }
        return $value;
    }

    /**
     * 将匹配的字符进行替换，支持字符串替换和数组对应替换
     * @param string $pattern
     * @param string/array $replacement
     * @param string $subject
     * @return string
     */
    private function replaceSpecialChar($pattern, $replacement, $subject) {
        if (is_array($replacement)) {
            $length = count($replacement);
            for ($i = 0; $i < $length; $i++) {
                $subject = preg_replace($pattern, $this->link->quote($replacement[$i]), $subject, 1);
            }
        } elseif (is_string($replacement)) {
            $subject = preg_replace($pattern, $this->link->quote($replacement), $subject);
        } else {
            $this->throw_exception('replaceSpecialChar函数的第二个参数类型错误');
            return false;
        }
        return $subject;
    }

    /*
     * 获取文件最后一行/倒数第$n行
     */
    private function get_file_lastline($file_name, $n = 1) {
        if (file_exists($file_name) != 1) {
            echo "failed to open stream: File does not exist";
            return false;
        }
        if (!$fp = fopen($file_name, 'r')) {
            echo "failed to open stream: Permission denied";
            return false;
        }
        fseek($fp, -1, SEEK_END);
        $content = '';
        while (($c = fgetc($fp)) !== false) {
            if ($c == "\n" && $content) {
                $n--;
                if (!$n) {
                    break;
                }
                $content = '';
            }
            $content = $c . $content;
            fseek($fp, -2, SEEK_CUR);
        }
        fclose($fp);
        return trim($content);
    }

    /**
     * 每次执行完sql语句清空连贯操作的sql子句
     */
    private function clearSubString() {
        // $this->SQLerror = null;
        $this->fieldString = '';
        $this->joinString = '';
        $this->whereString = '';
        $this->groupString = '';
        $this->havingString = '';
        $this->orderString = '';
        $this->limitString = '';
        $this->lockString = '';
        $this->aliasString = '';
        $this->tmp_table = '';
        $this->fetchSql = false;
        $this->whereStringArray = array();
        $this->whereValueArray = array();
    }

    /**
     * 判断SQL是否执行有误，有误则抛出异常(throw_exception)
     */
    public function haveErrorThrowException() {
        $link = empty($this->PDOStatement) ? $this->link : $this->PDOStatement;
        $arrError = $link->errorInfo();
        if ($arrError[0] != '00000') {
            if ($this->dbdebug) {
                $this->SQLerror = array(
                    'sqlstate' => $arrError[0],
                    'errno' => $arrError[1],
                    'msg' => $arrError[2],
                    'sql' => $this->queryStr,
                );
            }
            return false;
        }
        return true;
    }

    /**
     * 直接获取SQL错误信息
     */
    public function getSQLError() {
        if ($this->dbdebug) {
            return $this->SQLerror['msg'];
        } else {
            return '没有开启Debug模式';
        }
    }

    /**
     * 直接获取SQL错误时的SQLSTATE
     */
    public function getSQLstate() {
        if ($this->dbdebug) {
            return $this->SQLerror['sqlstate'];
        } else {
            return '没有开启Debug模式';
        }
    }

    /**
     * 打印SQL错误信息
     */
    public function showError() {
        if ($this->SQLerror == null) {
            $this->throw_exception('没有开启DEBUG模式无法看到详细错误信息，或者最近一次SQL操作并没有发生错误', false, false);
        } else {
            $this->throw_exception('Error Code: ' . $this->SQLerror['errno'] . '<br/>SQLSTATE: ' . $this->SQLerror['sqlstate'] . ' <br/>Error Message: <div>' . $this->SQLerror['msg'] . '</div><br/>Error SQL: <div>' . $this->SQLerror['sql'] . '</div>', false, false);
        }
    }

    /**
     * 自定义错误处理
     * @param unknown $errMsg
     * @param boolean $ignore_debug
     */
    public function throw_exception($errMsg, $ignore_debug = false, $exit = true) {
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        if ($this->dbdebug || $ignore_debug) {
            $errMsg .= '</b><br/><br/><b>SOURCE</b><br>FILE: ' . $caller['file'] . '   LINE: ' . $caller['line'];
            $caller = array_shift($bt);
            $number = 0;
            if ($caller != null) {
                $errMsg .= '<br/><br/><b>TRACE</b><br/>';
            }
            while ($caller != null) {
                $number++;
                $errMsg .= '#' . $number . ' ' . $caller['file'] . '(' . $caller['line'] . ')<br/>';
                $caller = array_shift($bt);
            }
        } else {
            $errMsg = "系统出错，请联系管理员。</b>";
        }
        echo '<div style="width:80%;background-color:#ABCDEF;color:black;padding:20px 0px;"><b style="font-size:25px;">
				' . $errMsg . '
        </div><br/>';
        if ($exit) {
            exit(0);
        }
    }

    /**
     * 获取类绑定的数据表名
     * @return string
     */
    public function getTableName() {
        return $this->table;
    }

    /**
     * 获取类绑定的数据表中的字段信息
     * @return array
     */
    public function getColumns() {
        $this->set_columns($this->table);
        return $this->columns;
    }

    /**
     * 获取上一步操作产生受影响的记录的条数
     * @return string
     */
    public function getDbVersion() {
        return $this->dbVersion;
    }

    /**
     * 获取类绑定的数据表中的字段信息
     * @return array
     */
    public function getNumRows() {
        return $this->numRows;
    }

    /**
     * 销毁连接对象，关闭数据库
     */
    public function close() {
        $this->link = null;
    }

    /**
     * 析构函数
     */
    public function __destruct() {
        $this->close();
    }
}

//M函数
function M($dbtable, $ConfigID = 0, $dbConfig = null) {
    return new PDOMySQL($dbtable, $ConfigID, $dbConfig);
}

function filter(&$value) {
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

//I函数，参数为字符串
function I($str) {
    $pos = strrpos($str, '.', -1);
    if ($pos === false) {
        PDOMySQL::throw_exception("I函数参数错误");
        return false;
    }
    $type = substr($str, 0, $pos);
    $param = substr($str, $pos + 1);
    switch (strtoupper($type)) {
        case 'GET':
            if ($param != '') {
                $result_set = isset($_GET[$param]) ? $_GET[$param] : null;
            } else {
                $result_set = $_GET;
            }
            break;
        case 'POST':
            // 如果$_POST中无数据，则从php://input中取
            if (count($_POST) == 0) {
                $_POST = json_decode(file_get_contents('php://input'), true);
            }
            if ($param != '') {
                $result_set = isset($_POST[$param]) ? $_POST[$param] : null;
            } else {
                $result_set = $_POST;
            }
            break;
        default:
            PDOMySQL::throw_exception("I函数不支持此参数：" . $str);
            return false;
    }
    if (is_array($result_set)) {
        array_walk_recursive($result_set, "filter");
    }
    return $result_set;
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip($type = 0, $adv = false) {
    $type = $type ? 1 : 0;
    static $ip = null;
    if ($ip !== null) {
        return $ip[$type];
    }
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

/**
 * Ajax方式返回数据到客户端
 * 暂时只支持返回json格式数据
 */
function jsonResponse($code = 1, $msg = "fail", $data = []) {
    header('Content-Type:application/json; charset=utf-8');
    $data = json_encode(["code" => $code, "msg" => $msg, "data" => $data]);
    exit($data);
}

/**
 * 浏览器友好的变量输出
 * @param mixed $var 变量
 * @param boolean $echo 是否输出 默认为True 如果为false 则返回输出字符串
 * @param string $label 标签 默认为空
 * @param boolean $strict 是否严谨 默认为true
 * @return void|string
 */
function dump($var, $echo = true, $label = null, $strict = true) {
    $label = ($label === null) ? '' : rtrim($label) . ' ';
    if (!$strict) {
        if (ini_get('html_errors')) {
            $output = print_r($var, true);
            $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        } else {
            $output = $label . print_r($var, true);
        }
    } else {
        ob_start();
        var_dump($var);
        $output = ob_get_clean();
        if (!extension_loaded('xdebug')) {
            $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
            $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        }
    }
    if ($echo) {
        echo($output);
        return null;
    } else {
        return $output;
    }
}

/**
 * PHP的html编码函数
 */
function html_encode($str) {
    $s = "";
    if (strlen($str) == 0) {
        return "";
    }
    $s = preg_replace('/&/', "&amp;", $str);
    $s = preg_replace('/</', "&lt;", $s);
    $s = preg_replace('/>/', "&gt;", $s);
    $s = preg_replace('/ /', "&nbsp;", $s);
    $s = preg_replace('/\'/', "&#39;", $s);
    $s = preg_replace('/\"/', "&quot;", $s);
    $s = preg_replace('/\n/', "<br/>", $s);
    return $s;
}

/**
 * PHP的html解码函数
 */
function html_decode($str) {
    $s = "";
    if (strlen($str) == 0) {
        return "";
    }
    $s = preg_replace('/&lt;/', "<", $str);
    $s = preg_replace('/&gt;/', ">", $s);
    $s = preg_replace('/&nbsp;/', " ", $s);
    $s = preg_replace('/&#39;/', "\'", $s);
    $s = preg_replace('/&quot;/', "\"", $s);
    $s = preg_replace('/&amp;/', "&", $s);
    $s = preg_replace('/<br\/>/', "\n", $s);
    return $s;
}
