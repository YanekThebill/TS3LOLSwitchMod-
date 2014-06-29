<?php
/**
 * LOLSwitchMOD v.0.5 beta
 * 
 * @author YanekThebill
 * @Copyright (C) 2014  <yanekthebill@gmail.com>
 * 
 * This product is not endorsed, certified or otherwise approved
 * in any way by Riot Games, Inc. or any of its affiliates.
 * 
 **/

//-------------------------------------------------------------------------------------------------------------
// TS  CONFIGURATION
//-------------------------------------------------------------------------------------------------------------

$botname = "LOLSwitch"; //Bot Nickname;
$TSaddress = "127.0.0.1"; //TS server address
$TSqueryport = "10011"; //TS Query port (deflaut is: 10011)
$TSport = "9987"; //TS Server Port (deflaut is 9987)
$TSadminNickname = "serveradmin"; //TS  Admin Query nickname
$TSpassword = "pass"; // TS Admin Query password
$LoLsgID = "";        //  Server Group ID - Only users with this sg will be checked
$AutomoveCHID = ""; // Chanel id - After game users will be move to this channel.
$lolmainCHid = ""; // Channel id - In this channel Mod will create subchannels.
//--------------------------------------------------------------------------------------------
// **** LOL CONFIG ***
//---------------------------------------------------------------------------------------------
//  ** Only users on contact list will be checked **
$sumName = "Login"; // LOL Login
$sumPassword = "Pass"; //LOL Password
$region = "eune"; // Region 
//---------------------------------------------------------------------------------------------
$Riotkey = ""; //Riot Api key
$MSkey = ""; // Mashape Api key
//---------------------------------------------------------------------------------------------

require_once dirname(__file__) .
    "/../LOLSwitchMOD/ts3php/libraries/TeamSpeak3/TeamSpeak3.php";
require_once dirname(__file__) . "/../LOLSwitchMOD/XMPPHP/XMPP.php";
require_once dirname(__file__) . "/../LOLSwitchMOD/Unilib/Unirest.php";
Unirest::verifyPeer(false);
set_time_limit(0);
$filename = "SummonerStatus.txt";


try
{
    $ts3_VirtualServer = TeamSpeak3::factory("serverquery://$TSadminNickname:$TSpassword@$TSaddress:$TSqueryport/?server_port=$TSport&nickname=$botname");


}

catch (TeamSpeak3_Exception $e)
{
    // print the error message returned by the server
    echo "Error " . $e->getCode() . ": " . $e->getMessage();
}

$subchannelsDel = $ts3_VirtualServer->channelGetById("$lolmainCHid")->
    subChannelList();

foreach ($subchannelsDel as $sub)
{
    if (isset($sub["channel_name"]))
    {
        $a = $sub["channel_name"];
        $b = $ts3_VirtualServer->channelGetByName("$a")->subChannelGetByName("Team One");
        $c = $ts3_VirtualServer->channelGetByName("$a")->subChannelGetByName("Team Two");
        if (($b["total_clients"] == "0") && ($c["total_clients"] == "0"))
        {
            echo "Channel for game " . $sub["channel_name"] .
                " is empty<br />Delleting...<br />";
            $ts3_VirtualServer->channelDelete($sub["cid"], $force = false);
        } else
        {
            echo "Users still on channel<br />";
        }
    } else
    {
        echo "Channel check<br />No subchannels to delete<br />";
    }
}

$arr_LOLClientList = $ts3_VirtualServer->serverGroupGetById("$LoLsgID");
$TSuserdata = array();

foreach ($arr_LOLClientList as $TSClid => $TSArray)
{
    array_push($TSuserdata, array(

        "TsNICK" => $TSArray->client_nickname->toString(),
        "TsDBID" => $TSArray->client_database_id,
        "TsUID" => $TSArray->client_unique_identifier->toString(),
        "TsSERVGIDs" => $TSArray->client_servergroups,
        "TsCHANGIDs" => $TSArray->client_channel_group_id,
        "TsCHANID" => $TSArray->cid,
        "TsCLIENTID" => $TSArray->clid));
}

