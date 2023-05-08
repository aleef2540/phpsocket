<?php
define('HOST_NAME',"192.168.0.101"); 
define('PORT',"8090");
$null = NULL;

require_once("class.php");
$chatHandler = new ChatHandler();

// สร้าง Socket (IPv4,socket STREAM สำหรับ TCP, TCP Protocal)
$socketResource = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);


// set option (Socket ที่สร้างจาก create, ระดับ Protocal, option, value)
if (!socket_set_option($socketResource, SOL_SOCKET, SO_REUSEADDR, 1)) {
    echo 'Unable to set option on socket: '. socket_strerror(socket_last_error()) . PHP_EOL;
}else{
	echo 'socket_set_option successfully !!'. PHP_EOL;
}

// ผูก socket ที่สร้างกับ ip port
if (!socket_bind($socketResource, HOST_NAME, PORT)) {
    echo 'Unable to bind socket: '. socket_strerror(socket_last_error()) . PHP_EOL;
}else{
	echo 'socket_bind successfully !!'. PHP_EOL;
}

// รอรับ connecttion
socket_listen($socketResource);
echo 'Socket Is Ready !!'. PHP_EOL;

$clientSocketArray = array($socketResource);

while (true) {
	$newSocketArray = $clientSocketArray;
	socket_select($newSocketArray, $null, $null, 0, 10);
	
	// เช็คใน array newSocketArray มี socketResource.
	// เช็คการเชื่อมต่อทำ handshake
	if (in_array($socketResource, $newSocketArray)) {
		// รับการเชื่อมต่อ นำค่าไปเก็บใน array
		
		$newSocket = socket_accept($socketResource);
		$clientSocketArray[] = $newSocket;
		print_r($clientSocketArray);
		
		$header = socket_read($newSocket, 1024);
		echo 'hearder : '.$header;
		$chatHandler->doHandshake($header, $newSocket, HOST_NAME, PORT);
		
		socket_getpeername($newSocket, $client_ip_address);
		$connectionACK = $chatHandler->newConnectionACK($client_ip_address);
		
		$chatHandler->send($connectionACK);
		
		$newSocketIndex = array_search($socketResource, $newSocketArray);
		unset($newSocketArray[$newSocketIndex]);
	}
	
	foreach ($newSocketArray as $newSocketArrayResource) {	
		while(socket_recv($newSocketArrayResource, $socketData, 1024, 0) >= 1){
			$socketMessage = $chatHandler->unseal($socketData);
			$messageObj = json_decode($socketMessage);
			
			$chat_box_message = $chatHandler->createChatBoxMessage($messageObj->chat_user, $messageObj->chat_message);
			$chatHandler->send($chat_box_message);
			break 2;
		}
		
		$socketData = @socket_read($newSocketArrayResource, 1024, PHP_NORMAL_READ);
		if ($socketData === false) { 
			socket_getpeername($newSocketArrayResource, $client_ip_address);
			$connectionACK = $chatHandler->connectionDisconnectACK($client_ip_address);
			$chatHandler->send($connectionACK);
			$newSocketIndex = array_search($newSocketArrayResource, $clientSocketArray);
			unset($clientSocketArray[$newSocketIndex]);			
		}
	}
}
socket_close($socketResource);
?>