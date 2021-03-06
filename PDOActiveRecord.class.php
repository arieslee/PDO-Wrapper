<?php
namespace \Emergence\ActiveRecordPDO

class PDOActiveRecord
{
  	public $host;
	public $db_name; 
	public $db_username; 
	public $db_password;
	public $dbh;
	
	function __construct($config = false) 
	{
		if(is_array($config))
		{
			$this->host = $config['host'];
			$this->db_name = $config['db_name'];
			$this->db_username = $config['db_username'];
			$this->db_password = $config['db_password'];
		}	
		try 
		{
		    $this->dbh = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->db_username, $this->db_password);
		    $this->dbh->exec("SET CHARACTER SET utf8");
		}
		catch (PDOException $e)
		{
		    throw new Exception($e->getMessage());
		}
	}
	
	public function create($table, $insert)
	//Timestamp always set unless otherwise specified
	{		
		// Filter out fields that don't exist
		$insert = $this->filter($insert, $table);
		//End Filter
		
		
		$keys = implode(', ', array_keys($insert));
		$table_values = implode(", :", array_keys($insert));
		$sql = "INSERT INTO $table ($keys) VALUES(:$table_values)";
		$query = $this->dbh->prepare($sql);
		$new_insert = array();
		foreach($insert as $key=>$value)
		{
			if($value==null)
			{
				$value = '';
			}
			$new_insert[":" . $key] = $value;
		}
		$query->execute($new_insert);
		
		//to check that there is an id field before using it to get the last object
		if($this->dbh->lastInsertId())
		{
			$stmt = $this->dbh->query("SELECT * FROM $table WHERE {$this->getPrimaryKey($table)}='" . $this->dbh->lastInsertId() . "'");
			return $stmt->fetch(PDO::FETCH_OBJ);
		}
		else
		//if there isn't, just get the object by fields
		{
			return $this->getByWhere($table, $insert);
		}
	}
	
	public function update($table, $insert, $object)
	{
		$tmp = array();
		$primaryKey = $this->getPrimaryKey($table);
		$insert = $this->filter($insert, $table);
		
		foreach($insert as $key=>$value)
		{
			$tmp[] = "$key=?";
		}
		$str = implode(', ', $tmp);
		
		$sql = "UPDATE $table SET $str WHERE $primaryKey='" . $object->$primaryKey . "'";
		$query = $this->dbh->prepare($sql);
		$query->execute(array_values($insert));
		
		return $this->dbh->exec($sql);
	}
	
	public function getByID($table, $id)
	{
		return $this->getByField($table, $this->getPrimaryKey($table), $id);
	}
	
	public function getByField($table, $field, $value, $options = false)
	{
		$data = array($field=>$value);
		return $this->getByWhere($table, $data, $options);
	}
	
	public function getByWhere($table, $data, $options = false)
	{
		$data = $this->filter($data, $table);
		$conditions = array();
		foreach($data as $key=>$value)
		{
			if($value==null)
			{
				$conditions[] = "$key IS NULL";
				unset($data[$key]);
			}
			else
			{
				$conditions[] = "$key=?";
			}
		}
		$str = implode(' AND ', $conditions);
		$sql = "SELECT * FROM $table WHERE $str";
		if($options)
		{
			$sql .= ' ' . $options;
		}
		$query = $this->dbh->prepare($sql);
		$query->execute(array_values($data));
		return $query->fetch(PDO::FETCH_OBJ);			
	}
	
	public function getAllByWhere($table, $data, $options = false)
	{
		$data = $this->filter($data, $table);
		$conditions = array();
		foreach($data as $key=>$value)
		{
			if($value==null)
			{
				$conditions[] = "$key IS NULL";
				unset($data[$key]);
			}
			else
			{
				$conditions[] = "$key=?";
			}
		}
		$str = implode(' AND ', $conditions);
		$sql = "SELECT * FROM $table WHERE $str";
		if($options)
		{
			$sql .= ' ' . $options;
		}
		$query = $this->dbh->prepare($sql);
		$query->execute(array_values($data));
		return $query->fetchAll(PDO::FETCH_OBJ);			
	}
	
	public function filter($insert, $table)
	{
		$columns = $this->dbh->query("SHOW COLUMNS FROM `$table`")->fetchAll();
		$fields = array();
		foreach($columns as $row)
		{
			$fields[$row['Field']] = true;
		}
		
		foreach($insert as $key=>$value)
		{
			if(!isset($fields[$key]))
			{
				unset($insert[$key]);
			}
		}
		
		if(count($insert)===0)
		{
			throw new Exception('At least one field must be passed as data.  Check to make sure fields exist in Database');
		}
		return $insert;
	}
	
	public function getPrimaryKey($table)
	{
		$sql = "SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'";
		$stmt = $this->dbh->query($sql);	
		$res = $stmt->fetch(PDO::FETCH_OBJ);
		return $res->Column_name;
	}
}
