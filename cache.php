<?php
class storage {

  function delete_data($label){
    $key = $label . '_'. md5($label);
    $files = glob(__DIR__ . '/cache/cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) );

    if ($files) {
      foreach ($files as $file) {
        if (file_exists($file)) {
          unlink($file);
        }
      }
    }
  }

  function save_data($label, $data){
    $this->delete_data($label);
    $key = $label . '_'. md5($label);
    $file = __DIR__ . '/cache/cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key);
    $handle = fopen($file, 'w');
    fwrite($handle, serialize($data));
    fclose($handle);
  }

  function get_data($label){
    $key = $label .'_'. md5($label);
    $files = glob(__DIR__ . '/cache/cache.' . preg_replace('/[^A-Z0-9\._-]/i', '', $key) );
    if ($files) {
      $handle = fopen($files[0], 'r');
      $cache = fread($handle, filesize($files[0]));
      fclose($handle);
      return unserialize($cache);
    }
  }
}

