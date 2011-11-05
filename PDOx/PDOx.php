<?php
/**
 * PHP Data Objects x
 * 
 * extends PDO and build debug info
 * 
 * @author		Zephyr Wu <zephyr214@gmail.com>
 * @license		New BSD License
 * @version		SVN: $Id$
 */
class Zeef_PDOx extends PDO
{
    /**
     * fields names
     * @var	String
     */
    const COL_SQL     = 'sql';
    const COL_COST    = 'cost';
    const COL_FILE    = 'file';
    const COL_LINE    = 'line';
    const COL_METHOD  = 'method';
    const COL_LOOPS   = 'loops';
    
    /**
     * name of the table to save debug info
     * @var String
     */
    const MEM_TABLE = 'debug';
    
    /**
     * field list of memory db to save debug info 
     * @var Array
     */
    private $_fieldInfo = array(
    	self::COL_SQL    => 'VARCHAR(127)',
    	self::COL_COST   => 'DECIMAL(12,10)',
    	self::COL_FILE   => 'VARCHAR(127)',
    	self::COL_LINE   => 'INT(5)',
    	self::COL_METHOD => 'VARCHAR(32)',
	);
	
	/**
	 * PDOStatement 
	 * @var PDOStatement 
	 */
	private $_stmt = null;
    
	/**
	 * PDO
	 * @var PDO
	 */
	private static $_mdb = null;
	
    /**
     * Constructor
     * 
     * @param $dsn
     * @param $username
     * @param $passwd
     * @param $options
     */
    public function __construct ($dsn, $username = '', $passwd = '', $options = array())
    {
        parent::__construct($dsn, $username, $passwd, $options);
    	$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('Zeef_PDOxStatement', array($this)));
    	
    	//init memory db to save debug info
        if (null === self::$_mdb) {
            self::$_mdb = new PDO('sqlite::memory:');
        }
    	$fields = array_keys($this->_fieldInfo);
    	$fieldSet = array_map(create_function('$a,$b', 'return "`$a` $b";'), $fields, array_values($this->_fieldInfo));
        $marks = ':' . implode(', :', $fields);
        $res = self::$_mdb->exec("CREATE TABLE " .self::MEM_TABLE. " (" .implode(', ', $fieldSet). ")");        
        $this->_stmt = self::$_mdb->prepare("INSERT INTO " .self::MEM_TABLE. " VALUES($marks)");
    }
    
    /**
     * Executes an SQL statement, returning a result set as a PDOStatement object 
     * 
     * @param	string	$sql
     * @return	PDOStatement
     */
    public function query ($sql)
    {
        $startTime = Zeef_G::microtime();
        $result = parent::query($sql);
        $costTime = (float) Zeef_G::microtime($startTime);
        $debug = array(self::COL_SQL=>$sql, self::COL_COST=>$costTime);
        
        //add back trace info
        $this->dumpBacktrace($debug)->saveDebugInfo($debug);
        return $result;
    }
    
    /**
     * Dump baktrace
     *
     * @param	Array	$debug	debug info
     * @return	Zeef_PDOx
     */
    public function dumpBacktrace (& $debug)
    {
        $debug = array_merge($debug, array(self::COL_FILE=>'', self::COL_LINE=>'', self::COL_METHOD=>''));
        $backtrace = debug_backtrace();
        
        //add file, line and method from backtrace
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && $trace['file'] != __FILE__) {
	            $debug[self::COL_FILE] = $trace['file'];
	            $debug[self::COL_LINE] = $trace['line'];
	            $debug[self::COL_METHOD] = $trace['function'];
	            break;
            }
        }
        return $this;
    }

    /**
     * save debug info to memory db
     * 
     * @param	Array	$debug	debug info
     * @return	Zeef_PDOx
     */
    public function saveDebugInfo ($debug)
    {
        $this->_stmt->execute(array_values($debug));
        return $this;
    }
        
    /**
     * dump debug info as Array
     * 
     * @return	Array
     */
    public static function dumpDebugInfo ()
    {
        $debugSet = self::_dumpDebugData(self::COL_COST); //sort by cost time desc
        return $debugSet;
    }
        
    /**
     * dump debug info as HTML comments format
     * 
     * @return	Array
     */
    public static function dumpDebugInfoAsHtmlComments ()
    {
        $debugSet = self::_dumpDebugData(self::COL_COST);
        $content = '';
        foreach ($debugSet as $d) {
            $file = basename($d['file']);
            $content .= "<!-- SQL-Cost: {$d['cost']} ({$d['loops']}) [{$file} (L.{$d['line']})];  (SQL: {$d['sql']}) -->\n";
        }        
        return $content;
    }
    
    /**
     * Dump debug data from memory db
     * 
     * @param	String|null	$sortby
     * @param	Boolean		$desc
     * @return	Array
     */
    private static function _dumpDebugData ($sortby = null, $desc = true)
    {
        $debugSet = array();
        $res = self::$_mdb->query("SELECT * FROM " .self::MEM_TABLE)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $v) {
            $v[self::COL_LOOPS] = 1; //default to 1 loop
            $v[self::COL_COST] = (float) $v[self::COL_COST]; //convert to float
            $v[self::COL_SQL] = str_replace(array("\n", "\t", "  "), '', $v[self::COL_SQL]); //strip space
            if (@empty($v[self::COL_FILE]) || @empty($v[self::COL_LINE])) {
                $debugSet[] = $v;
                continue;
            }
            
            //merge debug info from same iteration
            $key = md5($v[self::COL_FILE] . $v[self::COL_LINE]);
            if (!isset($debugSet[$key])) {
                $debugSet[$key] = $v;
                continue;
            }
            $debugSet[$key][self::COL_COST] += $v[self::COL_COST];
            $debugSet[$key][self::COL_LOOPS]++;
        }
        
        //sort by cost time desc
        if (null !== $sortby) {
            $op = $desc ? '<' : '>';
            usort($debugSet, create_function('$a,$b', "return \$a['{$sortby}'] {$op} \$b['{$sortby}'] ? 1 : -1;"));
        }
        return $debugSet;
    }
}


