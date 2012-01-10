<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * SMS helper class
 * 
 *
 * @package	   SMS
 * @author	   Ushahidi Team
 * @copyright  (c) 2008 Ushahidi Team
 * @license	   http://www.ushahidi.com/license.html
 */

class sms_Core
{
	/**
	 * Send The SMS Message Using Default Provider
	 * @param to mixed	The destination address.
	 * @param from mixed  The source/sender address
	 * @param text mixed  The text content of the message
	 *
	 * @return mixed/bool (returns TRUE if sent FALSE or other text for fail)
	 */
	public static function send($to = NULL, $from = NULL, $message = NULL)	
	{			  
		if ( ! $to OR ! $message){
    	return "Missing Recipients and/or Message";
		}
		
		// 1. Do we have an SMS Provider?
		$provider = Kohana::config("settings.sms_provider");
		if ($provider)
		{
			// 2. Does the plugin exist, and if so, is it active?
			$plugin = ORM::factory("plugin")
				->where("plugin_name", $provider)
				->where("plugin_active", 1)
				->find();
				
			if ($plugin->loaded)
			{ // Plugin exists and is active
				
				// 3. Does this plugin have the SMS Library in place?
				$class = ucfirst($provider).'_SMS';
				
				$path = sms::find_provider($provider);
				
				if ($path)
				{ // File Exists
					$sender = new $class;
					// 4. Does the send method exist in this class?
					if (method_exists($sender, 'send'))
					{
						$response = $sender->send($to, $from, $message);
						
						return $response;
					}
				}
			}
		}
		
		return "No SMS Sending Provider In System";
	}
	
