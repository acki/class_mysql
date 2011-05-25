<?

	/**
	 * Database Class
	 * 
	 * This is a very usable database class which uses mysqli.
	 * Tried to abstract all usable usecases.
	 *
	 * @author Christoph S. Ackermann, info@acki.be
	 */

	class Database {
	
		public 		$mysqli;
		protected 	$toSelect;
		protected 	$table;
		protected 	$whereString;
		protected 	$bindValues 		= array();
		private 	$query;
		private 	$stmt;
		
		public function __construct($host, $user, $pass, $db) {
			$this->mysqli = new mysqli($host, $user, $pass, $db);
			return $this->mysqli;
		} //function construct
		
		/**
		 * Update an entry in the database
		 * @param string $table
		 * @param array $values (Format: array(field=>value))
		 * @param array $whereClauses (Format: array(field=>value) or array(field=>array(value, operator)))
		*/
		
		public function update($table, $values, $whereClauses) {
			$this->table			= $table;
			$this->whereString		= false;
			$this->bindValues		= false;
			
			if(!is_array($values)) {
				die('update takes an array as second parameter');
			}
			
			$this->createDataString($values);
			$this->createWhereString($whereClauses);
			
			$this->query			= 'UPDATE ' . $this->table . ' SET ' . $this->dataString . $this->whereString;
						
			$this->prepareAndExecuteQuery();
			//var_dump($this->dataValues);
			
		}
		
		/**
		 * Select database entries
		 * @param string $table
		 * @param array $whereClauses (Format: array(field=>value) or array(field=>array(value, operator)))
		 * @param string|array $toSelect (Format: string(value) or array(value, value))
		*/
	
		public function select($table, $whereClauses=false, $toSelect='*') {
			$this->toSelect 		= $toSelect;
			$this->table 			= $table;
			$this->bindValues		= false;
			$this->whereString		= false;
			$this->stmt				= false;

			//look if toSelect is an array			
			if(is_array($this->toSelect)) {
				$this->toSelect = implode(',', $this->toSelect);
			} //if is_array
			
			//if $whereClauses exists, create a string for it
			if(isset($whereClauses) && is_array($whereClauses) && count($whereClauses)>0) {
				$this->createWhereString($whereClauses);
			} //if whereclauses
			
			//creates a query with our known data
			$this->query			= 'SELECT ' . $this->toSelect . ' FROM ' . $this->table . $this->whereString;
			
			//prepare, bind and execute the statement
			$this->prepareAndExecuteQuery();

			//get the data
			$meta = $this->stmt->result_metadata();
			while($field = $meta->fetch_field()) {
				$params[] = &$row[$field->name];
			}//while
			call_user_func_array(array($this->stmt, 'bind_result'), $params);

			//creates array with all results
			$allRows = array();
			$i=0;
			while($this->stmt->fetch()) {
				foreach($row as $key => $val){
					$allRows[$i][$key] = $val;
				}//foreach
				$i++;
			}//while
			
			//return data if available, otherwise return false
			if(count($allRows)>0) {
				return $allRows;
			} else {
				return false;
			}
			
		} //function select
		
		/**
		 *
		*/
		public function escape($string) {
			return mysqli_real_escape_string($this->mysqli, $string);
		} // function escape
				
		/**
		 * Bind the params to the statement
		 * @param array $bindValues (Format: array(key=>val))
		*/
		public function bindParams($bindValues) {
		
			$values = array('');
						
			//generates array for each value
			if(isset($bindValues) && is_array($bindValues)) {
			
				foreach($bindValues as $key=>$val) {
					//difference between string and integer
					if((int)$val === $val && is_int((int)$val)) {
						$values[0] .= 'i';
						$values[] = &$bindValues[$key];
					} else {
						$values[0] .= 's';
						$values[] = &$bindValues[$key];
					}//ifelse
				}//foreach
						
			}
			
			//bind parameters to statement
			if(call_user_func_array(array($this->stmt, "bind_param"), $values)) {
				return true;
			}//if call_user_func_array

			return false;

		}//function bindparams
		
		/**
		 * Creates the where string with an array of data
		 * @param array $where (Format: array(field=>value) or array(field=>array(value, operator)))
		*/
		public function createWhereString($where) {
		
			$this->whereString 	= '';
			$this->first 		= true;
		
			//function die if is not an array
			if(!is_array($where)) {
				return false;
			}//if
			
			//create the string for each value
			foreach($where as $key=>$val) {
				//in the first call add a WHERE, after that add an AND
				if($this->first) {
					$this->whereString .= ' WHERE ';
					$this->first = false;
				} else {
					$this->whereString .= ' AND ';
				} //ifelse
				
				//create an array if not
				if(!is_array($val)) {
					$val = array($val);
				}//if
				
				//creates the operator for where statement
				if(isset($val[1]) && (
								$val[1] == "=" || 
								$val[1] == "!=" || 
								$val[1] == "<" || 
								$val[1] == ">" || 
								$val[1] == "<=" || 
								$val[1] == ">="
							)) {
					$operator = $val[1];
				} else {
					$operator = "=";
				} //ifelse
				
				//add the key and operator to the classwide where string
				$this->whereString .= $key . ' ' . $operator . ' ? ';
				
				//add the values to the classwide value cache
				$this->bindValues[] = $val[0];
				
			} //foreach
			
		} //function createWhereString
		
		/**
		 * Creates a string with data for update or insert
		 * @param array $data (Format: array(key=>val))
		 * @param string $type insert|update switch
		*/
		
		public function createDataString($data, $type='update') {
		
			$this->bindValues = '';
				
			if($type === 'update') {
			
				foreach($data as $key=>$val) {
					$this->dataString .= $key . ' = ?, ';
					$this->bindValues[] = $val;
				}
				$this->dataString = substr($this->dataString, 0, strlen($this->dataString)-2);
			
			}
			
		}
		
		/**
		 * Prepares the query, bind the params and execute the thing
		*/
		
		public function prepareAndExecuteQuery() {
		
			//prepare the statement
			if(!$this->stmt = $this->mysqli->prepare($this->query)) {
				$this->throwMysqliError($this->query, $this->mysqli->error);
			}
			
			//bind params if $this->whereValues exists and is an array
			if(isset($this->bindValues) && is_array($this->bindValues)) {
				if(!$this->bindParams($this->bindValues)) {
					$this->throwMysqliError('bind params', $this->mysqli->error);
				}
			}
			
			//execute the statement
			if(!$this->stmt->execute()) {
				$this->throwMysqliError('execute', $this->mysqli->error);
			}
			
		}
		
		/**
		 * Throws a fine error
		 * @param string $message
		 * @param string $error ($this->mysqli->error)
		*/
		
		public static function throwMysqliError($message, $error) {
			print 'Database ' . $message . ' failed. Sorry.<br />';
			print $error;
			exit;
		}
	
	} //class
	
?>