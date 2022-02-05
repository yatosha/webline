<?php

use Blesta\Core\Util\Input\Fields\InputFields;


/**
 * Webline Messenger
 *
 * @package blesta
 * @subpackage blesta.components.messengers.webline
 * @copyright Copyright (c) 2020, Webline SMS, Webline Africa Limited
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Webline extends Messenger
{
    /**
     * Initializes the messenger.
     */
    public function __construct()
    {
        // Load configuration required by this messenger
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load the helpers required by this messenger
        Loader::loadHelpers($this, ['Html']);

        // Load the language required by this messenger
        Language::loadLang('webline', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Returns all fields used when setting up a messenger, including any
     * javascript to execute when the page is rendered with these fields.
     *
     * @param array $vars An array of post data submitted to the manage messenger page
     * @return InputFields An InputFields object, containing the fields to render
     *  as well as any additional HTML markup to include
     */
    public function getConfigurationFields(&$vars = [])
    {
        $fields = new InputFields();


        // Sender ID
        $sid = $fields->label(Language::_('Webline.configuration_fields.sid', true), 'webline_sid');
        $fields->setField(
            $sid->attach(
                $fields->fieldText('sid', $this->Html->ifSet($vars['sid']), ['id' => 'webline_sid'])
            )
        );

        // API Key
        $token = $fields->label(Language::_('Webline.configuration_fields.apikey', true), 'api_key');
        $fields->setField(
            $token->attach(
                $fields->fieldText('apikey', $this->Html->ifSet($vars['apikey']), ['id' => 'api_key'])
            )
        );

       
        return $fields;
    }

    /**
     * Updates the meta data for this messenger
     *
     * @param array $vars An array of messenger info to add
     * @return array A numerically indexed array of meta fields containing:
     *
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function setMeta(array $vars)
    {
        $meta_fields = ['sid', 'apikey'];
        //$encrypted_fields = ['password'];

        $meta = [];
        foreach ($vars as $key => $value) {
            if (in_array($key, $meta_fields)) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Send a message.
     *
     * @param mixed $to_user_id The user ID this message is to
     * @param string $content The content of the message to send
     * @param string $type The type of the message to send (optional)
     */
    public function send($to_user_id, $content, $type = null)
    {
        // Initialize the API

        $meta = $this->getMessengerMeta();
        
        Loader::loadModels($this, ['Staff', 'Clients', 'Contacts']);

        // Fetch user information

        $is_client = true;
        if (($user = $this->Staff->getByUserId($to_user_id))) {
            $is_client = false;
        } else {
            $user = $this->Clients->getByUserId($to_user_id);

            $phone_numbers = $this->Contacts->getNumbers($user->contact_id);
            if (is_array($phone_numbers) && !empty($phone_numbers)) {
                $user->phone_number = reset($phone_numbers);
            }
        }

        // Send message

        $error = null;
        $success = false;

        if ($type == 'sms') {

            if($is_client){
                $to = $this->Html->ifSet($user->phone_number->number);
            }else{
                $to = $this->Html->ifSet($user->number_mobile);
            }

            
            $sid =  $meta->sid;
            $apikey = $meta->apikey;
            $message = $content;
            $phone = $to;
            $recipient = str_replace(' ', '', $phone);
           
            // Start with country code 255
            
            if(substr($recipient,0,1) == "0")
			    $recipient = "255".substr($recipient,1);

            $curl = curl_init();
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://bulksms.webline.co.tz/api/v3/sms/send?recipient='. $recipient .'&sender_id='. $sid .'&message='.urlencode($message).'', 
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer $apikey"
                ),
            ));


            $response = curl_exec($curl);
            if(curl_errno($curl)){
                $success = false;
                $error = 'Request Error:' . curl_error($curl);
                $this->log($to_user_id, json_encode($error, JSON_PRETTY_PRINT), 'output', $success);
            }else{
                $success = true;
                $this->log($to_user_id, json_encode($response, JSON_PRETTY_PRINT), 'output', $success);
            }

        }
    }

   
}