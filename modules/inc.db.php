<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
		this file has been rewritten to take advantage of the new mysqli interface instead of pear::db
	*/


	class SwisdkDB {
		public static $connection = null;
		
		public function __construct()
		{
			$config = SwisdkRegistry::getInstance()->getValue( '/config/database/*', true, true );
			SwisdkDB::$connection = new mysqli( $config[ 'host' ], $config[ 'user' ],
							$config[ 'password' ], $config[ 'database' ] );
			if( mysqli_connect_errno() ) {
				SwisdkError::handle( new DBError( "Connect failed: " . mysqli_connect_error() ) );
				exit();
			}
		}
		
		public function __destruct()
		{
			SwisdkDB::$connection->close();
		}

		public static function getInstance()
		{
			static $instance = null;
			if( $instance === null ) {
				$instance = new SwisdkDB();
			}
			
			return $instance;
		}
		
		public function setConnection( &$connection )
		{
			SwisdkDB::$connection = $connection;
		}

		public function &getConnection()
		{
			if( !SwisdkDB::$connection )
				SwisdkDB::getInstance();
			return SwisdkDB::$connection;
		}
		
		public function query( $sql, $triggererror = true )
		{
			if( !SwisdkDB::$connection )
				SwisdkDB::getInstance();
			if( $triggererror && !( $result = SwisdkDB::$connection->query( $sql ) ) ) {
				SwisdkError::handle( new DBError( 'Query failed: ' . SwisdkDB::$connection->error, $sql ) );
			}
			return $result;
		}
		
		public function getAll( $sql, $orderKey = null, $triggererror = true, $params = array() )
		{
			if( $triggererror && !( $result = SwisdkDB::$connection->query( $sql ) ) ) {
				SwisdkError::handle( new DBError( 'Query failed: ' . SwisdkDB::$connection->error, $sql ) );
			}
			$res = array();
			if( $orderKey ) {
				if( is_array( $orderKey ) && ( $key = $orderKey[0] ) && ( $value = $orderKey[1] ) ) {
					while( $row = $result->fetch_assoc() ) {
						$res[ $row[ $key ] ] = $row[ $value ];
					}
				} else {
					while( $row = $result->fetch_assoc() ) {
						$res[ $row[ $orderKey ] ] = $row;
					}
				}
			} else {
				while( $row = $result->fetch_assoc() ) {
					$res[] = $row;
				}
			}
			
			$result->close();
			return $res;
		}
		
		public function getRow( $sql, $triggererror = true, $params = array() )
		{
			return SwisdkDB::query( $sql, $triggererror, $params )->fetch_assoc();
		}

		public function selectOperation( $table, $primaryKey = null, $triggererror = true )
		{
			$where = SwisdkDB::getWhereAndClause( $keys );
			if( $where === false ) {
				SwisdkError::handle( new DBError( "Primary keys are invalid!" ) );
				return false;
			}
			
			return SwisdkDB::getRow( "SELECT $table.* FROM $table WHERE $where", $triggererror );
		}

		public function insertOperation( $table , $data , $primarykey = null, $triggererror = true )
		{
			$fields = '';
			$args = array( null, '' );
			
			foreach( $data as $key => $value ) {
				$fields .= ",$key=?";
				if( is_numeric( $value ) ) {
					$args[1] .= 'i';	// integer
				} else {
					$args[1] .= 's';	// string
				}
				$args[] =& $data[ $key ];
			}
			
			$sql = "INSERT INTO $table SET " . substr( $fields, 1 );
			
			$conn =& SwisdkDB::getConnection();
			
			$stmt = $conn->prepare( $sql );
			$args[0] = $stmt;
			$ret = call_user_func_array( 'mysqli_stmt_bind_param', $args );
			
			$stmt->execute();
			
			if( $stmt->errno ) {
				if( $triggererror ) {
					SwisdkError::handle( new DBError( 'insertOperation failed: ' . $stmt->error, $sql ) );
				} else {
					return $stmt->errno;
				}
			}
			
			$stmt->close();
			
			return $conn->insert_id;
		}
		
		function updateOperation( $table , $data , $keys , $triggererror = true )
		{
			$sql = '';
			$args = array( null, '' );
			
			foreach( $data as $key => $value ) {
				
				if( is_numeric( $value ) ) {
					$args[1] .= 'i';
				} else {
					$args[1] .= 's';
				}
				
				$sql .= ",$key=?";
			}
			
			$where = SwisdkDB::getWhereAndClause( $keys );
			if( $where === false ) {
				// oops!
				return false;
			}
			
			$sql = "UPDATE $table SET " . substr( $sql, 1 ) . ' WHERE ' . $where;
			
			$conn =& SwisdkDB::getConnection();
			
			$stmt = $conn->prepare( $sql );
			$args[0] = $stmt;
			$ret = call_user_func_array( 'mysqli_stmt_bind_param', $args );
			
			$stmt->execute();
			
			if( $stmt->errno ) {
				if( $triggererror ) {
					SwisdkError::handle( new DBError( 'updateOperation failed: ' . $stmt->error, $sql ) );
				} else {
					return $stmt->errno;
				}
			}
			
			$stmt->close();
			
			return true;
		}
		
		/**
		*	Deletes all elements in the table with the specified keys
		* 	@param $keys
		*/
		function deleteOperation( $table , $keys , $triggererror = true ) {

			$sql = "DELETE FROM $table WHERE " . SwisdkDB::getWhereAndClause( $keys ) . ";";
			$queryresult = SwisdkDB::query( $sql , $triggererror );
			return true;
		}
		
		/**
		*	Assembles from the input array a sql where clausel. All elements gets grouped with an "AND"
		*	@param $keys has to be an array of strings
		*/
		function getWhereAndClause( $keys ) {
			
			if( !is_array( $keys ) )
				return false;
				
			$sql = "";
			$f = false;
			foreach( $keys as $k => $v ) {
				if( $f ) {
					$sql .= ' AND';
				}
				$sql .= " $k='" . SwisdkDB::escapeString( $v ) . "'";
				$f = true;
			}
			
			return $sql;
		}
		
		/**
		*	Checks if in the table the entry of primarykey with the value id exists.
		*	@param $table on which the table search should happen
		*	@param $id which values should 
		*/
		function keyExists( $table, $colname , $data , $excludeidcol = null , $excludeid = null ) {
			$sql = "SELECT $colname FROM " . $table . " WHERE $colname='" . SwisdkDB::escapeString( $data ) . "'";
			if( $excludeidcol != null ) {
				$sql = $sql . " AND " . $excludeidcol . "!='" . SwisdkDB::escapeString( $excludeid ) . "'";
			}
		
			$sql = $sql . ";";
			$queryresult =& SwisdkDB::query( $sql , true );
			if( $queryresult->num_rows > 0 ) {
				return true;
			}
	
			return false;		
		}

		public static function escapeString( $string )
		{
			if( !SwisdkDB::$connection )
				SwisdkDB::getInstance();
			return SwisdkDB::$connection->real_escape_string( $string );
		}
	}

?>
