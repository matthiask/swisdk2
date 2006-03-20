<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class DOMXMLClipboardBase {
		
		/**
		*	variable holding the DOM Document
		*/
		protected $doc;
		
		/**
		*	xpath-expression for the root element of all
		*	queries
		*/
		protected $rootElement;
		
		protected function __construct( $xmlFile )
		{
			$this->doc = new DomDocument();
			$this->doc->preserveWhiteSpace = false;
			$this->doc->load( SWISDK_ROOT . $xmlFile );
		}
		
		/**
		*	singleton accessor
		*/
		public static function getInstance()
		{
			static $instance = null;
			if( $instance === null ) {
				$instance = new SwisdkRegistry();
			}
			
			return $instance;
		}
		
		/**
		*	@returns DOM Document
		*/
		public function getDOM()
		{
			return $this->doc;
		}
		
		/**
		*	retrieve a value from the registry
		*
		*	@param key
		*	@param multiple: return multiple values if key matches more than once
		*	@param assoc: use DOMNode Names as array keys
		*/
		public function getValue( $key, $multiple = false, $assoc = false )
		{
			$xpath = new DOMXPath( $this->doc );
			$entries = $xpath->query( "{$this->rootElement}$key" );
			if( $multiple ) {
				$ret = array();
				$count = $entries->length;
				if( $assoc ) {
					for( $i=0; $i<$count; $i++) {
						$ret[ $entries->item($i)->tagName ] = $entries->item($i)->nodeValue;
					}
				} else {
					for( $i=0; $i<$count; $i++) {
						$ret[] = $entries->item($i)->nodeValue;
					}
				}
				return $ret;
			}
			
			if( $entries->length ) {
				return $entries->item(0)->nodeValue;
			}
			
			return null;
		}

		/**
		*	set a value in the registry creating the key/value pairs beforehand
		*	if necessary
		*	
		*	@param key
		*	@param value
		*/
		public function setValue( $key, $value )
		{
			$keys = explode( '/', $key );
			
			// try to find nearest match for the key passed.
			$entries = array();
			while( count( $keys ) ) {
				$xpath = new DOMXpath( $this->doc );
				$entries = $xpath->query( $this->rootElement . implode( '/', $keys ) );
				if( $entries->length ) {
					break;
				}
				
				array_pop( $keys );
			}

			$node = $entries->item(0);
			
			// build parent nodes of key if they are not here already
			$newnodes = array_slice( explode( '/', $key ) , count( $keys ) );
			$element = $node;
			foreach( $newnodes as $newnode ) {
				$element = $this->doc->createElement( $newnode );
				$node->appendChild( $element );
				$node = $element;
			}

			// if there is already a DOMText node, replace its content with the new value...
			$count = $element->childNodes->length;
			if( $count ) {
				$i = 0;
				for( $i=0; $i<$count; $i++ ) {
					if( $element->childNodes->item($i) instanceof DOMText ) {
						// if we found a DOMText node, replace its content with
						// the new value
						$element->childNodes->item($i)->replaceData( 0, 65535, $value );
						return;
					}
				}
			}
			
			// if there were no children OR if none of the children was a DOMText node
			// create a new node and assign the value to it
			$element->appendChild( new DOMText( $value ) );
		}
	}

?>
