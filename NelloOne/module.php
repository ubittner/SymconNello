<?php

/*
 * @module      Nello One
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2019
 * @license     CC BY-NC-SA 4.0
 *
 * @version     2.01
 * @build       2001
 * @date:       2019-04-19, 10:00
 *
 * @see         https://github.com/ubittner/SymconNello
 *
 * @guids       Library
 *              {22BC7A02-3BFB-4DDD-9387-692E5771491D}
 *
 *              Module
 *              {0029BC2B-B0D2-4BC4-9D7E-08A9D8061F10}
 *
 * @changelog   2019-04-19, 10:00, initial module script of version 2.01-2001
 *
 */

declare(strict_types=1);

// Definitions
if (!defined('WEBFRONT_GUID')) {
    define('WEBFRONT_GUID', '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}');
}

class NelloOne extends IPSModule
{
    //This one needs to be available on our OAuth client backend.
    private $oauthIdentifer = 'nello';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Register properties
        $this->RegisterPropertyString('Token', '');
        $this->RegisterPropertyString('LocationList', '');
        $this->RegisterPropertyString('WebhookUsername', 'symcon');
        $this->RegisterPropertyString('WebhookPassword', 'nello');
        $this->RegisterPropertyBoolean('UseNotification', false);
        $this->RegisterPropertyInteger('WebFrontID', 0);

        // Register variables
        $this->RegisterVariableString('LocationDescription', $this->Translate('Location'), '', 1);
        IPS_SetIcon($this->GetIDForIdent('LocationDescription'), 'IPS');
        $this->RegisterVariableBoolean('Door', $this->Translate('Door'), '~Lock.Reversed', 2);
        $this->EnableAction('Door');
        $this->SetValue('Door', false);
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        // Check kernel runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Register authentication
        $this->RegisterOAuth($this->oauthIdentifer);

