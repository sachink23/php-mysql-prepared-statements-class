<?php
	
	class db {

		private $host, $user, $pass, $database;

		function initialize($dbHost = dbHost, $dbUser = dbUser, $dbPass = dbPass, $dbName = dbName) {
			$this->host = $dbHost;
			$this->user = $dbUser;
			$this->pass = $dbPass;
			$this->database = $dbName;

		}

		private function connect() {
			$con = new mysqli($this->host, $this->user, $this->pass, $this->database);
			if($con->connect_error) {
				return false;
			}
			return $con;
		}

		function query($query) {
			$con=$this->connect();
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

			$con=$this->connect();
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
					$stmt->close();
					$con->close();	
					return array(true, $stmt);
				}
			}
			$error = mysqli_error($con);
			$stmt->close();
			$con->close();
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
			$con=$this->connect();
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
					$whFormat = $wherevalues[0];
					$valuesToAppend = array();
					for ($i=1; $i < count($wherevalues); $i++) { 
						$valuesToAppend[] = $wherevalues[$i];
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
                    $stmt->close();
					$con->close();	
					return array(true, $result);
				} 	
			}
			$error = mysqli_error($con);
			$stmt->close();
			$con->close();
			return array(false,$error);
		}

		function select($table, $columnsToSelect, $whereFormat = NULL, $whereValues = NULL) {
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
			$con=$this->connect();
			if($con == false) {
				return array(false,$con->connect_error);
			}
			if($whereFormat != NULL) {
				$query = $q_start.$columnsToSelect." FROM ".$table." WHERE ".$whereFormat .";";
				if($whereValues!=NULL) {
					$values = array();
					$format = $whereValues[0];				
					for ($i=1; $i < count($whereValues); $i++) { 
						$values[] = $whereValues[$i];
					}
					
					$values = $this->seperateArrays($whereValues)[0];
					array_unshift($values, $format);
					$stmt=$con->prepare($query);
					call_user_func_array(array($stmt, 'bind_param'), $this->refValues($values));
					if($stmt->execute()) {

						$result = $stmt->get_result();
						$stmt->close();
						$con->close();
						return array(true,$result);
					}
					else {
						$error = mysqli_error($con);
						$stmt->close();
						$con->close();
						return array(false,$error);
					}		
				}
			}
			else {
				$query = $q_start.$columnsToSelect." FROM ".$table.";";	
			}
			$stmt=$con->prepare($query);
			if($stmt->execute()) {
				return array(true, $stmt->get_result());
			}
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

	}


	  /* Here are few simple examples of using this class (In this example, I have assumed default database "test" */
	$db = new db;
	$db->initiallize("localhost", "username", "password", "test");
	/* Lets create a table first */
	$query = $db->query("CREATE TABLE CUSTOMERS (
	    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	    firstname VARCHAR(30) NOT NULL,
	    lastname VARCHAR(30) NOT NULL,
	    email VARCHAR(50),
	    reg_date TIMESTAMP
	    );
	");
	if($query[0] == true) {
	  echo "Table CUSTOMERS has been successfully created<br/>";
	}
	else {
	    die($query[1]);
	}

	/* lets insert some data in the table */
	$table = "CUSTOMERS";
	$data = array('firstname'=>'Sachin', 'Lastname'=>'Kekarjawalekar', 'email'=>'email@example.com');
	$format = 'sss';
	$query = $db->insert($table, $data, $format);
	if($query[0] == true) {
	    echo "New customer added successfully<br/>";
	}
	else {
	    die($query[1]);
	}
	$data = array('firstname'=>'Santosh', 'Lastname'=>'Kekarjawalekar', 'email'=>'email1@example.com');
	$query = $db->insert($table, $data, $format);
	if($query[0] == true) {
	    echo "New customer added successfully<br/><hr/>";
	}
	else {
	    die($query[1]);
	}
	/* Lets select the added two customers and display them */
	$query = $db->select($table,"*");

	if($query[0] == true) {
	    echo $query[1]->num_rows." customers found<br/><hr/>";
	    echo "<table width='80%' align='center' border='1px'>";
	    $i=0;
	    while($row = $query[1]->fetch_assoc()) {
		if($i==0) {
		    echo "<tr>";
		    foreach ($row as $key => $value) {
			echo "<th>".$key."</th>";
		    }
		    echo "</tr>";
		} 
		echo "<tr>";
		foreach ($row as $key => $value) {
		    echo "<td>".$value."</td>";
		}
		echo "</tr>";
		$i++;
	    }
	    echo "</table><hr/>";
	}
	else {
	    die($query[1]);
	}
	/* Let's update the customer with id=1 */
	$data = array('firstname'=>'Anmol', 'lastname'=>'Kekarjawlekar');
	$format = 'ss';
	$whereFormat = 'id=?';
	$whereValues = array(2=>'i');
	$query = $db->update($table, $data, $format, $whereFormat, $whereValues);
	if($query[0] == true) {
	    echo "Custormer with ID 2 has been updated successfully <br/><hr/>";
	}

	/* Now lets show up the updated customer */

	$query = $db->select($table,"*", $whereFormat, $whereValues);
	if($query[0] == true) {
	    echo $query[1]->num_rows." customers found<br/><hr/>";
	    echo "<table width='80%' align='center' border='1px'>";
	    $i=0;
	    while($row = $query[1]->fetch_assoc()) {
		if($i==0) {
		    echo "<tr>";
		    foreach ($row as $key => $value) {
			echo "<th>".$key."</th>";
		    }
		    echo "</tr>";
		} 
		echo "<tr>";
		foreach ($row as $key => $value) {
		    echo "<td>".$value."</td>";
		}
		echo "</tr>";
		$i++;
	    }
	    echo "</table><hr/>";
	}
	else {
	    die($query[1]);
	}

	/* Now let's turncat the table */
	$query = $db->query("TRUNCATE TABLE ".$table.";");

?>
