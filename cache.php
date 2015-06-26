<?php
class storage {

  function delete_data($user_url, $mp_token){
    $key = $user_url . '_'.$mp_token.'_'. md5sum($user_url .$mp_token);
    $files = glob(__DIR__ . '/cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) );

    if ($files) {
      foreach ($files as $file) {
        if (file_exists($file)) {
          unlink($file);
        }
      }
    }
  }

  function save_data($user_url, $mp_token, $data){
    $key = $user_url . '_'.$mp_token.'_'. md5sum($user_url .$mp_token);
    $file = __DIR__ . '/cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key);
    $handle = fopen($file, 'w');
    fwrite($handle, serialize($data));
    fclose($handle);
  }

  function get_data($user_url, $mp_token){
    $key = $user_url . '_'.$mp_token.'_'. md5sum($user_url .$mp_token);
    $files = glob(__DIR__ . '/cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) );
    if ($files) {
      $handle = fopen($files[0], 'r');
      $cache = fread($handle, filesize($files[0]));
      fclose($handle);
      return unserialize($cache);
    }
  }
}

