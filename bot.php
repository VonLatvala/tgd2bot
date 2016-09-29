<?php
    include 'env.php';
    require 'encoding.class.php';
    
    if(!defined('__TGWEBHOOKCALL__'))
    {
        header('HTTP/1.1 401 Unauthorized');
        die();
    }
    
    function sendMessage($chatId, $message, $logfh = null)
    {
        if(!($logfh === null))
            fwrite($logfh, 'Attempting to message '.$chatId.' with '.$message.PHP_EOL);
        $url = TG_BOTAPI_URL.'/sendMessage?chat_id='.$chatId.'&text='.urlencode(Encoding::fixUTF8(utf8_encode($message)));
        $res = file_get_contents($url);
        if(!($logfh === null))
            fwrite($logfh, 'Result was '.$res.PHP_EOL);
    }

    function parseChatFile($filePath)
    {
        if(!file_exists($filePath))
            return [];
        else
            return json_decode(file_get_contents($filePath));
    }

    function saveChatFile($dataArr, $filePath)
    {
        $fh = fopen($filePath, 'w');
        if(!$fh)
            return false;
        if(!fwrite($fh, json_encode($dataArr)))
            return false;
        fclose($fh);
        return true;
    }

    function registerParticipation($chatID, $username)
    {
        $chatFile = './chats/'.str_replace('/', "", $chatID).'.json';
        $chatData = parseChatFile($chatFile);
        $numParticipants = count($chatData);
        if($numParticipants > 4)
            return 'Sorry, full party already';
        if(substr($username, 0, 1) === '@')
            $username = substr($username, 1);
        if(empty($username))
            return 'Having no username is shameful. I do not permit you to sign up without one.';
        if(in_array($username, $chatData))
            return 'Already registered';
        else
        {
            $chatData[] = $username;
            if(saveChatFile($chatData, $chatFile))
                return ($numParticipants+1).'/5 (@'.$username.')';
            else
                return 'Error registering user '.$username;
        }
    }

    function status($chatID)
    {
        $chatFile = './chats/'.str_replace('/', "", $chatID).'.json';
        $chatData = parseChatFile($chatFile);
        $numParticipants = count($chatData);
        if($numParticipants < 1)
            return 'No party is currently on';
        $res = 'Current party size is '.$numParticipants.'/5:'.PHP_EOL;
        foreach($chatData as $participant)
            $res .= '@'.$participant.PHP_EOL;
        return $res;

    }

    function resign($chatID, $username)
    {
        $chatFile = './chats/'.str_replace('/', "", $chatID).'.json';
        $chatData = parseChatFile($chatFile);
        $newChatData = [];
        foreach($chatData as $participant)
            if($participant !== $username)
                $newChatData[] = $participant;
        $res = saveChatFile($newChatData, $chatFile);
        if($res)
            return 'If you were in the party, you have now been removed';
        else
            return 'Error resigning';
    }

    function resetParty($chatID)
    {
        $chatFile = './chats/'.str_replace('/', "", $chatID).'.json';
        $res = unlink($chatFile);
        if($res)
            return 'Party discarded.';
        else
            return 'Problem discarding party.';
    }

    function addParticipant($chatID, $username)
    {
        if(empty($username))
            return 'No username supplied.';
        return registerParticipation($chatID, $username);
    }

    function terminateParticipant($chatID, $username)
    {
        if(empty($username))
            return 'No username supplied.';
        $chatFile = './chats/'.str_replace('/', "", $chatID).'.json';
        $chatData = parseChatFile($chatFile);
        if(!in_array($username, $chatData))
            return $username.' is not participating.';
        $res = resign($chatID, $username);
        if($res == 'Error resigning')
            return 'Error terminating @'.$username;
        return '@'.$username.' terminated.';
    }


    $fh = fopen('webhookpost.log', 'a');
    if(!$fh)
        trigger_error('Unable to open webhookpost.log for appending', E_USER_ERROR);
    fwrite($fh, date('[d.m.Y H:i:s] ').'Webhook triggered.'.PHP_EOL);
    $reqJson = file_get_contents('php://input');
    #fwrite($fh, $reqJson.PHP_EOL);
    $reqData = json_decode($reqJson, true);
    fwrite($fh, print_r($reqData, true));
    #fwrite($fh, PHP_EOL);
    
    $msgRcvd = $reqData['message']['text'];
    $chatID  = $reqData['message']['chat']['id'];
    $botName = 'axynd2bot';
    $lowerMsgRcvd = strtolower($msgRcvd);

    $lowerSplitted = explode(' ', $msgRcvd);
    
    if($lowerMsgRcvd == '/d2' || $lowerMsgRcvd == '/d2@'.$botName)
        sendMessage($chatID, registerParticipation($reqData['message']['chat']['id'], $reqData['message']['from']['username']));
    else if($lowerMsgRcvd == '/status' || $lowerMsgRcvd == '/status@'.$botName)
        sendMessage($chatID, status($chatID));
    else if($lowerMsgRcvd == '/reset' || $lowerMsgRcvd == '/reset@'.$botName)
        sendMessage($chatID, resetParty($reqData['message']['chat']['id']));
    else if($lowerMsgRcvd == '/resign' || $lowerMsgRcvd == '/resign@'.$botName)
    {
        sendMessage($chatID, resign($chatID, $reqData['message']['from']['username']));
        sendMessage($chatID, status($chatID));
    }
    else if($lowerSplitted[0] == '/add' || $lowerSplitted[0] == '/add@'.$botName)
        sendMessage($chatID, addParticipant($chatID, $lowerSplitted[1]));
    else if($lowerSplitted[0] == '/terminate' || $lowerSplitted[0] == '/terminate@'.$botName)
        sendMessage($chatID, terminateParticipant($chatID, $lowerSplitted[1]));


    /*else 
        fwrite($fh, "Ei tajuu: ".$msgRcvd.PHP_EOL);*/
    
    fclose($fh);
