<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class DataObject {
		
		/**
		*	factory method
		*/
		public static function &create( $class, $data = null , $setupReferences = true, $translationDO = false )
		{
			$obj = null;
			if( isset( DataObject::$dataObjects[ $class ] ) ) {
				$obj = clone DataObject::$dataObjects[ $class ];
			} else {
				$obj = new DataObject( $class, null, false, $translationDO );
				DataObject::$dataObjects[ $class ] = clone $obj;
			}
			if( $setupReferences )
				$obj->setupReferences();
			if( null !== $data )
				$obj->setData( $data );
			return $obj;
		}

		public static function &find($class, $id)
		{
			$do = DataObject::create($class);
			$do->initById($id);
			return $do;
		}

		private static $dataObjects = array();

		
		
		// the following variables should really be protected or private but since there
		// are no "friend" relationships I have to make them all public
		// dataobjectcontainer needs direct access to them
		public $table;
		public $primaryKey;
		public $fieldPrefix;
		public $joinPrefix;
		public $joinField;
		public $fields = array();
		public $fulltextSearchFields = array();
		
		public $data = array();
		public $joinedObjects = array();
		
		// variable variables own your mind
		public $foreignLinks = array();
		
		/**
		*	maps db fields to their corresponding data objects
		*/
		protected $fieldMap = array();
		
		/**
		*	configuration of related containers
		*	see function __call ( getXyzDOC )
		*/
		protected $dataObjectContainerConfig = array();

		/**
		*	holds the datamapping configuration
		*/
		public static $xmlDOC = null;

		/**
		*	see mysqli documentation
		*/
		protected $preparedArgs = null;
		
		
		/**
		*	multi-language data
		*/
		public $multiLanguageDO = false;
		public $translationDO = false;
		public $translationDOclass;
		public $languageField;

		/**
		*	constructor is protected, always use the factory method
		*/
		protected function __construct( $class, $data = null, $setupReferences = true, $translationDO = false )
		{
			// load datamapping configuration
			if( null === DataObject::$xmlDOC ) {
				DataObject::$xmlDOC = simplexml_load_file( CONTENT_ROOT . 'datamapping.xml' );
			}

			// get configuration of requested dataobject
			$nodes = DataObject::$xmlDOC->xpath( "//dataobject[@name='$class']" );
			if( isset( $nodes[0] ) ) {
				$xml = $nodes[0];
			} else {
				SwisdkError::handle( new FatalError( "Error while constructing DataObject: $class unknown" ) );
			}
			
			$this->table = (string)$xml->table;
			$this->primaryKey = (string)$xml->primaryKey;
			$this->fieldPrefix = (string)$xml->fieldPrefix;
			$this->preparedArgs = '';
			
			if( isset( $xml->joinField ) ) {
				$this->joinField = (string)$xml->joinField;
			} else	$this->joinField = $this->primaryKey;
			
			
			foreach( $xml->fields->children() as $value ) {
				$this->fields[] = $svalue = (string)$value;
				$attr = $value->attributes();
				$this->preparedArgs .= ( $type = (string)$attr[0] );
				if( 's' == $type ) {
					$this->fulltextSearchFields[] = $svalue;
				}
				$this->data[ (string)$value ] = null;
			}
			
			if( isset( $xml->foreignLinks ) ) {
				foreach( $xml->foreignLinks->children() as $link ) {
					$attr = $link->attributes();
					$this->foreignLinks[ (string)$attr['var'] ] =
						array( (string)$attr['table'], (string)$attr['key'], false );
					if( isset( $attr['full'] ) && 'true' == (string)$attr[ 'full' ] ) {
						$this->foreignLinks[ (string)$attr['var'] ][2] = true;
					}
					if( isset( $attr['class'] ) ) {
						$this->dataObjectContainerConfig[ (string)$attr['class'] ] = array(
							null, (string)$attr['var'] );
					}
					$this->data[ (string)$attr['var'] ] = array();
				}
			}
			
			/* if there is a child dataobject, always assume it is a translation do */
			if( isset( $xml->dataobject ) ) {
				$this->multiLanguageDO = true;
				$attr = $xml->dataobject->attributes();
				$this->translationDOclass = (string)$attr['name'];
			}
			
			if( $translationDO ) {
				$this->translationDO = true;
				$this->languageField = (string)$xml->languageField;
			}
			
			if( $setupReferences )
				$this->setupReferences();

			if( $data !== null )
				$this->setData( $data );
		}
		
		/**
		*	setup fieldMap references
		*	should be protected too, but DataObjectContainer needs access
		*/
		public function setupReferences()
		{
			foreach( $this->fields as $field ) {
				$this->fieldMap[ $field ] =& $this;
			}
			
			foreach( array_keys( $this->foreignLinks ) as $link ) {
				$this->fieldMap[ $link ] =& $this;
			}
			
			foreach( $this->dataObjectContainerConfig as &$cfg ) {
				if( null === $cfg[0] )
					$cfg[0] =& $this;
			}
			
			if( $this->multiLanguageDO ) {
				$this->addJoin( $this->primaryKey, $this->translationDOclass, '', true );
			}
		}
		
		/**
		*	add another dataobject for a join query
		*	@param field		which db field to join on
		*	@param dataobject	dataobject or dataobject class
		*	@param prefix		prefix to add to all fields in the joined table
		*/
		public function addJoin( $field, $dataobject, $prefix = '', $translationDO = false )
		{
			$class = '';
			$do = null;
			if( $dataobject instanceof DataObject ) {
				$class = get_class( $dataobject );
				$do = $dataobject;
			} else {
				$class = $dataobject;
				$do = DataObject::create( $dataobject, null, true, $translationDO );
			}
			if( isset( $this->joinedObjects[ "{$prefix}$class" ] ) ) {
				SwisdkError::handle( new FatalError(
					"already joined $class-type dataobject" . ($prefix?" (prefix: $prefix)":'' ) ) ); 
			}
			if( $prefix ) {
				$do->joinPrefix = $prefix;
			}
			if( !isset( $this->fieldMap[ $field ] ) ) {
				SwisdkError::handle( new FatalError( "can't join on nonexistent field $field" ) );
			}
			foreach( $do->fields as $f ) {
				$this->fieldMap[ "{$prefix}$f" ] =& $do;
			}
			foreach( array_keys( $do->foreignLinks ) as $link ) {
				$this->fieldMap[ "{$prefix}$link" ] =& $do;
			}
			$this->joinedObjects[ $field ] =& $do;
			
			$this->fieldMap = array_merge( $this->fieldMap, $do->fieldMap );
		}
		
		/**
		*	set all data at once
		*	@param data	array holding new data
		*/
		
		public function setData( $data )
		{
			if( $data && count( $data ) ) {
				foreach( $data as $key => &$value ) {
					$this->$key = $value;
				}
			}
		}

		/**
		*	get data stored in dataobject as an array
		*	@return array
		*/
		public function getData()
		{
			return $this->getDataHelper1();
		}
		
		/**
		*	do not collect foreign link data from joined objects
		*/
		private function getDataHelper1( $collect = true )
		{
			$data = $this->data;
			foreach( $this->joinedObjects as &$obj ) {
				$data = array_merge( $data, $obj->getDataHelper1( false ) );
			}
			if( $collect ) {
				foreach( array_keys( $this->foreignLinks ) as $link ) {
					$data[ $link ] = $this->$link;
				}
			}
			return $data;
		}
		
		/**
		*	store data in database
		*	@param forceNewRecord	force insert even if data already exists in database
		*/
		public function store( $forceNewRecord = false )
		{
			if( isset( $this->data[ $this->primaryKey ] ) && $this->data[ $this->primaryKey ] && !$forceNewRecord ) {
				$ret = $this->update();
			} else {
				$ret = $this->insert();
			}
			
			foreach( $this->foreignLinks as $var => $link ) {
				$this->storeForeignKeyIds( $link[0], $link[1], $this->$var );
			}
			
			foreach( $this->joinedObjects as &$obj ) {
				$obj->store();
			}
			
			return $ret;
		}
		
		protected function insert( $triggererror = true )
		{
			unset( $this->data[ $this->primaryKey ] );
			$conn =& SwisdkDB::getConnection();
			
			$stmt = $conn->prepare( $this->getInsertSQL() );
			if( $stmt === false ) {
				SwisdkError::handle( new DBError( 'prepare update statement failed: ' . $conn->error, $this->getUpdateSQL() ) );
			}
			$params = array( $stmt, $this->preparedArgs );
			foreach( $this->fields as $f ) {
				$params[] =& $this->data[$f];
			}
			$ret = call_user_func_array( 'mysqli_stmt_bind_param', $params );
			$stmt->execute();

			if( $stmt->errno ) {
				if( $triggererror ) {
					SwisdkError::handle( new DBError( 'insert failed: ' . $stmt->error ) );
				} else {
					return $stmt->errno;
				}
			}
			
			$stmt->close();
			
			return $conn->insert_id;
		}
		
		protected function update( $triggererror = true )
		{
			$conn =& SwisdkDB::getConnection();
			
			$stmt = $conn->prepare( $this->getUpdateSQL() );
			if( $stmt === false ) {
				SwisdkError::handle( new DBError( 'prepare update statement failed: ' . $conn->error, $this->getUpdateSQL() ) );
			}
			$params = array( $stmt, $this->preparedArgs );
			foreach( $this->fields as $f ) {
				$params[] =& $this->data[$f];
			}
			$ret = call_user_func_array( 'mysqli_stmt_bind_param', $params );
			$stmt->execute();

			if( $stmt->errno ) {
				if( $triggererror ) {
					SwisdkError::handle( new DBError( 'update failed: ' . $stmt->error, $sql ) );
				} else {
					return $stmt->errno;
				}
			}
			
			$stmt->close();
			
			return true;
		}
		
		public function delete()
		{
			if( isset( $this->data[ $this->primaryKey ] ) && $data = $this->data[ $this->primaryKey ] ) {
				SwisdkDB::deleteOperation( $this->table,
					array( $this->primaryKey => $data ) );
				unset( $this->data[ $this->primaryKey ] );
				return true;
			}
			return false;
		}
		
		public function __get( $field )
		{
			if( false === strpos( $field, '_' ) )
				$field = "{$this->fieldPrefix}$field";
			return $this->fieldMap[ $field ]->data[ $field ];
		}
		
		public function __set( $field, $value )
		{
			if( false === strpos( $field, '_' ) )
				$field = "{$this->fieldPrefix}$field";
			return $this->fieldMap[ $field ]->data[ $field ] = $value;
		}
		
		public function __call( $method, $params )
		{
			preg_match( '/(?U)get(.*DO)C/', $method, $matches );
			if( isset( $matches[1] ) && isset( $this->dataObjectContainerConfig[ $matches[1] ] ) ) {
				$config =& $this->dataObjectContainerConfig[$matches[1]];
				$doc = DataObjectContainer::create( $matches[1] );
				$doc->initByIds( array_keys( $config[0]->$config[1] ) );
				return $doc;
			}

			SwisdkError::handle( new FatalError( "Method $method unknown on DataObject" ) );
		}
		
		/**
		*	@param id	unique id identifying the record (primary key)
		*	@param field	optional parameter, do initialise on another field than
		*			the primary key. you will probably never use this
		*/
		public function initById( $id, $field = null )
		{
			if( $field === null ) {
				$field = $this->primaryKey;
			}
			
			if( is_numeric( $id ) ) {
				$data = SwisdkDB::getRow( $this->getSelectSQL() . " AND {$this->table}.{$this->primaryKey}=$id" );
				$this->setData( $data );
				foreach( $this->foreignLinks as $var => &$link ) {
					$this->$var = $this->getForeignKeyIds( $link[0], $link[1], $link[2] );
				}
			}
			
			SwisdkError::handle( new DBError( "initById: $id not numeric" ) ); 
		}
		
		
		/**
		*	HELPERS
		*/
		
		
		/**
		*	Use this function to store f.e. the categories of a newsentry or the groups a user belongs to
		*	usage:	$obj->storeForeignKeys( 'tbl_user_to_user_groups', 'group_id', $userGroups );
		*/
		protected function storeForeignKeyIds( $linkTable, $foreignKey, $foreignIdArray )
		{
			if( !is_array( $foreignIdArray ) ) {
				$foreignIdArray = array( $foreignIdArray );
			}
			$db =& SwisdkDB::getConnection();
			$db->autocommit( false );
			// delete all group relations ...
			SwisdkDB::query( "DELETE FROM {$linkTable} WHERE {$this->primaryKey}='{$this->data[ $this->primaryKey ]}'" );
			foreach( $foreignIdArray as $foreignId ) {
				// ... and reinsert the new one by one
				if( is_array( $foreignId ) ) {
					// if it's an array i assume everything that needs to be set is already set
					if( $foreignId[ $foreignKey ] )
						SwisdkDB::insertOperation( $linkTable, $foreignId );
				} else {
					if( $foreignId )
						SwisdkDB::insertOperation( $linkTable, array( $this->primaryKey => $this->id, $foreignKey => $foreignId ) );
				}
			}
			$db->commit();
			$db->autocommit( true );
		}
		
		/**
		*	usage:	$userGroups = $obj->getForeignKeyIds( 'tbl_user_to_user_groups', 'group_id' );
		*/
		protected function getForeignKeyIds( $linkTable, $foreignKey, $alldata = false )
		{
			$_ids = SwisdkDB::getAll( "SELECT * FROM {$linkTable} WHERE {$this->primaryKey}='{$this->data[ $this->primaryKey ]}'" );
			$ids = array();
			if( $alldata ) {
				foreach( $_ids as $id ) {
					$ids[ $id[ $foreignKey ] ] = $id;
				}
			} else {
				foreach( $_ids as $id ) {
					$ids[ $id[ $foreignKey ] ] = $id[ $foreignKey ];
				}
			}
			if( isset( $ids[0] ) && !$ids[0] ) {
				unset( $ids[0] );
				if( !is_array( $ids ) ) {
					return array();
				}
			}
			return $ids;
		}
		

		
		
		/**
		*	SQL Helpers
		*/
		
		private function getInsertSQL()
		{
			return "INSERT INTO {$this->table} SET " . implode( '=?,', $this->fields ) . '=?';
		}
		
		private function getUpdateSQL()
		{
			$where = SwisdkDB::getWhereAndClause( array( $this->primaryKey => $this->data[ $this->primaryKey ] ) );
			return "UPDATE {$this->table} SET " . implode( '=?,', $this->fields ) . "=? WHERE $where";
		}
		
		public function getSelectSQL( $callerIsDOC = false )
		{
			$langClause = '1';
			if( $this->multiLanguageDO ) {
				//$language = LanguageHandler::getCurrentLanguageId();
				$language = 1;
				
				foreach( $this->joinedObjects as &$do ) {
					if( $do->languageField ) {
						$langClause .= " AND {$do->table}.{$do->languageField}=$language ";
					}
				}
			}
			
			$fields = '';
			$join = '';
			$joinedObjects = $this->joinedObjects;
			foreach( $this->joinedObjects as &$obj ) {
				$joinedObjects = array_merge( $joinedObjects, $obj->joinedObjects );
			}
			foreach( $joinedObjects as $joinField => &$obj ) {
				if( $prefix = $obj->joinPrefix ) {
					$join .= " LEFT JOIN {$obj->table} AS {$prefix}{$obj->table} ON {$this->fieldMap[$joinField]->table}.{$joinField}={$prefix}{$obj->table}.{$obj->joinField}";
					foreach( $obj->fields as &$field ) {
						$fields .= ", {$prefix}{$obj->table}.{$field} AS {$prefix}$field";
					}
				} else {
					$join .= " LEFT JOIN {$obj->table} ON {$this->fieldMap[$joinField]->table}.{$joinField}={$obj->table}.{$obj->joinField}";
					foreach( $obj->fields as $field ) {
						$fields .= ", {$obj->table}.{$field}";
					}
				}
			}
			foreach( $this->fields as $field ) {
				$fields .= ", {$this->table}.{$field}";
			}
			
			// multilanguage dataobjectcontainer specific hack
			if( count( $this->joinedObjects ) == 0 && $callerIsDOC && $this->multiLanguageDO ) {
				$tdo = DataObject::create( $this->translationDOclass, null, true, true );
				$langClause .= " AND {$tdo->table}.{$tdo->languageField}=$language ";
				$join .= " LEFT JOIN {$tdo->table} ON {$this->table}.{$this->primaryKey}={$tdo->table}.{$tdo->joinField}";
				foreach( $tdo->fields as $field ) {
					$fields .= ", {$tdo->table}.{$field}";
				}
			}
			
			return 'SELECT' . substr( $fields, 1 ) . " FROM {$this->table} $join WHERE $langClause";
		}
		
		public function getFulltextSearchClause( $query )
		{
			$query = SwisdkDB::escapeString( $query );
			if( $query && is_array( $this->fulltextSearchFields ) && count( $this->fulltextSearchFields ) ) {
				return " {$this->table}." . implode( " LIKE \"%{$query}%\" OR {$this->table}.", $this->fulltextSearchFields ) . " LIKE \"%{$query}%\""; 
			}
			return null;
		}
	}
	
	
	
	class DataObjectContainer {
		
		/**
		*	factory method
		*	does not much for now
		*/
		public static function create( $class )
		{
			return new DataObjectContainer( $class );
		}
		
		/**
		*	a single instance of the DataObject holding no data
		*/
		protected $dataObject;
		
		/**
		*	this is the container array
		*/
		protected $dataObjectArray;
		
		/**
		*	the type of dataobject stored in this container
		*/
		protected $dataObjectClass;
		
		/**
		*	container for the SQL query configuration
		*/
		protected $queryVars = array();
		
		protected function __construct( $class )
		{
			$this->dataObject = DataObject::create( $class, null, false );
			$this->dataObjectClass = $class;
			$this->queryVars = array(
				'fulltext' => '',
				'limit' => '',
				'order' => array(),
				'clauses' => array()
			);
		}
		
		/**
		*	@param query	string
		*/
		public function setFulltextQuery( $query )
		{
			$this->queryVars[ 'fulltext' ] = SwisdkDB::escapeString( $query );
		}
		
		/**
		*	@param arg1	integer
		*	@param arg2	integer
		*	See MySQL documentation for details
		*/
		public function setLimit( $arg1, $arg2 = null )
		{
			$this->queryVars[ 'limit' ] = "LIMIT " . SwisdkDB::escapeString( $arg1 ) .
							($arg2 ? ',' . SwisdkDB::escapeString( $arg2 ) : '');
		}
		
		/**
		*	@param orderCol	string (fieldname)
		*	@param dir	sorting directio (ASC or DESC)
		*/
		public function addOrderColumn( $orderCol, $dir = 'ASC' )
		{
			$this->queryVars[ 'order' ][] = SwisdkDB::escapeString( $orderCol ) .
							(strtoupper($dir)=='ASC'?' ASC':' DESC');
		}
		
		/**
		*	Beware! only the data field is properly escaped. If you build your own
		*	clause (and leave the other two parameters empty) you have to properly
		*	escape all arguments yourself.
		*	@param clause
		*	@param data
		*	@param binding	(AND, OR etc.)
		*/
		public function addClause( $clause, $data = null, $binding = 'AND' )
		{
			if( $data !== null ) {
				if( $data instanceof DataObject ) {
					$clause .= "'" . SwisdkDB::escapeString( $data->id ) . "'";
				} else {
					$clause .= "'" . SwisdkDB::escapeString( $data ) . "'";
				}
			}
			$this->queryVars[ 'clauses' ][] = " $binding $clause ";
		}
		
		/**
		*	See DataObject::join()
		*	@param field	string
		*	@param do	dataobject or dataobject class
		*/
		public function addJoin( $field, $do, $prefix = ''  )
		{
			$this->dataObject->addJoin( $field, $do, $prefix );
		}

		public function init( $lang = null )
		{
			$sql = $this->dataObject->getSelectSQL( true ) . $this->getQueryClause();
			return $this->initContainer( $sql, $lang ); 
		}
		
		public function initBySql( $sqlquery )
		{
			return $this->initContainer( $sqlquery );
		}
		
		public function initbyIds( $idArray, $lang = null )
		{
			if( is_array( $idArray ) && count( $idArray ) ) {
				$sql =  $this->dataObject->getSelectSQL( true ) .
					" AND {$this->dataObject->table}.{$this->dataObject->primaryKey} IN (" . 
					SwisdkDB::escapeString( implode( ',', $idArray ) ) . ")" .
					$this->getQueryClause();
				return $this->initContainer( $sql, $lang );
			}
			return false;
		}
		
		public function getData()
		{
			$array = array();
			if( count( $this->dataObjectArray ) ) {
				foreach( $this->dataObjectArray as &$dataObject ) {
					$array[ $dataObject->id ] = $dataObject->getData();
				}
			}
			
			return $array;
		}
		
		public function store()
		{
			foreach( $this->dataObjectArray as &$dataObject ) {
				$dataObject->store();
			}
		}
		
		/**
		*	delete all dataobjects
		*/
		public function delete()
		{
			foreach( $this->dataObjectArray as &$dataObject ) {
				$dataObject->delete();
			}
		}
		
		
		/**
		*	used in conjunction with the list component
		*/
		public function initBySearchForm( $data )
		{
			if( isset( $data[ 'start_element' ] ) && isset( $data[ 'element_limit' ] ) )
				$this->setLimit( $data[ 'start_element' ], $data[ 'element_limit' ] );
			if( $data[ 'sort_col' ] && $data[ 'sort_dir' ] )
				$this->addOrderColumn( $data[ 'sort_col' ], $data[ 'sort_dir' ] );
			if( $data[ 'search_string' ] )
				$this->setFulltextQuery( $data[ 'search_string' ] );
			$this->init();

		}
		
		/**
		*	how many items are there in the database matching our query?
		*/
		public function getTotalCount()
		{
			$sql = $this->dataObject->getSelectSQL() . $this->getQueryClause();
			$sql = preg_replace(
				array( '/SELECT \*/', '/ LIMIT.*/' ),
				array( "SELECT COUNT(DISTINCT {$obj->table}.{$obj->primaryKey}) AS count", "" ),
				$sql );
			$res = SwisdkDB::getSingle( $sql );
			return $res[ 'count' ];
		}

		/**
		*	@param field	string
		*/
		public function collectFieldValues( $field, $lang = null )
		{
			$array = array();
			if( count( $this->dataObjectArray ) ) {
				foreach( array_keys( $this->dataObjectArray ) as $id ) {
					$array[ $id ] = $this->dataObjectArray[$id]->$field;
				}
			}
			return $array;
		}

		/**
		*	get an element from the container by its primary id
		*/
		public function getById( $id )
		{
			if( isset( $this->dataObjectArray[ $id ] ) ) {
				return $this->dataObjectArray[ $id ];
			} else	return null;
		}
		
		/**
		*	first and next provide iterators for all dataobjects in the container
		*/
		public function first()
		{
			return reset( $this->dataObjectArray );
		}
		
		public function next()
		{
			return next( $this->dataObjectArray );
		}
		
		public function getCount()
		{
			return count( $this->dataObjectArray );
		}
		
		public function getIds()
		{
			return array_keys( $this->dataObjectArray );
		}

		
		/**
		*	HELPERS
		*/

		protected function initContainer( $sql, $lang = null )
		{
			$conn =& SwisdkDB::getConnection();
			
			if( !$result = $conn->query( $sql ) ) {
				SwisdkError::handle( new DBError( 'Query failed: ' . SwisdkDB::$connection->error, $sql ) );
			}

			while( $row = $result->fetch_assoc() ) {
				$dataObject = clone $this->dataObject;
				$dataObject->setupReferences();
				$dataObject->setData( $row );
				$this->dataObjectArray[ $dataObject->id ] = $dataObject;
			}
			
			if( count( $this->dataObjectArray ) ) {
				$ids = implode( ',', array_keys( $this->dataObjectArray ) );
				foreach( $this->dataObject->foreignLinks as $var => &$link ) {
					$links = array();
					$data = SwisdkDB::getAll( "SELECT * FROM {$link[0]} WHERE {$this->dataObject->primaryKey} IN ($ids)" );
					foreach( $data as &$d ) {
						if( $link[2] ) {
							$links[ $d[ $this->dataObject->primaryKey ] ][ $d[ $link[1] ] ] = $d;
						} else {
							$links[ $d[ $this->dataObject->primaryKey ] ][ $d[ $link[1] ] ] = $d[ $link[1] ];
						}
					}
					foreach( $links as $key => &$value ) {
						$this->dataObjectArray[ $key ]->$var = $value;
					}
				}
			}
			
			return true;
		}
		
		/**
		*	SQL Helper
		*/
		protected function getQueryClause( $includeLimit = true )
		{
			$fulltext = $this->dataObject->getFulltextSearchClause( $this->queryVars[ 'fulltext' ] );
			if( count( $this->queryVars[ 'order' ] ) )
				$order = 'ORDER BY ' . implode( ',', $this->queryVars[ 'order' ] );
			else	$order = '';
			$limit = $this->queryVars[ 'limit' ];
			$clauses = implode( '', $this->queryVars[ 'clauses' ] );
			return ($fulltext?" AND ($fulltext)":'') . " $clauses $order " . ($includeLimit?$limit:'');
		}
		
		
		/**
		*	filters the dataobject list for objects on which the user may operate
		*/
		public function filterByPermission( $operation )
		{
			if( count( $this->dataObjectArray ) ) {
				foreach( array_keys( $this->dataObjectArray ) as $key ) {
					if( ! $this->dataObjectArray[ $key ]->checkPermission( $operation ) ) {
						unset( $this->dataObjectArray[ $key ] );
					}
				}
			}
		}
	}

?>
