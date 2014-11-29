<?php
/*!
 * Fileservice entrypoint
 *
 * Released under the MIT License (MIT)
 * Copyright (c) 2014 Christoffer Lindahl
 *
 * @version 1.0
 * @date 2014-11-24
 * @author Christoffer Lindahl <christoffer@kekos.se>
 */

define('FILESERVICE_ROOT', '/var/www/fileservice/');
require_once FILESERVICE_ROOT . 'Fileservice.php';

$response = new stdclass();

try {
  $fileservice = new Fileservice($response);
  $fileservice->handleRequest();

} catch (Exception $ex) {
  $response->error = $ex->getMessage();
  $response->code = $ex->getCode();
}

// Always return with a JSON string
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($response, JSON_PRETTY_PRINT);
?>