/**************************************************************************************************
 * PHP Data Objects x Statement
 * 
 * extends PDOStatement and build debug info
 * 
 * @author		Zephyr Wu <zephyr214@gmail.com>
 * @license		New BSD License
 * @version		SVN: $Id$
 */
class Zeef_PDOxStatement extends PDOStatement
{
    /**
     * Zeef_PDOx
     * @var	Zeef_PDOx|PDO
     */
    protected $_dbh;
    
    /**
     * debug info
     * @var Array
     */
    protected $_info = array();
        
    /**
     * Constructor
     * 
     * @param	Zeef_PDOx	$dbh
     */    
    protected function __construct ($dbh)
    {
        $this->_dbh = $dbh;
        $this->_info = array(Zeef_PDOx::COL_SQL=>'', Zeef_PDOx::COL_COST=>0);
        $this->_dbh->dumpBacktrace($this->_info); //add back trace info
    }
    
    /**
	 * Executes a prepared statement
	 * 
	 * @param	Array	$input_parameters	An array of values with as many elements as there are bound
	 * 										parameters in the SQL statement being executed.
	 * @return	Boolean
     */
    public function execute ($input_parameters = null)
    {
        $startTime = Zeef_G::microtime();
        $result = parent::execute($input_parameters);
        $this->_info[Zeef_PDOx::COL_COST] = (float) Zeef_G::microtime($startTime);
       
        //get sql statment
        $this->_info[Zeef_PDOx::COL_SQL] = $this->queryString;
        
        //ob_start();
        //parent::debugDumpParams();
        //$params = ob_get_clean();
        //$this->_info[Zeef_PDOx::COL_SQL] = preg_replace('@^SQL:\s\[\d+\]\s?(.+?)\sParams:\s.+$@is', '$1', $params);
      
        //save debug info
        $this->_dbh->saveDebugInfo($this->_info);
        return $result;
    }
}

/**
 * common functions
 * 
 * @author		Zephyr Wu <zephyr214@gmail.com>
 * @license		New BSD License
 * @version		SVN: $Id$
 */
class Zeef_G
{
	public static function microtime ($start = 0) {
		$t = explode(' ', microtime());      
		return number_format($t[0] + $t[1] - $start, 5, '.', '');
	}    
}