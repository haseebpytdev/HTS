<?php

$userId = 'V1:op68xv0wovxdr78z:DEVCENTER:EXT';
$password = '5PcYsg2D';

$encodedUser = base64_encode(trim($userId));
$encodedPass = base64_encode(trim($password));

$token = base64_encode($encodedUser . ':' . $encodedPass);

echo $token . PHP_EOL;
