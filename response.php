<?php
require_once './MepsFpx.class.php';
$mepsFpx = new MepsFpx('SELLER_ID', '127.0.0.1', PORT_NUMBER, MepsFpx::PAYMENT_MODE_DEV);
$response = $mepsFpx->getResponse();
var_dump($response);  // Use this to debug the response