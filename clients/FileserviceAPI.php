<?php
/*!
 * Fileservice API Client
 *
 * Released under the MIT License (MIT)
 * Copyright (c) 2014 Christoffer Lindahl
 *
 * @version 1.0
 * @date 2014-11-29
 * @author Christoffer Lindahl <christoffer@kekos.se>
 */

class FileserviceAPI {
  private $username;
  private $password;
  private $last_response_headers = array();

  public function __construct($username, $password) {
    $this->username = $username;
    $this->password = $password;
  }

  public function send($file_path, $method, $data = array(), $request_headers = array()) {
    if (substr($file_path, 0, 1) == '/') {
      $file_path = substr($file_path, 1);
    }

    $options = array(
      CURLOPT_URL => 'http://localhost/' . $this->username . '/' . $file_path,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => true,
      CURLOPT_USERPWD => $this->username . ':' . $this->password
    );

    if ($method !== 'GET' && $method !== 'PUT') {
      $options[CURLOPT_POSTFIELDS] = self::encodePostData($data);
    }

    if ($method === 'DELETE') {
      $options[CURLOPT_CUSTOMREQUEST] = $method;

    } else if ($method === 'PUT') {
      $options[CURLOPT_PUT] = true;
      $options[CURLOPT_INFILE] = $data['handle'];
      $options[CURLOPT_INFILESIZE] = $data['size'];
    }

    if (count($request_headers) > 0) {
      $options[CURLOPT_HTTPHEADER] = $this->compileRequestHeaders($request_headers);
    }

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $raw_response = curl_exec($curl);

    curl_close($curl);

    @list($raw_headers, $body) = explode("\r\n\r\n", $raw_response);
    $this->last_response_headers = self::parseHttpHeaders($raw_headers);

    if ($this->last_response_headers['http_code'] == 100) {
      @list($raw_headers, $body) = explode("\r\n\r\n", $body);
      $this->last_response_headers = self::parseHttpHeaders($raw_headers);
    }

    return $body;
  }

  private function compileRequestHeaders($request_headers) {
    $headers = array();
    foreach ($request_headers as $key => $value) {
      $headers[] = $key . ': ' . $value;
    }

    return $headers;
  }

  public static function encodePostData($data) {
    $url_data = array();
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $value = self::encodePostData($value);
      } else {
        $value = urlencode($value);
      }

      $url_data[] = urlencode($key) . '=' . $value;
    }

    return implode('&', $url_data);
  }

  public static function parseHttpHeaders($raw_headers) {
    $headers = array();
    $previous_key = '';

    foreach (explode("\r\n", $raw_headers) as $line) {
      @list($key, $value) = explode(":", $line);

      if (isset($value)) {
        if (!isset($headers[$key])) {
          $headers[$key] = trim($value);

        } else {
          if (!is_array($headers[$key])) {
            $headers[$key] = array($headers[$key]);
          }

          $headers[$key][] = trim($value);
        }

        $previous_key = $key;

      } else {
        if (substr($value, 0, 1) === "\t") {
          $headers[$previous_key] .= "\r\n\t" . trim($value);

        } else {
          preg_match('#^HTTP/\d\.\d (\d+) #', trim($key), $matches);
          $headers['http_code'] = $matches[1];
        }
      }
    }

    return $headers;
  }

  public function getResponseHeader($key) {
    return (isset($this->last_response_headers[$key]) ? $this->last_response_headers[$key] : null);
  }
}
?>