if (empty($TSuserdata))
{
    echo "No online users with LoLAutoMove server group ";
    die;
}

$conn = new XMPPHP_XMPP('chat.eun1.lol.riotgames.com', 5223, $sumName, "AIR_$sumPassword",
    'xiff', 'pvp.net', $printlog = true, $loglevel = XMPPHP_Log::LEVEL_INFO);
$conn->autoSubscribe(true);
$conn->connect();
$conn->processUntil('session_start');
$conn->presence($status = 'Controller available.');
$conn->processTime(3);

// now see the results
$roster = $conn->roster->getRoster();
$gameStatus = array();

foreach ($roster as $suminfo)
{
    $a = ($suminfo["contact"]["jid"]);
    $b = str_replace("@pvp.net", "", $a);
    $c = str_replace("sum", "", $b);
    $d = ($suminfo['presence']['xiff']['status']);
    $e = getSummststus($d);
    $gameStatus[] = array("summonerID" => $c, "summonerSTATUS" => $e);

}


$EUNEidList = "";
foreach ($gameStatus as $sum1id)
{
    $sumid1 = $sum1id["summonerID"];
    $EUNEidList = $EUNEidList . $sumid1 . ",";
}

$EUNEidListArray = explode(",", $EUNEidList);
$EUNEid_chunks = array_chunk($EUNEidListArray, 40);


if (!empty($EUNEid_chunks["0"]["0"]))
{
    foreach ($EUNEid_chunks as $chunk)
    {
        $idlist = implode(',', $chunk);

        $url4 = "https://eune.api.pvp.net/api/lol/eune/v1.4/summoner/" . $idlist .
            "?api_key=" . $Riotkey . "";
        $JSON4 = file_get_contents($url4);
        $Dataid1 = json_decode($JSON4, true);

    }
}

$EUNEname = array();
if (!empty($Dataid1))
{
    foreach ($Dataid1 as $sumArr1)
    {
        $EUNEname[] = array("summonerID" => $sumArr1["id"], "name" => $sumArr1["name"]);
    }
}

foreach ($gameStatus as $key => $s1)
{
    foreach ($EUNEname as $tr)
    {
        if ($s1["summonerID"] == $tr["summonerID"])
        {
            foreach ($tr as $k => $c)
            {
                $gameStatus[$key][$k] = $c;
            }
            $find = 1;
            break;
        }
    }
}
foreach ($TSuserdata as $key => $s1)
{
    foreach ($gameStatus as $tr)
    {
        if ($s1["TsNICK"] == $tr["name"])
        {
            foreach ($tr as $k => $c)
            {
                $TSuserdata[$key][$k] = $c;
            }
            $find = 1;
            break;
        }
    }
}

$usersAlreadymoved = "";

