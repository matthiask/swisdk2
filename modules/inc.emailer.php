<?php

	require_once MODULE_ROOT . "inc.data.php";
	require_once MODULE_ROOT . "inc.form.php";
	
	
	/**
	*	Interface sending emails.
	*/
	interface IEMailMessageSender 
	{
		public function send_message( EMailMessage $message );
	}

	/**
	*	Standard email sender sends the email with pear::mail_mime
	*	Does currently NOT support cc and bcc receipts.
	*/
	class StandardEmailSender implements IEMailMessageSender
	{
		public function send_message( EMailMessage $message )
		{
			require_once 'Mail.php';
			require_once 'Mail/mime.php';
			
			$mime = new Mail_mime();
			$mime->setTXTBody( $message->body() );

			$hdrs = array(
				'From' => $message->sender(),
				'Subject' => $message->subject(),
				'Reply-To' => $message->sender(),
				'To' => $message->receiver()
			);
			
			$body = $mime->get();
			$hdrs = $mime->headers( $hdrs );
			
			$mail =& Mail::factory( 'mail' );
			return $mail->send( $message->receiver() , $hdrs, $body );
		}
	}
	
	
	class EMailMessage extends DBObject 
	{
		protected $rawdata = null;
	
		protected $class = __CLASS__;
		
		public function rawdata() { return $this->rawdata; }
		public function set_rawdata( $rawdata ) { $this->rawdata = $rawdata; }
		
		public function subject() { return $this->subject; }
		public function set_subject( $subject ) { $this->subject = $subject; }
		
		public function sender() { return $this->sender; }
		public function set_sender( $sender ) { $this->sender = $sender; }
		
		public function receiver() { return $this->receiver; }
		public function set_receiver( $receiver ) { $this->receiver = $receiver; }
		
		public function body() { return $this->body; }
		public function set_body( $body ) { $this->body = $body; }
		
		
		/** 
			Feeds the message with data. Call get_acceptor_method an executes the returned method.
		*/
		public function feed_data( $paramdata )
		{
			// do we accept that type of data? 
			$acceptor_func = $this->get_acceptor_method( $paramdata );
			
			// call data acceptor method
			if( method_exists( $this, $acceptor_func ) ) {
				call_user_func( array($this, $acceptor_func ) , $paramdata  );
			} else {
				return new FatalError( "EMailMessage:: feed_data() - Acceptor method does not exists! Method name is: $acceptor_func" );
			}
			
			return true;
		}
	
		/**
			This method returns a method name wich shoudl accepts the data for the message.
			The EMailMessage supports methods for array, dbobject and simple (string & numbers) data.
			If you want accept your own data subclass this method and add your own acceptor method to 
			the sub-class.
		*/
		public function get_acceptor_method( $data ) {
			if( is_array( $data ) ) {
				return "accept_array_data";
			} else if ( $data instanceof DBObject ) {
				return "accept_dbobj_data";
			} else if ( is_string( $data ) || is_numeric( $data ) ) {
				return "accept_simple_data";
			} else {
				return "";
			}
		}
		
		/*
			Accepts simple data. Strings and Numbers an set the data as array on rawdata
		*/
		public function accept_simple_data( $data )
		{
			$this->set_rawdata( array( "message" => $data ) );
			$this->set_body( $data );
		}
		
		public function accept_array_data( $data ) 
		{
			$this->set_rawdata( $data );
		}
						
		public function accept_dbobj_data( $dbobj ) 
		{
			$this->set_rawdata( $dbobj->data() );
		}
		
		
		public function generate_message() 
		{
			
			$state = $this->generate_subject();
			
			if ( !SwisdkError::is_error( $state = $this->generate_sender() ) &&
				 !SwisdkError::is_error( $state = $this->generate_receivers() ) && 
				 !SwisdkError::is_error( $state = $this->generate_subject() ) && 
				 !SwisdkError::is_error( $state =  $this->generate_body() ) ) {
				return true;
			} else {
				return $state;
			}
		}
		
		public function generate_sender() 
		{
			$rawdata = $this->rawdata();
			if( isset( $rawdata["sender_adress"] ) ) {
				$this->set_sender( $this->get_adress_string( isset( $rawdata["sender_name"] ) ? $rawdata["sender_name"] : "" , $rawdata["sender_adress"] ) );
				return true;
			}
			
			return false;
		}

		public function generate_receivers() 
		{
			$rawdata = $this->rawdata();
			if( isset( $rawdata["receiver_name"] ) ) {
				$this->set_receiver( $this->get_adress_string( isset( $rawdata["receiver_name"] ) ? $rawdata["receiver_name"] : "" , $rawdata["receiver_adress"] ) );
				return true;
			}
			
			return false;
		}
		
		public function generate_subject() 
		{
			$rawdata = $this->rawdata();
			if( isset( $rawdata["subject"] ) ) {
				$this->set_subject( $rawdata["subject"] );
			}
			
			return true;
		}

		public function generate_body() 
		{
			if( $this->body() != "" )
			{
				if( isset( $rawdata["body"] ) ) {
					$this->set_body( $rawdata["body"] );
				} else {
					return false;
				}
			}
			
			return true;
		}
		
		public function get_adress_string( $name , $adress )
		{
			if( isset( $dbobj->message_name ) && $dbobj->message_name != "" )
			{
				return $name .= " <$adress>";
			} else {
				return $adress;
			}
		}
		
	}
	
	class SmartyEmailMessage extends EmailMessage 
	{
		protected $smarty = null;
		protected $template = "";
		
		public function smarty() { return $this->smarty; }
		public function set_smarty( $smarty ) { $this->smarty = $smarty; }
		
		public function template() { return $this->template; }
		public function set_template( $template ) { $this->template = $template; }
		
		public function __construct( $template = "" , $data = null )
		{
			$this->set_smarty( new SwisdkSmarty() ); 
			
			$this->set_template($template);
			
			if( $data ) 
				$this->assign_array_data( $data );
		}
		
		public function accept_dbobj_data( $data ) 
		{
			parent::accept_array_data( $data->data() );
			$this->assign_array_data( $data );
		}
		
		public function assign_array_data( $data )
		{
			$smarty = $this->smarty();
			foreach( $data as $key => $d )
			{
				if( is_string($key) && $key != "" ) {
					$smarty->assign( $key , $d );
				}
			}
		}
		
		public function generate_body() 
		{
			$template = $this->template();
			$smarty = $this->smarty();
			
			if( $smarty->template_exists( $template ) ) {
				
				$messagecontent = $smarty->fetch( $template );
				$messagecontent = $this->check_content_for_subject( $messagecontent );
				$this->set_body( $messagecontent );
				
			} else {
				return new FatalError("SmartyEmailMessage::generate_body() - Template does not exists! Template is: $template ");
			}
			
			return true;
		}
		
		/*
			If the message starts with subject>> use all up to <<subject as subject
		*/
		public function check_content_for_subject( $messagecontent )
		{
			if( substr( $messagecontent, 0 , 9 ) == "subject>>" ) {
				$l = strpos( $messagecontent, "<<subject" );
				$this->set_subject( substr( $messagecontent, 9, $l-9 ) ); 
				return ltrim( substr( $messagecontent , $l+10 ) );
			}
			
			return $messagecontent;
		}
	}

	class EMail
	{
		
		public static $instance = null;
		protected $email_sender = null;
		
		protected function __construct()
		{
			$this->load_email_sender(null, false );
		}
		
		public static function instance()
		{
			if( EMail::$instance === null )
				EMail::$instance = new EMail();

			return EMail::$instance;
		}
		
		public function load_email_sender( $sender = null , $return_error = true )
		{
			if( $sender === null ) {
				$sender = Swisdk::config_value( "emailer.sender" );
			}
		
			if( $sender instanceof IEMailMessageSender ) {
				$this->email_sender = $sender;
			} else {
				$this->email_sender = Swisdk::load_module( $sender , "messenger/engine" );
			}
			if( !( $this->email_sender instanceof IEMailMessageSender) ) {
				 $error = new FatalError( "EMail::send_email() - sender is not instance of IEMailMessageSender" );
				if( $return_error ) {
					return $error;
				} else {
					SwisdkError::handle( $error );
				}
			}
			return true;
		}
	
		/**
		 *	Send a email message. 
		 *	
		 * 	This function has 5 overloads
		 * 	1. send_email( array( "to" => "email@server.ch" , "from" => "info@somewaht.ur" , "subject" => "text" , "body" => "text, "headers" => array() ) )
		 * 	2. send_email( $to, $from, $subject, $body )
		 *	3. send_email( $message , $data )
		 *	4. send_email( $data, $template )
		 *	5. send_email( $message ) message is object or string
		 */
		public function send_email() 
		{
			$args = func_get_args();
			if( count( $args ) == 1 && is_array( $args[0] ) )
			{
				$param_data = array( "receiver_adress" => $args[0]["to"] , "sender_adress" => $args[0]["from"],
					"subject" => $args[0]["subject"], "body" => $args[0]["body"], "headers" => $args[0]["headers"]
					);
				
				return $this->handle_email_data( $param_data ); 
				
			} else if( count( $args ) == 4 ) {
				//instance standard message, create data array from params  
				$param_data = array( "receiver_adress" => $args[0], "sender_adress" => $args[1], 
					"subject" => $args[2], "body" => $args[3]
					);
					
				return $this->handle_email_data( $param_data ); 
				
			} else if( count( $args ) == 2 && (is_array( $args[0] ) && is_string( $args[1] ) )  ) {
				
				//instance template message and add data 
				$message = $this->instance_message( "SmartyEmailMessage" );
				$mesage->set_template( $args[1] );
				return $this->handle_email( $message , $args[0] );
				
			} else if( count( $args ) == 2 && (is_string( $args[0] ) || $args[0] instanceof EMailMessage)  ) {
				//instance message , and call handle emai
				$message = $this->instance_message($args[0]);
				if( SwisdkError::is_error($message) )
					return $message;
				return $this->handle_email( $message , $args[1] );
			} else if( count( $args ) == 1 ) {
				// instance message 
				$message = $this->instance_message($args[0]);
				if( SwisdkError::is_error($message) )
					return $message;
				return $this->handle_email( $message );
			}
				
			return new FatalError( "Wrong number my friend! You called this function in a wrong way!" );
		}
		
		
		
		public function instance_message( $parammessage )
		{
			$msg = null;
			if( $parammessage instanceof EMailMessage ) {
				return $parammessage;
			} else {
				$msg = Swisdk::load_module( $parammessage , "messenger/messages" );
			}
			if( !($msg instanceof EMailMessage) ) {
				return new FatalError( "EMail::instance_message() - 
					message is not instance of EMailMessage" );
			}
			
			return $msg;
		}
		
		public function handle_email_data( $data ) 
		{
			//instance standard message, create data array from array (args0)
			$message = $this->instance_message( Swisdk::config_value("emailer.message") );
			if( SwisdkError::is_error($message) )
				return $message;
			return $this->handle_email( $message , $data );
		}
		
		protected function handle_email( $message , $paramdata )
		{

			// feed message object
			// paramdata can be a string, a dbobject, or wathever
			// see emailmessage::accept data
			if( $paramdata != null ) 
			{
				$state = $message->feed_data( $paramdata );
				if( SwisdkError::is_error($state) ) {
					return $state;
				}
			}
						
			// generate the message , afterwards sender, receiver, subject, and the message body
			// are set...
			$state = $message->generate_message();
			if( SwisdkError::is_error($state) ) {
				return $state;
			}
			
			// send message
			return $this->email_sender->send_message( $message );
			
			// store message? if so store in the database TODO
		}
	}
	
	
	class FormMailer 
	{
		protected $form = null;
		protected $messagetpl = null;
		protected $sitetpl = null;
		protected $formrenderer = null;
		
		public function __construct( $form, $messagetpl , $sitetpl , $formrenderer = 'TableFormRenderer' )
		{
			if( $form instanceof Form ) {
				$this->form = $form;
			} else {
				$this->form = new $form();
			}
			
			$this->messagetpl = $messagetpl;
			$this->sitetpl = $sitetpl;
			$this->formrenderer = $formrenderer;
		}
		
		
		public function html()
		{
			$smarty = new SwisdkSmarty(); // we have in all cases an output...
			$messageSend = false;
			$msg = "";
			
			if( $this->form->is_valid() ) {
				
				$dbobj = $this->form->dbobj();
				$this->edit_dataobject( $dbobj );		
				$state = EMail::instance()->send_email( new SmartyEmailMessage( $this->messagetpl )  , $dbobj  );
				if( SwisdkError::is_error( $state ) )
				{
					SwisdkError::handle( $state );
				} else {
					$msg = "Die Nachricht wurde geschickt.";
					$messageSend = true;
					$this->form->dbobj()->clear();
					$this->form->refresh();
				}
			}
			
			$smarty->assign( "system_message" , $msg );
			$smarty->assign( "message_send" , $messageSend );
			$smarty->assign( "form_html" , $this->form->html( $this->formrenderer ) );
			
			return $smarty->fetch( $this->sitetpl );
		}
		
		function edit_dataobject( $dbobj ) 
		{
			//subclass this to get the data in the correct form for the formmailer message-template
		}
	}
?>
