<?php

namespace Nolte\Metrics\DataPipeline;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\Core\ExponentialBackoff;
use Google\Cloud\Logging\LoggingClient;

/**
 * Represents a BigQuery connection.
 */
Class BigQuery {

   private $big_query;
   private $logger;

   /**
    * Constructor
    */
   public function __construct()
   {
      $this->big_query = new BigQueryClient();
      
      $logging = new LoggingClient();
      $this->logger = $logging->psrLogger('app');
   }

   /**
    * Imports a file stored on GCS to BigQuery
    *
    * @param string $dataset The dataset to import into.
    * @param string $table   The table within the dataset.
    * @param string $gcsUri  The URI of the file on GCS.
    * @return void
    */
   public function import_file( string $dataset, string $table, string $gcsUri ) {
      $table = $this->big_query->dataset( $dataset )->table( $table );

      // create the import job
      $loadConfig = $table->loadFromStorage($gcsUri)->sourceFormat('NEWLINE_DELIMITED_JSON')->writeDisposition('WRITE_APPEND');
      $job = $table->runJob($loadConfig);
   
      // poll the job until it is complete
      $backoff = new ExponentialBackoff(10);
      $backoff->execute(function () use ($job) {
         print('Waiting for job to complete' . PHP_EOL);
         $job->reload();
         if (!$job->isComplete()) {
            throw new Exception('Job has not yet completed', 500);
         }
      });
   
      // check if the job has errors
      if (isset($job->info()['status']['errorResult'])) {
         $error = $job->info()['status']['errorResult']['message'];
         printf('Error running job: %s' . PHP_EOL, $error);
         $logger->error( 'Error running job: %s' . PHP_EOL, $error );
         throw new Exception('Error running job: %s' . PHP_EOL, 500);
      } else {
         print('Data imported successfully' . PHP_EOL);
      }
   }
}