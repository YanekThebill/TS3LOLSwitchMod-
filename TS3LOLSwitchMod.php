<?php
/**
 * LOLSwitchMOD v.0.1 beta
 * 
 * @author YanekThebill
 * @Copyright (C) 2014  <yanekthebill@gmail.com>
 * 
 * This product is not endorsed, certified or otherwise approved
 * in any way by Riot Games, Inc. or any of its affiliates.
 * 
 **/
//----------------------------------------------------------------------------------------------------------------
$LoLsgname = ""; // Server Group with users to by check
$lolmainCHid = ""; // Channel id - In this channel Mod will create subchannels
$region = "eune"; // Region of players
$MSkey = ""; // Mashape Api key
$botname = "Tes"; //Bot Name
$TSaddress = "127.0.0.1"; // TS server Adrress
$TSqueryport = "10011"; //Ts server query port
$TSport = "9987"; //TS Port
$TSadminNickname = "serveradmin"; //TS Admin Nickname
$TSpassword = "pass"; // Ts Admin Pass
//-----------------------------------------------------------------------------------------------------------------
// ERROR REPORT
ini_set('display_errors', 'On');
error_reporting(E_ALL);

set_time_limit(0);

require_once dirname(__file__) .
    "/../TS3LOLRANKMOD/ts3php/libraries/TeamSpeak3/TeamSpeak3.php";
require_once dirname(__file__) . "/../TS3LOLRANKMOD/Unilib/Unirest.php";
Unirest::verifyPeer(false);


try
{
    $ts3_VirtualServer = TeamSpeak3::factory("serverquery://$TSadminNickname:$TSpassword@$TSaddress:$TSqueryport/?server_port=$TSport&nickname=$botname");


}
catch (TeamSpeak3_Exception $e)
{
    // print the error message returned by the server
    echo "Error " . $e->getCode() . ": " . $e->getMessage();
}


$arr_LOLClientList = $ts3_VirtualServer->serverGroupGetByName("$LoLsgname");

$TSuserdata = array();


foreach ($arr_LOLClientList as $TSClid => $TSArray)
{
    array_push($TSuserdata, array(

        "TsNICK" => $TSArray->client_nickname->toString(),
        "TsDBID" => $TSArray->client_database_id,
        // "TsUID" => $TSArray->client_unique_identifier->toString(),
        // "TsSERVGIDs" => $TSArray->client_servergroups,
        //"TsCHANGIDs" => $TSArray->client_channel_group_id,
        "TsCLIENTID" => $TSArray->clid,
        "TsCLIENTCHANID" => $TSArray->cid));

}

if (empty($TSuserdata))
{
    echo "No online users with $LoLsgname server group ";
    $ts3_VirtualServer->message("No online users with $LoLsgname server group");
    die;
}
$usersAlreadymoved = "";

