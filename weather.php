<?php
session_start();
header("Content-Type: text/xml");

echo '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>';

function gather($digits,$action,$audio)
{
  echo "<Gather numDigits='$digits' action='$action'>";
  echo "<Play>$audio</Play>";
  echo "</Gather>";
}

function play($action,$audio)
{
  echo "<Play action='$action'>$audio</Play>";
}

function forward($location)
{
  echo "<Forward >$location</Forward>";
}



function openweathermap($zip)
{

    $url = "https://api.openweathermap.org/data/2.5/weather?zip=".$zip.",us&appid=2f3478d18778c6ab48936f69400579c5&units=imperial";
    $session = curl_init($url);
    curl_setopt($session, CURLOPT_RETURNTRANSFER,true);
    $json = curl_exec($session);
    $phpObj =  json_decode($json,true);

    $city = $phpObj['name'];

    $currentText = $phpObj['weather'][0]['description'];
    $currentTemp = $phpObj['main']['temp'];

    $speech = "The current weather for ".$city." is ".$currentText." and ".$currentTemp." degrees. ";

    return $speech;

}

function getAudioFile($speech)
{
    //This next line shoudl be replaced with a authenticated oauth token in best practice.
    $url = "https://local:only@127.0.0.1/ns-api/?object=voice&action=token&domain=netsapiens.cloud";
    $session = curl_init($url);
    curl_setopt($session, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);
    $json = curl_exec($session);
    $phpObj =  json_decode($json,true);

    curl_close($session);

    $token = $phpObj['token'];
    $serviceBaseUri = $phpObj['serviceBaseUri'];

    $url = $serviceBaseUri . '/voices?language=en-US';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $output = curl_exec($ch);
    $phpObj =  json_decode($output,true);
    $voices = $phpObj['voices'];

    $voice = false;
    for ($i = 0; $i < count($voices); $i++) {
        if (isset($voices[$i]['id']) && $voices[$i]['name'] == "Brian")
        {
          $voice = $voices[$i];
          break;
        }
    }
    curl_close($ch);


    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serviceBaseUri . '/tts');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $token));
    $data = json_encode(array('voice' => $voice, 'text' => $speech));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $output = curl_exec($ch);
    curl_close($ch);


    $output = json_decode($output, true);
    $tmp_name = 'tts_'.uniqid();
    $binary = "";
    for($index = 0; $index < count($output['audioStream']['data']); $index++)
    {
        $binary = $binary.pack("C*", $output['audioStream']['data'][$index]);
    }

    file_put_contents("/var/www/html/weatherivr/audio/".$tmp_name, $binary);

    $cmd1 = '/usr/bin/mpg123 -w '."/tmp/".$tmp_name.'.wav '."/var/www/html/weatherivr/audio/".$tmp_name;

    $cmd2 = '/usr/bin/sox '."/tmp/".$tmp_name.'.wav  -e mu-law -r 8000 -c 1 -b 8 '."/var/www/html/weatherivr/audio/".$tmp_name.".wav";
    exec($cmd1);
    exec($cmd2);


    return "https://".$_SERVER['SERVER_NAME']."/weatherivr/audio/".$tmp_name.".wav";

}



if (!isset($_REQUEST["case"])) {
  $speech = "Thank you for calling the NetSapiens UGM weather application. ";
  $speech .= "Please enter a zip code to get a weather report";
  gather(5,"weather.php?case=playzip",getAudioFile($speech));

}
else if ($_REQUEST["case"] == "playzip") {

  $speech = openweathermap($_REQUEST["Digits"]);
  $audioPath = getAudioFile($speech);

  play("weather.php",$audioPath);
}