foreach ($TSuserdata as $Summoner)
{
    if (array_key_exists('summonerSTATUS', $Summoner))
    {

        if (($Summoner["summonerSTATUS"] === "TEAM_SELECT") || ($Summoner["summonerSTATUS"]
            === "CHAMPION_SELECT") || ($Summoner["summonerSTATUS"] === "OUT_OF_GAME") || ($Summoner["summonerSTATUS"]
            === "IN_QUEUE") || ($Summoner["summonerSTATUS"] === "SPECTATING") || ($Summoner["summonerSTATUS"]
            === "UNKNOWN") || ($Summoner["summonerSTATUS"] === "HOSTING_RANKED_GAME") || ($Summoner["summonerSTATUS"]
            === "HOSTING_PRACTICE_GAME") || ($Summoner["summonerSTATUS"] ===
            "HOSTING_NORMAL_GAME"))
        {
            $searchfor = $Summoner["TsNICK"];
            $file = file_get_contents($filename);
            if (strpos($file, $searchfor) !== false)
            {
                $file = trim(str_replace("$searchfor", '', $file));
                $file = file_put_contents($filename, $file);
                echo "" . $Summoner["TsNICK"] . " Status: " . $Summoner["summonerSTATUS"] .
                    " Not in game anymore. Delete from List<br />";
                $ts3_VirtualServer->clientGetByDbid($Summoner["TsDBID"])->message("[b]Your game is end. Channel will be delete[/b]");
                $userchannName = $ts3_VirtualServer->channelGetById($Summoner["TsCHANID"])->
                    channel_name;
                if (($userchannName == "Team One") || ($userchannName == "Team Two"))
                {
                    $ts3_VirtualServer->clientMove($Summoner["TsCLIENTID"], $AutomoveCHID);
                }
            } else
                echo "" . $Summoner["TsNICK"] . " Status: " . $Summoner["summonerSTATUS"] .
                    " Not in game. No Action<br />";
        } elseif ($Summoner["summonerSTATUS"] === "IN_GAME")
        {
            $searchfor = $Summoner["TsNICK"];
            $file = file_get_contents($filename);
            if (strpos($file, $searchfor) !== false)
            {
                echo "" . $Summoner["TsNICK"] . " Are Still in game<br />";
            } else
            {
                if (!in_array($Summoner['TsNICK'], explode(',', $usersAlreadymoved)))
                {
                    $person = $Summoner["TsNICK"];
                    $newperson = "\n" . $person;
                    file_put_contents($filename, $newperson, FILE_APPEND | LOCK_EX);
                    $Summoner['TsNICK'] = urlencode($Summoner['TsNICK']);


                    $response = Unirest::get("https://community-league-of-legends.p.mashape.com/api/v1.0/$region/summoner/retrieveInProgressSpectatorGameInfo/" .
                        $Summoner['TsNICK'], array("X-Mashape-Authorization" => "$MSkey"), null);
                    $array = ($response->raw_body);
                    $new = json_decode($array, true);


                    if (isset($new["success"]) == "false")
                    {
                        echo "No Game for player " . $Summoner['TsNICK'] .
                            " was found in the system <br />";
                        $ts3_VirtualServer->channelGetById("$lolmainCHid")->message("No Game for player " .
                            $Summoner['TsNICK'] . " was found in the system");
                        $userCIDNoGame = $ts3_VirtualServer->clientGetByDbid("" . $Summoner["TsDBID"] .
                            "")->cid;
                        //           if ($userCIDNoGame !== ){}


                    } elseif (empty($new))
                    {
                        echo "API not responding";
                        $ts3_VirtualServer->message("API not responding");
                        die;
                    } elseif (!empty($new["game"]))
                    {
                        //$ts3_VirtualServer->message("Looks like " . $Summoner['TsNICK'] . " are in game!!\n[b]LOADING DATA....[/b]");
                        echo "Looks like " . $Summoner['TsNICK'] . " are in game!!<br />";

                        $sumgameid = array();
                        $teamOne = array();
                        $teamTwo = array();

                        array_push($sumgameid, array(
                            "playerId" => $new["playerCredentials"]["playerId"],
                            "gameId" => $new["playerCredentials"]["gameId"],
                            "gametype" => $new["game"]["gameType"],
                            "gametypename" => $new["game"]["queueTypeName"]));


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
                            $tsu["TsNICK"] = str_replace(' ', '', $tsu["TsNICK"]);
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
                            $tsu["TsNICK"] = str_replace(' ', '', $tsu["TsNICK"]);
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
                                    "channel_topic" => "LoL Auto Move Channel",
                                    "channel_codec" => TeamSpeak3::CODEC_SPEEX_ULTRAWIDEBAND,
                                    "channel_codec_quality" => 0x08,
                                    "channel_flag_permanent" => true,
                                    "channel_password" => "123",
                                    "cpid" => $lolmainCHid,
                                    "channel_description" => "[center][b][size=15]== == GAME INFO == ==[/size][/b]\n\n\n[b]** Game Type **[/b]\n " .
                                        $tsUser["gametype"] . "\n\n[b]** Game Type Name **[/b]\n " . $tsUser["gametypename"] .
                                        "\n\n[b]*** LOLNEXUS ***[/b]\n\n[URL]http://www.lolnexus.com/" . $region .
                                        "/search?name=" . $tsUser["summonerInternalName"] . "&region=" . $region .
                                        "[/url][/center]"));


                                $Onecid = $ts3_VirtualServer->channelCreate(array(
                                    "channel_name" => "Team One",
                                    "channel_topic" => "Team One",
                                    "channel_codec" => TeamSpeak3::CODEC_SPEEX_ULTRAWIDEBAND,
                                    "channel_codec_quality" => 0x08,
                                    "channel_flag_permanent" => true,
                                    "channel_password" => "123",
                                    "cpid" => $cid,
                                    "channel_description" =>
                                        "[center][b][size=15]== == TEAM ONE INFO == ==[/size][/b]\n\n\n[b]** Team Members **\n\n " .
                                        $teamOne[0]["summonerInternalName"] . "\n" . $teamOne[1]["summonerInternalName"] .
                                        "\n" . $teamOne[2]["summonerInternalName"] . "\n" . $teamOne[3]["summonerInternalName"] .
                                        "\n" . $teamOne[4]["summonerInternalName"] . "\n"));

                                $Twocid = $ts3_VirtualServer->channelCreate(array(
                                    "channel_name" => "Team Two",
                                    "channel_topic" => "Team Two",
                                    "channel_codec" => TeamSpeak3::CODEC_SPEEX_ULTRAWIDEBAND,
                                    "channel_codec_quality" => 0x08,
                                    "channel_flag_permanent" => true,
                                    "channel_password" => "123",
                                    "cpid" => $cid,
                                    "channel_description" =>
                                        "[center][b][size=15]== == TEAM TWO INFO == ==[/size][/b]\n\n\n[b]** Team Members **\n\n " .
                                        $teamTwo[0]["summonerInternalName"] . "\n" . $teamTwo[1]["summonerInternalName"] .
                                        "\n" . $teamTwo[2]["summonerInternalName"] . "\n" . $teamTwo[3]["summonerInternalName"] .
                                        "\n" . $teamTwo[4]["summonerInternalName"] . "\n"));
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
                                            $searchfor = $tsmove["TsNICK"];
                                            $file = file_get_contents($filename);
                                            if (strpos($file, $searchfor) !== false)
                                            {
                                                echo "Exist in file";
                                            } else
                                            {
                                                $person = ($tsmove["TsNICK"]);
                                                $newperson = "\n" . $person;
                                                file_put_contents($filename, $newperson, FILE_APPEND | LOCK_EX);

                                            }
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
                                            $searchfor = $tsmove["TsNICK"];
                                            $file = file_get_contents($filename);
                                            if (strpos($file, $searchfor) !== false)
                                            {
                                                echo "Exist in file";
                                            } else
                                            {
                                                $person = $tsmove["TsNICK"];
                                                $newperson = "\n" . $person;
                                                file_put_contents($filename, $newperson, FILE_APPEND | LOCK_EX);

                                            }
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
        }
    } else
    {
        echo "" . $Summoner["TsNICK"] . " is not on $sumName frends list or is offline.<br />";

    }
}
$conn->disconnect();


function getSummststus($d)
{
    if (strpos($d, "inGame") !== false)
    {
        $status = "IN_GAME";
    } elseif (strpos($d, "championSelect") !== false)
    {
        $status = "CHAMPION_SELECT";
    } elseif (strpos($d, "outOfGame") !== false)
    {
        $status = "OUT_OF_GAME";
    } elseif (strpos($d, "spectating") !== false)
    {
        $status = "SPECTATING";
    } elseif (strpos($d, "inQueue") !== false)
    {
        $status = "IN_QUEUE";
    } elseif (strpos($d, "hostingRankedGame") !== false)
    {
        $status = "HOSTING_RANKED_GAME";
    } elseif (strpos($d, "hostingPracticeGame") !== false)
    {
        $status = "HOSTING_PRACTICE_GAME";
    } elseif (strpos($d, "hostingNormalGame") !== false)
    {
        $status = "HOSTING_NORMAL_GAME";
    } elseif (strpos($d, "teamSelect") !== false)
    {
        $status = "TEAM_SELECT";
    } elseif ($d == "Controller available.")
    {
        $status = "OFFLINE";
    } else
    {
        $status = "UNKNOWN";
    }
    return $status;
}
