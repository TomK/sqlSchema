<?php
abstract class sqlSchema extends PDO {
/*    protected static $__CLASS__ = __CLASS__;
	static $singleton = array();
	static function &getSingleton() {
		if (isset(self::$singleton[static::$__CLASS__])) return self::$singleton[static::$__CLASS__];
		self::$singleton[static::$__CLASS__] = new static::$__CLASS__();
		return self::$singleton[static::$__CLASS__];
	}*/

	protected $servername	= '';
	protected $dbname		= '';
	protected $username		= '';
	protected $password		= '';
	
	public $returnValue = null;
	public $resultSets = array();

	private $hasReturn = null;
	private $params = array();
	function setReturn($type,$length=-1) {
		if (is_null($type)) { $this->hasReturn = null; return; }
		$this->hasReturn = array($type,$length);
	}
	function &addByRef(&$var,$type=PDO::PARAM_STR,$length) {
		$type = $type | PDO::PARAM_INPUT_OUTPUT;
		if (is_string($var) && strlen($var) > $length)
			$var = substr($var,0,$length);
		$param = array(&$var,$type,$length,true);
		$this->params[] =& $param;
		return $param;
	}
	function &addByVal($var,$type=PDO::PARAM_STR) {
		$param = array($var,$type,null,false);
		$this->params[] =& $param;
		return $param;
	}
	function call($fnName) {
		$isSelect = (strtolower(substr($fnName,0,6))==='select');
		if ($isSelect) $this->setReturn(null);
		// build function call query

		$paramCount = count($this->params);
		
		if ($isSelect) {
			$qry = $fnName;
		} else {
			if (!is_null($this->hasReturn)) $qry = '? = ';
			$qry .= 'CALL '.$fnName.'(';
			for ($i = 0; $i < $paramCount; $i++) {
				$qry .= '?';
				if ($i+1 < $paramCount) {
					$qry .= ',';
				}
			}
			$qry .= ')';
			$qry = '{'.$qry.'}';
		}

		// get PDOStatement
		$stmt = $this->prepare( $qry );
		if (!$stmt)	{
			trigger_error('Invalid query: '.$qry, E_USER_ERROR);
			return false;
		}

		// add return value parameter
		if (!is_null($this->hasReturn))
			$stmt->bindParam(1,&$this->returnValue,$this->hasReturn[0] | PDO::PARAM_INPUT_OUTPUT,$this->hasReturn[1]);
		
		// add other parameters in order
		for ($i = 0,$inc = is_null($this->hasReturn)?1:2; $i < $paramCount; $i++) {
			if ($this->params[$i][3])
				$stmt->bindParam($i+$inc,$this->params[$i][0],$this->params[$i][1],$this->params[$i][2]);
			else
				$stmt->bindValue($i+$inc,$this->params[$i][0],$this->params[$i][1]);
		}
		
		// execute statement
		$this->resultSets = array();
		if ($stmt->execute()) {
			do {
				$resultSet =& $this->resultSets[];
				while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					$resultSet[] = $row;
				}
			} while ($stmt->nextRowset());
			
			return true;
		}
		
		$err = $stmt->errorInfo();
		if ($err[0] !== '00000') {
			trigger_error(var_export($stmt->errorInfo()), E_USER_ERROR);
		}
		return false;
	}
	
	function __construct() {
		if (!$this->servername)	trigger_error('Please declare protected $servername', E_USER_ERROR);
		if (!$this->dbname)		trigger_error('Please declare protected $dbname', E_USER_ERROR);
		if (!$this->username)	trigger_error('Please declare protected $username', E_USER_ERROR);
		if (!$this->password)	trigger_error('Please declare protected $password', E_USER_ERROR);
		
        $this->engine = 'sqlsrv';
        $dns = $this->engine.':server='.$this->servername.";Database=".$this->dbname;
        try {
        	parent::__construct( $dns, $this->username, $this->password );
        } catch (Exception $e) {
        	echo $e->getMessage();
        	return;
        }
        
		$this->setReturn(PDO::PARAM_INT);
	}
}
?>