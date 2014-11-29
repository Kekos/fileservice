<?php
/*!
 * Fileservice - Proxy for file operations on behalf of other Linux users.
 *
 * Released under the MIT License (MIT)
 * Copyright (c) 2014 Christoffer Lindahl
 *
 * @version 1.0
 * @date 2014-11-29
 * @author Christoffer Lindahl <christoffer@kekos.se>
 */

class Fileservice {
  private $username;
  private $password;
  private $cd = '';
  private $operation;
  private $response;

  public function __construct($response) {
    if (!is_file('fileservice.conf.json')) {
      $this->setStatusCode('500', 'No config file found');
    }

    $config = @json_decode(file_get_contents('fileservice.conf.json'));
    if ($config === null || !is_object($config)) {
      $this->setStatusCode('500', 'Config file found but was in wrong format');
    }

    $this->response = $response;
    $this->username = $config->username;
    $this->password = $config->password;

    $this->authenticate();
    $this->operation = $_SERVER['REQUEST_METHOD'];

    if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
      $this->operation = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
    }

    if (!chdir(dirname(getcwd()))) {
      $this->setStatusCode('500', 'Could not change directory to ' . dirname(getcwd()));
    }

    if (isset($_GET['path'])) {
      $this->cd = str_replace('../', '', $_GET['path']);
    }

    $this->cd = getcwd() . '/' . $this->cd;

    if (substr($this->cd, -1, 1) != '/') {
      $this->cd .= '/';
    }

    // For PUT requests, we don't actually care if the path exists or not.
    // BUT the parent directory has to exist!
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
      $this->cd = substr($this->cd, 0, -1);
      $parent_cd = dirname($this->cd);

      if (!file_exists($parent_cd)) {
        $this->setStatusCode(404, 'The requested resource ' . $parent_cd . ' could not be found.');
      }

    } else if (!file_exists($this->cd)) {
      $this->cd = substr($this->cd, 0, -1);

      if (!file_exists($this->cd)) {
        $this->setStatusCode(404, 'The requested resource ' . $this->cd . ' could not be found.');
      }
    }
  }

  public function handleRequest() {
    switch ($this->operation) {
      case 'OPTIONS':
        $this->handleOptions();
        break;

      case 'GET':
        $this->handleGet();
        break;

      case 'POST':
        $this->handlePost();
        break;

      case 'PUT':
        $this->handlePut();
        break;

      case 'DELETE':
        $this->handleDelete();
        break;
    }
  }

  private function handleOptions() {
    header('Allow: GET, POST, PUT, DELETE, OPTIONS');
  }

  private function handleGet() {
    // Directory
    if (is_dir($this->cd)) {
      $this->response->cwd = preg_replace('#^' . getcwd() . '#', '', $this->cd);
      $this->response->directories = array();
      $this->response->files = array();

      foreach (scandir($this->cd) as $file) {
        if ($file != '.' && $file != '..') {
          $file_path = $this->cd . $file;

          $finfo = new stdclass();
          $finfo->name = $file;
          $finfo->mtime = filemtime($file_path);

          if (is_dir($file_path)) {
            $this->response->directories[] = $finfo;
          } else {
            $finfo->size = filesize($file_path);
            $finfo->type = filetype($file_path);
            $this->response->files[] = $finfo;
          }
        }
      }

    // File
    } else {
      header('Content-Type: ' . $this->getMimeType());
      //header Cache och dispition för rätt filnamn!
      set_time_limit(0);

      $file = @fopen($this->cd, 'rb');
      while (!feof($file)) {
        echo @fread($file, 1024*8);
        ob_flush();
        flush();
      }

      fclose($file);
      exit;
    }
  }

  // PUT is for uploading files
  private function handlePut() {
    $filename = basename($this->cd);
    if (strlen($filename) < 1) {
      $this->setStatusCode(400, 'No filename provided.');
    }

    if (file_put_contents($this->cd, file_get_contents('php://input')) === false) {
      $this->setStatusCode(500, 'File could not be written.');

    } else {
      $this->setStatusCode(201, '', false);
    }
  }

  // POST is for directories and renaming files
  private function handlePost() {
    // Create a new directory inside current directory
    if (isset($_POST['name'])) {
      if (!self::isValidFileName($_POST['name'])) {
        $this->setStatusCode(422, 'Invalid directory name.');
      }

      $result = @mkdir($this->cd . $_POST['name']);
      if ($result) {
        $this->setStatusCode(201, '', false);
      } else {
        $this->setStatusCode(500, 'The directory could not be created.');
      }

    // Rename current directory
    } else if (isset($_POST['new_name'])) {
      if (!self::isValidFileName($_POST['new_name'])) {
        $this->setStatusCode(422, 'Invalid directory name.');
      }

      $result = @rename($this->cd, dirname($this->cd) . '/' . $_POST['new_name']);
      if ($result) {
        $this->setStatusCode(200, '', false);
      } else {
        $this->setStatusCode(500, 'The directory could not be renamed.');
      }

    // Error
    } else {
      $this->setStatusCode(400, 'Missing directory name.');
    }
  }

  private function handleDelete() {
    // Directory
    if (is_dir($this->cd)) {
      $result = @rmdir($this->cd);

      if ($result) {
        $this->setStatusCode(204, '', false);
      } else {
        $this->setStatusCode(500, 'The directory could not be deleted. Maybe it is not empty?');
      }

    // File
    } else if (is_file($this->cd)) {
      $result = @unlink($this->cd);

      if ($result) {
        $this->setStatusCode(204, '', false);
      } else {
        $this->setStatusCode(500, 'The file could not be deleted.');
      }

    // Error
    } else {
      $this->setStatusCode(404, 'Resource not found.');
    }
  }

  public static function isValidFileName($name) {
    return !preg_match('/[\\/]/', $name);
  }

  /**
    * Returns MIME-type for file
   */
  private function getMimeType() {
    $finfo = new finfo(FILEINFO_MIME);
    return $finfo->file($this->cd);
  }

  /**
   * Compares authentication data from client with key file in user's home
   * directory.
   */
  private function authenticate() {
    $auth = false;

    if (isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] == $this->username && $_SERVER['PHP_AUTH_PW'] == $this->password) {
      $auth = true;
    }

    if (!$auth) {
      header('WWW-Authenticate: Basic realm="Fileservice needs authentication"');
      $this->setStatusCode(401, 'Unauthorized');
    }
  }

  /**
   * Sets response HTTP status code
   * @param int $code The status code to set
   * @param [string] $exception_message An optional message to set to thrown exception
   * @param [bool] $throw If true, an exception will be thrown
   */
  private function setStatusCode($code, $exception_message = '', $throw = true) {
    http_response_code($code);

    if ($throw) {
      throw new Exception($exception_message, $code);
    }
  }
}
?>