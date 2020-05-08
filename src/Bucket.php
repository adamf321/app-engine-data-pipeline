<?php

namespace Nolte\Metrics\DataPipeline;

use Google\Cloud\Storage\StorageClient;

/**
 * Represents a single bucket on GCS.
 */
Class Bucket {

   private $bucket;

   /**
    * Constructor
    */
   public function __construct()
   {
      // [START gae_php_app_storage_client_setup]
      $storage = new StorageClient();
      $this->bucket = $storage->bucket( getenv('GC_BUCKET') );
      // [END gae_php_app_storage_client_setup]
   }

   /**
    * Takes a string and writes it to a GCS objecte with a given key.
    *
    * @param string $key     The object key.
    * @param string $content The content to write.
    * @return void
    */
   public function write_string_to_gcs( string $key, string $content ) {
      // Write the content to a tmp file
      $tmp = tempnam(sys_get_temp_dir(), 'tempo');
      file_put_contents($tmp, $content);

      // Then uplod it to GCS
      $file = fopen($tmp, 'r');

      return $this->bucket->upload($file, [
         'name' => $key,
         'predefinedAcl' => 'projectPrivate',
      ]);
   }
}
