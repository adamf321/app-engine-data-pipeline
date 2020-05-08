<?php

namespace Nolte\Metrics\DataPipeline;

/**
 * Adds all the controllers to $app.  Follows Silex Skeleton pattern.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
// use Google\Cloud\BigQuery\BigQueryClient;
// use Google\Cloud\Core\ExponentialBackoff;
use Google\Cloud\Logging\LoggingClient;
use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;

/**
 * Extract Tempo data.
 */
$app->get('/tempo/{table:worklogs|plans|accounts|customers}/extract[/{updatedFrom}]', function(Request $request, Response $response, array $args) {

    $logging = new LoggingClient();
    $logger = $logging->psrLogger('app');

    $table = $args['table'];
    $bucket = new Bucket();

    $counter = 0;
    $batch_ts = time();
    $new_latest_update = 0;

    $params = [
        'limit' => 1000,
        'updatedFrom' => $args['updatedFrom'] ?? date( 'Y-m-d', time() - 60 * 60 * 24 ),
    ];

    $base_url = "https://api.tempo.io/core/3/$table?" . http_build_query( $params );

    // Get the Tempo Auth Token from the GCP Secrets Manager
    // https://console.cloud.google.com/security/secret-manager/secret/TEMPO_AUTH_TOKEN?project=nolte-metrics
    $secrets = new SecretManagerServiceClient();
    $tempo_secret_name = $secrets->secretVersionName(getenv('GC_PROJECT'), 'TEMPO_AUTH_TOKEN', 'latest');
    $tempo_secret = $secrets->accessSecretVersion($tempo_secret_name);
    $temp_auth_token = $tempo_secret->getPayload()->getData();

    $logger->info( "Starting batch $batch_ts with base_url $base_url..." );

    do {
        $counter++;

        // Get API URL
        $url = $data->metadata->next ?? $base_url;

        // Query the API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $temp_auth_token"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return the transfer as a string
        $data_str = curl_exec($ch);
        curl_close($ch);

        $data = \json_decode($data_str);

        if ( isset( $data->errors ) ) {
            var_dump( $data->errors );
            $logger->error( $data->errors );
            return $response->withStatus(500);
        }

        if ( $data->metadata->count > 0 ) {
            // Convert the result to Newline Deliminated JSON, http://ndjson.org/
            $ndjson = '';
        
            foreach( $data->results as $item ) {
                $item->_batch_ts = $batch_ts;
                $new_latest_update = max($new_latest_update, $item->updatedAt);
                $ndjson .= \json_encode($item) . "\n";
            }

            // Upload the NDJSON to GCS
            $bucket->write_string_to_gcs( "tempo/$table/staged/$batch_ts.$counter.json", $ndjson );

            $logger->info( "Wrote file $counter with " . $data->metadata->count . " items (batch $batch_ts)." );
            echo "Wrote file $counter with " . $data->metadata->count . " items (batch $batch_ts).<br>";
        }

    } while ( isset( $data->metadata->next ) );

    $logger->info( "Completed batch $batch_ts. Wrote $counter files." );

})->setName('tempo-extract');







// $app->get('/tempo/load/worklogs', function (Request $request, Response $response) {
//     // instantiate the bigquery table service
//     $bigQuery = new BigQueryClient();
//     $table = $bigQuery->dataset('testing')->table('worklogs');

//     // create the import job
//     $gcsUri = 'gs://nolte-metrics/test.json';
//     $loadConfig = $table->loadFromStorage($gcsUri)->sourceFormat('NEWLINE_DELIMITED_JSON')->writeDisposition('WRITE_APPEND');
//     $job = $table->runJob($loadConfig);

//     // poll the job until it is complete
//     $backoff = new ExponentialBackoff(10);
//     $backoff->execute(function () use ($job) {
//         print('Waiting for job to complete' . PHP_EOL);
//         $job->reload();
//         if (!$job->isComplete()) {
//             throw new Exception('Job has not yet completed', 500);
//         }
//     });

//     // check if the job has errors
//     if (isset($job->info()['status']['errorResult'])) {
//         $error = $job->info()['status']['errorResult']['message'];
//         printf('Error running job: %s' . PHP_EOL, $error);
//     } else {
//         print('Data imported successfully' . PHP_EOL);
//     }

// })->setName('tempo-load');