        // Register messages
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        // Validate configuration
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->ValidateConfiguration();
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'SenderID: ' . $SenderID . ', Message: ' . $Message, 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }

    /**
     * Applies changes when the kernel is ready.
     */
    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    public function Destroy()
    {
        // Delete webhook
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/nello');
        }
        // Never delete this line!
        parent::Destroy();
    }

    public function GetConfigurationForm()
    {
        $formdata = json_decode(file_get_contents(__DIR__ . '/form.json'));
        $locationList = json_decode($this->ReadPropertyString('LocationList'));
        if (!empty($locationList)) {
            $status = true;
            foreach ($locationList as $currentKey => $currentArray) {
                $rowColor = '#DFDFDF';
                $utilisation = true;
                foreach ($locationList as $searchKey => $searchArray) {
                    // Search for duplicate positions
                    if ($searchArray->Position == $currentArray->Position) {
                        if ($searchKey != $currentKey) {
                            $rowColor = '#FFC0C0';
                            $status = false;
                        }
                    }
                    // Search for duplicate location ids
                    if ($searchArray->LocationID == $currentArray->LocationID) {
                        if ($searchKey != $currentKey) {
                            $rowColor = '#FFC0C0';
                            $status = false;
                        }
                    }
                    // Search for duplication use utilisation
                    if ($searchArray->UseUtilisation == $currentArray->UseUtilisation) {
                        if ($searchKey != $currentKey) {
                            $rowColor = '#FFC0C0';
                            $status = false;
                            $utilisation = false;
                        }
                    }
                }
                if ($utilisation == true && $currentArray->UseUtilisation == true) {
                    $rowColor = '#C0FFC0';
                }
                // Check entries
                if (($currentArray->Position == '') || ($currentArray->LocationID == '')) {
                    $rowColor = '#FFC0C0';
                    $status = false;
                }
                $formdata->elements[11]->items[2]->values[] = ['rowColor' => $rowColor];
                // Set Status
                if ($status == true) {
                    $this->SetStatus(102);
                } else {
                    $this->SetStatus(2211);
                }
            }
        }
        return json_encode($formdata);
    }

    //################### WebOAuth

    /**
     * Registers this instance to WebOAuth instance.
     *
     * @param $WebOAuth
     */
    private function RegisterOAuth($WebOAuth)
    {
        $ids = IPS_GetInstanceListByModuleID('{F99BF07D-CECA-438B-A497-E4B55F139D37}');
        if (count($ids) > 0) {
            $clientIDs = json_decode(IPS_GetProperty($ids[0], 'ClientIDs'), true);
            $found = false;
            foreach ($clientIDs as $index => $clientID) {
                if ($clientID['ClientID'] == $WebOAuth) {
                    if ($clientID['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $clientIDs[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $clientIDs[] = ['ClientID' => $WebOAuth, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'ClientIDs', json_encode($clientIDs));
            IPS_ApplyChanges($ids[0]);
        }
    }

    /**
     * Called by the register button on the property page.
     */
    public function Register()
    {
        //Return everything which will open the browser
        return 'https://oauth.ipmagic.de/authorize/' . $this->oauthIdentifer . '?username=' . urlencode(IPS_GetLicensee());
    }

    /**
     * Exchanges our authentication code for a permanent bearer token.
     *
     * @param $code
     *
     * @return mixed
     */
    private function FetchBearerToken($code)
    {
        $options = [
        'http' => [
        'header'  => 'Content-Type: application/x-www-form-urlencoded' . "\r\n",
        'method'  => 'POST',
        'content' => http_build_query(['code' => $code])
        ]
        ];
        $context = stream_context_create($options);
        $result = file_get_contents('https://oauth.ipmagic.de/access_token/' . $this->oauthIdentifer, false, $context);
        $data = json_decode($result);
        if (!isset($data->token_type) || $data->token_type != 'Bearer') {
            die('Bearer Token expected');
        }
        return $data->access_token;
    }

    /**
     * Called by the OAuth control.
     */
    protected function ProcessOAuthData()
    {
        if (!isset($_GET['code'])) {
            die('Authorization Code expected');
        }
        $token = $this->FetchBearerToken($_GET['code']);
        IPS_SetProperty($this->InstanceID, 'Token', $token);
        IPS_ApplyChanges($this->InstanceID);
    }

    private function FetchData($url)
    {
        if ($this->ReadPropertyString('Token') == '') {
            die('No token found. Please register for a token first.');
        }
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $this->ReadPropertyString('Token') . "\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }

    /**
     * Requests the status.
     */
    public function RequestStatus()
    {
        echo $this->FetchData('https://oauth.ipmagic.de/forward');
    }

    //################### Webhook

    /**
     * Adds/updates the webhook for the assigned location.
     */
    public function AddUpdateWebhook()
    {
        // Get token
        $token = $this->GetBearerToken();
        // Check authetification data
        if ($this->ReadPropertyString('WebhookUsername') == '') {
            die($this->Translate('No webhook username found. Please enter a username.'));
        }
        if ($this->ReadPropertyString('WebhookPassword') == '') {
            die($this->Translate('No webhook password found. Please enter a password.'));
        }
        // Get ipmagic address and add webhook credentials
        $ids = IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}');
        if (count($ids) > 0) {
            if (IPS_GetInstance($ids[0])['InstanceStatus'] == 102) {
                $url = CC_GetURL($ids[0]) . '/hook/nello';
                //IPS_LogMessage("URL", $url);
                $credentials = urlencode($this->ReadPropertyString('WebhookUsername')) . ':' . urlencode($this->ReadPropertyString('WebhookPassword')) . '@';
                $webhook = substr($url, 0, 8) . $credentials . substr($url, 8);
                //IPS_LogMessage('Nello One Webhook', $webhook);
                $locationID = $this->GetAssignedLocation();
                //IPS_LogMessage("2", $locationID);
                if (!is_null($locationID)) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://public-api.nello.io/v1/locations/' . $locationID . '/webhook/');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"url": "' . $webhook . '","actions": ["swipe","geo","tw","deny"]}');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $token
                    ]);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    IPS_LogMessage('Nello One Webhook', $response);
                    $result = json_decode($response)->result->success;
                    if ($result == true) {
                        echo $this->Translate('Webhook was added/updated successfully.');
                        // Register hook
                        $this->RegisterHook('/hook/nello');
                    } else {
                        echo $this->Translate('An error occurred while adding/updating the webhook.');
                    }
                } else {
                    echo $this->Translate('No location has been assigned.');
                }
            }
        }
    }

    /**
     * Registers the webhook to the WebHook instance.
     *
     * @param $WebHook
     */
    private function RegisterHook($WebHook)
    {
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $WebHook) {
                    if ($hook['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $hooks[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

    /**
     * Deletes the webhook for the assigned location.
     */
    public function DeleteWebhook()
    {
        // Check token first
        $token = $this->GetBearerToken();
        // Get assigned location id
        $locationID = $this->GetAssignedLocation();
        if (!is_null($locationID)) {
            // Send data to endpoint
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://public-api.nello.io/v1/locations/' . $locationID . '/webhook/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            IPS_LogMessage('Nello One Webhook', $response);
            // Check result
            $result = json_decode($response)->result->success;
            if ($result == true) {
                echo $this->Translate('Webhook was deleted successfully.');
                // Unregister hook
                $this->UnregisterHook('/hook/nello');
            } else {
                echo $this->Translate('An error occurred while deleting the webhook.');
            }
        } else {
            echo $this->Translate('No location has been assigned.');
        }
    }

    /**
     * Unregisters the webhook from the WebHook instance.
     *
     * @param $WebHook
     */
    private function UnregisterHook($WebHook)
    {
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            $index = null;
            foreach ($hooks as $key => $hook) {
                if ($hook['Hook'] == $WebHook) {
                    $found = true;
                    $index = $key;
                    break;
                }
            }
            if ($found === true && !is_null($index)) {
                array_splice($hooks, $index, 1);
                IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
                IPS_ApplyChanges($ids[0]);
            }
        }
    }

    /**
     * Processes the incomming data from WebHook control.
     */
    protected function ProcessHookData()
    {
        $state = true;
        // Get incomming data from server
        $this->SendDebug('Webhook Data', print_r($_SERVER, true), 0);
        // Get webhook content
        $data = file_get_contents('php://input');
        $this->SendDebug('Webhook Data', $data, 0);
        // Check credentials
        $username = urldecode($_SERVER['PHP_AUTH_USER']);
        $password = urldecode($_SERVER['PHP_AUTH_PW']);
        $this->SendDebug('Webhook Credentials', 'Username: ' . $username . ' Password: ' . $password, 0);
        $webhookUsername = $this->ReadPropertyString('WebhookUsername');
        $webhookPassword = $this->ReadPropertyString('WebhookPassword');
        if (($username != $webhookUsername) || ($password != $webhookPassword)) {
            header('HTTP/1.0 401 Unauthorized');
            $this->SendDebug('Webhook Access', 'Access denied', 0);
            if ($username != $webhookUsername) {
                $this->SendDebug('Webhook Username', 'Webhook username: ' . $username . ' does not match with ' . $webhookUsername, 0);
            }
            if ($password != $webhookPassword) {
                $this->SendDebug('Webhook Password', 'Webhook password: ' . $password . ' does not match with ' . $webhookPassword, 0);
            }
            echo 'Authorization required';
            $state = false;
        }
        // Analyse message type
        if ($state == true) {
            $message = json_decode($data);
            $action = $message->action;
            $locationName = $this->GetLocationName($message->data->location_id);
            switch ($action) {
                case 'swipe':
                    $user = $message->data->name;
                    $this->SendNotification(utf8_decode($user . ' ' . $this->Translate('has opened the door. Location: ') . $locationName . '.'));
                    break;
                case 'geo':
                    $user = $message->data->name;
                    $this->SendNotification(utf8_decode($user . ' ' . $this->Translate('has opened the door via Homezone Unlock automatically. Location: ') . $locationName . '.'));
                    break;
                case 'tw':
                    $user = $message->data->name;
                    $this->SendNotification(utf8_decode($user . ' ' . $this->Translate('has opened the door via Time Window automatically. Location: ') . $locationName . '.'));
                    break;
                case 'deny':
                    $this->SendNotification(utf8_decode($this->Translate('Someone rang the doorbell, but nello did not open. Location: ') . $locationName . '.'));
                    break;
            }
        }
        return $state;
    }

    //################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Door':
                $this->BuzzDoor();
                break;
        }
    }

    //################### Public functions

    /**
     * Lists the available locations.
     *
     * @return mixed|null
     */
    public function GetLocations()
    {
        $locations = null;
        // Check token first
        $token = $this->GetBearerToken();
        // Send data to endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://public-api.nello.io/v1/locations/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        // Check result
        $result = json_decode($response)->result->success;
        if ($result == true) {
            $locations = json_decode($response)->data;
        } else {
            echo $this->Translate('An error occurred while getting the locations.');
        }
        return $locations;
    }

    /**
     * Imports the locations into the configuration form.
     */
    public function ImportLocations()
    {
        $state = false;
        $locations = $this->GetLocations();
        if (!is_null($locations)) {
            $locationList = [];
            $i = 0;
            foreach ($locations as $location) {
                $position = ($i) + 1;
                $locationList[$i]['Position'] = (string) $position;
                $useUtilisation = false;
                if ($i == 0) {
                    $useUtilisation = true;
                }
                $locationList[$i]['UseUtilisation'] = (bool) $useUtilisation;
                $locationList[$i]['LocationID'] = (string) $location->location_id;
                $locationList[$i]['Street'] = (string) $location->address->street;
                $locationList[$i]['City'] = (string) $location->address->zip . ' ' . $location->address->city;
                $locationList[$i]['Country'] = (string) $location->address->country;
                $i++;
            }
            IPS_SetProperty($this->InstanceID, 'LocationList', json_encode($locationList));
            $state = true;
            if (IPS_HasChanges($this->InstanceID)) {
                IPS_ApplyChanges($this->InstanceID);
            }
        }
        if ($state == true) {
            echo $this->Translate('The locations were imported successfully!');
        }
    }

    /**
     * Opens a location.
     *
     * @return bool
     */
    public function BuzzDoor()
    {
        $this->SetValue('Door', true);
        $state = false;
        $locations = json_decode($this->ReadPropertyString('LocationList'));
        if (!empty($locations)) {
            foreach ($locations as $location) {
                if ($location->UseUtilisation == true) {
                    $locationID = $location->LocationID;
                    if (!empty($locationID)) {
                        $token = $this->GetBearerToken();
                        // Send data to endpoint
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, 'https://public-api.nello.io/v1/locations/' . $locationID . '/open/');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HEADER, false);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $token
                        ]);
                        $response = curl_exec($ch);
                        curl_close($ch);
                        // Check result
                        $result = json_decode($response)->result->success;
                        if ($result == true) {
                            $state = true;
                        } else {
                            $locationName = $this->GetLocationName($locationID);
                            echo $this->Translate('An error occurred while opening the location.') . ' Standort: ' . $locationName . ' .';
                        }
                    }
                }
            }
        }
        $this->SetValue('Door', false);
        return $state;
    }

    //################### Private functions

    /**
     * Validates the configuration.
     */
    private function ValidateConfiguration()
    {
        // Check WebFront
        $useNotification = $this->ReadPropertyBoolean('UseNotification');
        if ($useNotification == true) {
            $webFrontID = $this->ReadPropertyInteger('WebFrontID');
            if ($webFrontID != 0 && IPS_GetInstance($webFrontID)['ModuleInfo']['ModuleID'] == WEBFRONT_GUID) {
                $this->SetStatus(2411);
            }
        }
        // Check bearer token
        $token = $this->ReadPropertyString('Token');
        if ($token == '') {
            $this->SetStatus(2111);
        } else {
            $locationList = json_decode($this->ReadPropertyString('LocationList'));
            if (empty($locationList)) {
                $this->SetStatus(2201);
            }
        }
        // Check location name
        $name = '';
        $locationID = $this->GetAssignedLocation();
        if (!is_null($locationID)) {
            $locationName = $this->GetLocationName($locationID);
            if (!is_null($locationName)) {
                $name = $locationName;
            }
        }
        $this->SetValue('LocationDescription', $name);
    }

    /**
     * Gets the bearer token from configuration.
     *
     * @return null|string
     */
    private function GetBearerToken()
    {
        $token = null;
        if ($this->ReadPropertyString('Token') == '') {
            die($this->Translate('No token found. Please register for a token first.'));
        } else {
            $token = $this->ReadPropertyString('Token');
        }
        return $token;
    }

    /**
     * Gets the assigned location id from configuration.
     *
     * @return null
     */
    private function GetAssignedLocation()
    {
        $locationID = null;
        $locations = json_decode($this->ReadPropertyString('LocationList'));
        if (!empty($locations)) {
            foreach ($locations as $location) {
                if ($location->UseUtilisation == true) {
                    if (!empty($location->LocationID)) {
                        $locationID = $location->LocationID;
                    }
                }
            }
        }
        return $locationID;
    }

    /**
     * Gets the location name from location id.
     *
     * @param $LocationID
     *
     * @return null
     */
    private function GetLocationName($LocationID)
    {
        $locationName = null;
        $locations = json_decode($this->ReadPropertyString('LocationList'));
        if (!empty($locations)) {
            foreach ($locations as $location) {
                if ($location->LocationID == $LocationID) {
                    if (!empty($location->Street)) {
                        $locationName = $location->Street;
                    }
                }
            }
        }
        return $locationName;
    }

    /**
     * Sends a notification to the WebFront instance.
     *
     * @param string $Message
     */
    private function SendNotification(string $Message)
    {
        $notification = $this->ReadPropertyBoolean('UseNotification');
        if ($notification == true) {
            $webFrontID = $this->ReadPropertyInteger('WebFrontID');
            if ($webFrontID != 0 && IPS_GetInstance($webFrontID)['ModuleInfo']['ModuleID'] == WEBFRONT_GUID) {
                $send = WFC_PushNotification($webFrontID, $this->Translate('Nello Notification'), $Message, '', 0);
                if ($send == false) {
                    IPS_LogMessage('Nello One', $this->Translate('Notification could not be sent.'));
                }
            }
        }
    }
}