foreach ($TSuserdata as $Summoner)
{
    if (!in_array($Summoner['TsNICK'], explode(',', $usersAlreadymoved)))
    {


        $response = Unirest::get("https://community-league-of-legends.p.mashape.com/api/v1.0/$region/summoner/retrieveInProgressSpectatorGameInfo/" .
            $Summoner['TsNICK'], array("X-Mashape-Authorization" => "$MSkey"), null);
        $array = ($response->raw_body);
        $new = json_decode($array, true);


        if (isset($new["success"]) == "false")
        {
            echo "No Game for player " . $Summoner['TsNICK'] .
                " was found in the system <br />";
            $ts3_VirtualServer->message("No Game for player " . $Summoner['TsNICK'] .
                " was found in the system");
            $userCIDNoGame = $ts3_VirtualServer->clientGetByName("" . $Summoner["TsNICK"] .
                "")->cid;
            //           if ($userCIDNoGame !== ){}


        } elseif (empty($new))
        {
            echo "API not responding";
            $ts3_VirtualServer->message("API not responding");
            die;
        } elseif (!empty($new["game"]))
        {
            $ts3_VirtualServer->message("Looks like " . $Summoner['TsNICK'] .
                " are in game!!\n[b]LOADING DATA....[/b]");
            echo "Looks like " . $Summoner['TsNICK'] . " are in game!!<br />";

            $sumgameid = array();
            $teamOne = array();
            $teamTwo = array();

            array_push($sumgameid, array("playerId" => $new["playerCredentials"]["playerId"],
                    "gameId" => $new["playerCredentials"]["gameId"]));


            //TEAM ONE
            foreach ($new["game"]["teamOne"]["array"] as $teamOneArray)
            {

                array_push($teamOne, array(
                    "summonerInternalName" => $teamOneArray["summonerInternalName"],
                    "accountID" => $teamOneArray["accountId"],
                    "summonerID" => $teamOneArray["summonerId"]));
            }
            //Team TWO
            foreach ($new["game"]["teamTwo"]["array"] as $teamTwoArray)
            {

                array_push($teamTwo, array(

                    "summonerInternalName" => $teamTwoArray["summonerInternalName"],
                    "accountID" => $teamTwoArray["accountId"],
                    "summonerID" => $teamTwoArray["summonerId"]));

            }

            foreach ($TSuserdata as $key => $tsu)
            {
                foreach ($teamOne as $tone)
                {
                    if ($tone["summonerInternalName"] == strtolower($tsu["TsNICK"]))
                    {
                        foreach ($tone as $k => $c)
                        {
                            $TSuserdata[$key][$k] = $c;
                        }
                        $find = 1;
                        break;
                    }
                }
            }
            foreach ($TSuserdata as $key => $tsu)
            {
                foreach ($teamTwo as $ttwo)
                {
                    if ($ttwo["summonerInternalName"] == strtolower($tsu["TsNICK"]))
                    {
                        foreach ($ttwo as $k => $c)
                        {
                            $TSuserdata[$key][$k] = $c;
                        }
                        $find = 1;
                        break;
                    }
                }
            }


            foreach ($TSuserdata as $key => $tsu)
            {
                foreach ($sumgameid as $sumid)
                {
                    if (isset($tsu["accountID"]))
                    {
                        if ($sumid["playerId"] == ($tsu["accountID"]))
                        {
                            foreach ($sumid as $k => $c)
                            {
                                $TSuserdata[$key][$k] = $c;
                            }
                            $find = 1;
                            break;
                        }
                    }
                }
            }
        }


        $subchannelnamelist = "";
        $channel = $ts3_VirtualServer->channelGetById("$lolmainCHid")->subChannelList();
        foreach ($channel as $sub)
        {
            $subchannelname = $sub;
            $subchannelnamelist = $subchannelnamelist . $subchannelname . ",";
        }


        foreach ($TSuserdata as $tsUser)
        {
            if (isset($tsUser["gameId"]))
            {
                if (!in_array($tsUser["gameId"], explode(',', $subchannelnamelist)))
                {

                    $cid = $ts3_VirtualServer->channelCreate(array(
                        "channel_name" => "" . $tsUser["gameId"] . "",
                        "channel_topic" => "test",
                        "channel_codec" => TeamSpeak3::CODEC_SPEEX_ULTRAWIDEBAND,
                        "channel_codec_quality" => 0x08,
                        "channel_flag_permanent" => true,
                        "channel_password" => false,
                        "cpid" => $lolmainCHid));

                    $Onecid = $ts3_VirtualServer->channelCreate(array(
                        "channel_name" => "Team One",
                        "channel_topic" => "Team One",
                        "channel_codec" => TeamSpeak3::CODEC_SPEEX_ULTRAWIDEBAND,
                        "channel_codec_quality" => 0x08,
                        "channel_flag_permanent" => true,
                        "channel_password" => false,
                        "cpid" => $cid));

                    $Twocid = $ts3_VirtualServer->channelCreate(array(
                        "channel_name" => "Team Two",
                        "channel_topic" => "Team Two",
                        "channel_codec" => TeamSpeak3::CODEC_SPEEX_ULTRAWIDEBAND,
                        "channel_codec_quality" => 0x08,
                        "channel_flag_permanent" => true,
                        "channel_password" => false,
                        "cpid" => $cid));
                }
                $Onecid = $ts3_VirtualServer->channelGetByName("" . $tsUser["gameId"] . "")->
                    subChannelGetByName("Team One")->cid;
                $Twocid = $ts3_VirtualServer->channelGetByName("" . $tsUser["gameId"] . "")->
                    subChannelGetByName("Team Two")->cid;

            }
        }


        if (isset($teamOne))
        {
            foreach ($teamOne as $tomove)
            {
                foreach ($TSuserdata as $tsmove)
                {
                    if (isset($tsmove["summonerInternalName"]))
                    {

                        $userCID = $ts3_VirtualServer->clientGetByName("" . $tsmove["TsNICK"] . "")->
                            cid;


                        if ($tsmove["summonerInternalName"] == $tomove["summonerInternalName"])
                        {
                            if ($Onecid !== $userCID)
                            {
                                $ts3_VirtualServer->clientMove($tsmove["TsCLIENTID"], $Onecid);
                                $ts3_VirtualServer->clientGetByDbid($tsmove["TsDBID"])->message("[b]You are moved to your team channel[/b]");
                                $movedUser = $tsmove["TsNICK"];
                                $usersAlreadymoved = $usersAlreadymoved . $movedUser . ",";
                            } else
                            {
                                echo " " . $tsmove["TsNICK"] . " Already on channel<br />";
                                //$ts3_VirtualServer->message(" " . $tsmove["TsNICK"] . " Already on channel ");
                            }
                        }
                    }
                }
            }
        }
        if (isset($teamTwo))
        {
            foreach ($teamTwo as $tomove)
            {
                foreach ($TSuserdata as $tsmove)
                {
                    if (isset($tsmove["summonerInternalName"]))
                    {
                        $userCID = $ts3_VirtualServer->clientGetByName("" . $tsmove["TsNICK"] . "")->
                            cid;

                        if ($tsmove["summonerInternalName"] == $tomove["summonerInternalName"])
                        {
                            if ($Twocid !== $userCID)
                            {
                                $ts3_VirtualServer->clientMove($tsmove["TsCLIENTID"], $Twocid);
                                $ts3_VirtualServer->clientGetByDbid($tsmove["TsDBID"])->message("[b]You are moved to your team channel[/b]");
                                $movedUser = $tsmove["TsNICK"];
                                $usersAlreadymoved = $usersAlreadymoved . $movedUser . ",";
                            } else
                            {
                                echo " " . $tsmove["TsNICK"] . " Already on channel<br />";
                                //$ts3_VirtualServer->message(" " . $tsmove["TsNICK"] . " Already on channel ");
                            }
                        }
                    }
                }
            }
        }
    }
}
