<?php
	
	class db {

		private $host, $user, $pass, $database;
		public $con;

		function __construct($dbHost = DB_HOST, $dbUser = DB_USER, $dbPass = DB_PASS, $dbName = DB_NAME) {
			$this->host = $dbHost;
			$this->user = $dbUser;
			$this->pass = $dbPass;
			$this->database = $dbName;
            		$this->con = new mysqli($this->host, $this->user, $this->pass, $this->database);
			if($this->con->connect_error) {
				$this->con = false;
			}
		}
		function __destructor() {
			$this->con->close();
			return true;
		}
		private function connect() {
			$this->con = new mysqli($this->host, $this->user, $this->pass, $this->database);
			if($this->con->connect_error) {
				$this->con = false;
			}
		}

		function query($query) {
			$con=$this->con;
			if($con == false) {
				return array(false,$con->connect_error);
			}
			$result = $con->query($query);
			return array(true,$result);
		}

		function insert($table, $data, $format) {
			$array = $this->seperateArrays($data);
			$columns = $array[0];
			$values = $array[1];
			if(empty($table)) {
				return array(false,"Table name can't be empty");
			}
			if(empty($format)) {
				return array(false,"Format can't be empty");
			}
			if(count(str_split($format, 1)) != count($columns)) {
				return array(false,"No of columns and parameters is not matching");
			}
			if(count($columns) != count($values)) {
				return array(false,"Number columns and values are not matching");
			}

			$con=$this->con;
			if($con == false) {
				return array(false,$con->connect_error);
			}

			$query = "INSERT INTO ". $table . "(".implode(', ', $columns).") VALUES(".$this->getPlaceholder($columns). ");";
		
			$stmt=$con->prepare($query);
			
			// Prepend the format to $values

			array_unshift($values, $format);
			call_user_func_array(array($stmt, 'bind_param'), $this->refValues($values));
			if($stmt->execute()) {
				if($stmt->affected_rows) {
                    $result = $stmt;
					return array(true, $stmt);
				}
			}
			$error = mysqli_error($con);
			return array(false,$error);
			
		}

		function update($table, $data, $format, $whereFormat = NULL, $whereValues = NULL) {
			if(empty($table)) {
				return array(false,"Table name is empty");
			}
			if(empty($data)) {
				return array(false,"data array is empty");
			}
			if(empty($format)) {
				return array(false,"Format is empty");
			}
			$con=$this->con;
			if($con == false) {
				return array(false,$con->connect_error);
			}
			$array = $this->seperateArrays($data);
			$columns = $array[0];
			$values = $array[1];
			$colPlaceholder = $this->getPlaceholderWithColumnName($columns);
			if($whereFormat == NULL) {
				$query = "UPDATE ".$table." SET " .$colPlaceholder .";";  
			}
			else {
				$query = "UPDATE ".$table." SET " .$colPlaceholder ." WHERE ".$whereFormat.";";	
				if($whereValues != NULL) {
					$whFormat = $whereValues[0];
					$valuesToAppend = array();
					for ($i=1; $i < count($whereValues); $i++) { 
						$valuesToAppend[] = $whereValues[$i];
					}

					// append whereFormat
					$format.=$whFormat;
					$values = array_merge($values, $valuesToAppend);
				}
			}
			// Prepend the format to $values
			array_unshift($values, $format);
			$stmt=$con->prepare($query);
			call_user_func_array(array($stmt, 'bind_param'), $this->refValues($values));
			if($stmt->execute()) {
				if($stmt->affected_rows) {
                    $result=$stmt;
					return array(true, $result);
				} 	
			}
			$error = mysqli_error($con);
			return array(false,$error);
		}

		function select($table, $columnsToSelect, $whereFormat = NULL, $whereValues = NULL, $extra = NULL) {
			if(empty($table)) {
				return array(false,"Table Name is empty");
			}
			if(is_array($table) && $table[1] == 'distinct') {
				$q_start = "SELECT DISTINCT ";
				$table = $table[0];
			}
			else
				$q_start = "SELECT ";

			if(empty($columnsToSelect)) {
				return array(false,"Columns to select are empty");
			}
			$con=$this->con;
			if($con == false) {
				return array(false,$con->connect_error);
			}
			if($whereFormat != NULL) {
				$query = $q_start.$columnsToSelect." FROM ".$table." WHERE ".$whereFormat ." ".$extra.";";
				if($whereValues!=NULL) {
					$values = array();
					$format = $whereValues[0];				
					for ($i=1; $i < count($whereValues); $i++) { 
						$values[] = $whereValues[$i];
					}
					array_unshift($values, $format);
					$stmt=$con->prepare($query);
					call_user_func_array(array($stmt, 'bind_param'), $this->refValues($values));
					if($stmt->execute()) {

						$result = $stmt->get_result();
						return array(true,$result);
					}
					else {
						$error = mysqli_error($con);
						return array(false,$error);
					}		
				}
			}
			else {
				$query = $q_start.$columnsToSelect." FROM ".$table." ".$extra.";";	
			}
			$stmt=$con->prepare($query);
			if($stmt->execute()) {
				return array(true, $stmt->get_result());
			}
		}
		// can be used to start transaction
		public function transaction() {
			return $this->query("START TRANSACTION");
		}
		// can be used to commit transaction
		public function commit() {
			return $this->query("START TRANSACTION");
		}
		// can be used for rollback
		public function rollback() {
			return $this->query("ROLLBACK");
		}
		/*
			This function creates a placeholder of ?,? by fetching the array of values or names of columns in insert query	
		*/
		private function getPlaceholder($array) {
			
			$placeholder = NULL;
			$len = count($array);
			for ($i=0; $i < $len; $i++) { 
				if($i == 0 && $len == 0) {
					return false;
				}
				if($i == 0 && $i != $len - 1 ) {
					$placeholder = "?,";	
				}
				else if($i == $len - 1) {
					$placeholder = $placeholder . "?";
				}
				else {
					$placeholder .= "?,";
				}
			}
			return $placeholder;
		}

		/* this function is used to seperate the two arrays values and columns for insert statements, it makes it easy for the user to insert data */
		private function seperateArrays($array) {
			foreach ($array as $field => $value) {
				$fields[] = $field;
				$values[] = $value;
			}
			return array($fields, $values);
		} 

		/*This function generates the statement after set for update query */
		private function getPlaceholderWithColumnName($array_of_column_names) {
			$str = NULL;
			foreach ($array_of_column_names as $key => $value) {
				 $str .= $value . "=?, ";
			}
			$str = substr($str, 0, -2);
			return $str;
		}

		/* This function is required beacuse bind_params requires referrence values */
		private function refValues($array) {
			$refs = array();
			foreach ($array as $key => $value) {
				$ref[$key] = &$array[$key]; 
			}
			return $ref; 
		}

    };
    
