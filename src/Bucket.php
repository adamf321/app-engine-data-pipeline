<?php

namespace Nolte\Metrics\DataPipeline;

use Google\Cloud\Storage\StorageClient;

Class Bucket {

   private $bucket;

   public function __construct()
   {
      // [START gae_php_app_storage_client_setup]
      $storage = new StorageClient();
      $this->bucket = $storage->bucket( getenv('GC_BUCKET') );
      // [END gae_php_app_storage_client_setup]
   }

   public function write_string_to_gcs( $key, $content ) {
      // Write the content to a tmp file
      $tmp = tempnam(sys_get_temp_dir(), 'tempo');
      file_put_contents($tmp, $content);

      // Then uplod it to GCS
      $file = fopen($tmp, 'r');
      $object = $this->bucket->upload($file, [
         'name' => $key,
         'predefinedAcl' => 'projectPrivate',
      ]);
   }

   public function read_string_from_gcs( $key ) {
      if ( $this->object_exists( $key ) ) {
         $obj = $this->bucket->object($key);
        
         $obj->downloadToFile(sys_get_temp_dir() . '/random.tmp');
      
         return file_get_contents(sys_get_temp_dir() . '/random.tmp');
      }
   
      return false;
   }

   public function object_exists( $key ) {
      $objs = $this->bucket->objects();

      foreach( $objs as $obj ) {
         if ( $key === $obj->name() ) {
            return true;
         }
      }

      return false;
   }
}