	/**
	 * ADD The SMS Message Using Default Provider
	 * @param from mixed  The source/sender address
	 * @param message mixed  The text content of the message
	 * @param to mixed  Optional... 'which number the message was sent to'
	 */
	public static function add($from = NULL, $message = NULL, $to = NULL)
	{
		$from = preg_replace("#[^0-9]#", "", $from);
		$to = preg_replace("#[^0-9]#", "", $to);
		
		if ( ! $from OR ! $message)
			return "Missing Sender and/or Message";
		
		//Filters to allow modification of the values from the SMS gateway
        Event::run('ushahidi_filter.message_sms_from',$from);
        Event::run('ushahidi_filter.message_sms', $message);
		
		$services = new Service_Model();
		$service = $services->where('service_name', 'SMS')->find();
		if ( ! $service) 
			return false;
	
		$reporter = ORM::factory('reporter')
			->where('service_id', $service->id)
			->where('service_account', $from)
			->find();

		if ( ! $reporter->loaded == TRUE)
		{
			// get default reporter level (Untrusted)
			$level = ORM::factory('level')
				->where('level_weight', 0)
				->find();
			
			$reporter->service_id = $service->id;
			$reporter->level_id = $level->id;
			$reporter->service_userid = null;
			$reporter->service_account = $from;
			$reporter->reporter_first = null;
			$reporter->reporter_last = null;
			$reporter->reporter_email = null;
			$reporter->reporter_phone = null;
			$reporter->reporter_ip = null;
			$reporter->reporter_date = date('Y-m-d');
			$reporter->save();
		}
		
		// Save Message
		$sms = new Message_Model();
		$sms->parent_id = 0;
		$sms->incident_id = 0;
		$sms->user_id = 0;
		$sms->reporter_id = $reporter->id;
		$sms->message_from = $from;
		$sms->message_to = $to;
		$sms->message = $message;
		$sms->message_type = 1; // Inbox
		$sms->message_date = date("Y-m-d H:i:s",time());
		$sms->service_messageid = null;
		$sms->save();
		
		// Notify Admin Of New Email Message
		$send = notifications::notify_admins(
			"[".Kohana::config('settings.site_name')."] ".
				Kohana::lang('notifications.admin_new_sms.subject'),
			Kohana::lang('notifications.admin_new_sms.message')
			);

		// Action::message_sms_add - SMS Received!
		Event::run('ushahidi_action.message_sms_add', $sms);
		
		// Auto-Create A Report if Reporter is Trusted
		$reporter_weight = $reporter->level->level_weight;
		$reporter_location = $reporter->location;
		if ($reporter_weight > 0 AND $reporter_location)
		{
			$incident_title = text::limit_chars($message, 50, "...", false);
			// Create Incident
			$incident = new Incident_Model();
			$incident->location_id = $reporter_location->id;
			$incident->incident_title = $incident_title;
			$incident->incident_description = $message;
			$incident->incident_date = $sms->message_date;
			$incident->incident_dateadd = date("Y-m-d H:i:s",time());
			$incident->incident_active = 1;
			if ($reporter_weight == 2)
			{
				$incident->incident_verified = 1;
			}
			$incident->save();
			
			// Update Message with Incident ID
			$sms->incident_id = $incident->id;
			$sms->save();
			
			// Save Incident Category
			$trusted_categories = ORM::factory("category")
				->where("category_trusted", 1)
				->find();
			if ($trusted_categories->loaded)
			{
				$incident_category = new Incident_Category_Model();
				$incident_category->incident_id = $incident->id;
				$incident_category->category_id = $trusted_categories->id;
				$incident_category->save();
			}
		}
		
		// Add clickable report back feature.
		// Change delimiter to whatever is needed for sending the text aka #
		$delimiter = "/";
	  $token = strtok($message, $delimiter);
		$i = 0;
		while ($token !== false){
		  $str[$i] = $token;
      $token = strtok($delimiter);
		  $i++;
		}              			
		// Redirection for mysql server       
		$php_db = "ranjoat_Ushahidi_Web";
		$myphp_db = 'ranjoat_Ushahidi_Web';       
		                									  
		// Change these variables to the working database  
    $addr = "127.0.0.1";
    $login = "root";
    $passwd = "0258";
		                									  
    if (strstr($str[0], "$delimiter.stop")){
    // connect to database and find/match sms number in list of sms alerts numbers
    
      $db = mysql_connect($addr, $login, $passwd);
      if (!$db)
      { 
        die('Could not connect: ' . mysql_error());
      }
                        
      mysql_select_db($php_db, $db);
      //when matched begin process to remove that number from the table      
      mysql_query("DELETE FROM $myphp_db.`alert` WHERE `alert`.`alert_recipient` = `$from`"); 
      mysql_close($db);
    }
    else if (strstr($str[0], "$delimiter.report")){
      if (($i == 1) && ($str[0] !== false)){// When the user does not how to use the #report function   
      // Add clickable report back feature.
      	if (strstr($str[0], "$delimiter.report"))
  			{
  			  $message = "Format for #report is: #report/# where the # is the incident id or #report/#location/keyword where location is the city and keyword used in the search.";
			    // Edit the parameters in sms::send to work with main deployment
				  //sms::send($to, $from, $message);
				  sms::send($from, $from, $message);
				  
				}
      }
     }// For matching specific cases where the user knows the report ID
      if (($i == 2) && ($str[1] !== false)){
      
       $db = mysql_connect($addr, $login, $passwd);
       if (!$db)
       { 
         die('Could not connect: ' . mysql_error());
       }
                        
       mysql_select_db($php_db, $db);
       $new = $str[1];
       $result = mysql_query("SELECT `incident`.`id`, `incident`.`incident_description` FROM `incident` WHERE `incident`.`id` = $str[1]");
       $message = mysql_fetch_row($result);
       sms::send($from, $from, $message[1]);
                        
       mysql_free_result($result);
       mysql_close($db);
     }		
     								
		return TRUE;
	}
	
	/**
	 * Using this function because someone somewhere will name this file wrong!!!
	 */
	public static function find_provider($provider)
	{
		if ($path = Kohana::find_file('libraries', $provider.'_sms'))
		{ // provider_sms
			return $path;
		}
		elseif ($path = Kohana::find_file('libraries', $provider.'_SMS'))
		{ // provider_SMS
			return $path;
		}
		elseif ($path = Kohana::find_file('libraries', ucfirst($provider).'_sms'))
		{ // Provider_sms
			return $path;
		}
		elseif ($path = Kohana::find_file('libraries', ucfirst($provider).'_SMS'))
		{ // Provider_SMS
			return $path;
		}
		else
		{
			return false;
		}	
	}
}
