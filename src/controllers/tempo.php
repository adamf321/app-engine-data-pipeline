<?php

namespace Nolte\Metrics\DataPipeline;

/**
 * Adds all the controllers to $app.  Follows Silex Skeleton pattern.
 */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Google\Cloud\Logging\LoggingClient;
use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;

/**
 * Extract Tempo data.
 */
$app->get('/tempo/{table:worklogs|plans|accounts}[/{updatedFrom}]', function(Request $request, Response $response, array $args) {

    // Only allow requests from App Engine Cron if running in the App Engine env (ie skipp this check if running locally).
    if ( getenv('GAE_ENV') !== false ) {
        $request_headers = getallheaders();
        if( ! isset($request_headers['X-Appengine-Cron']) ) {
            return $response->withStatus(401);
        }
    }

    $logging = new LoggingClient();
    $logger = $logging->psrLogger('app');

    $table = $args['table'];
    $bucket = new Bucket();

    $counter = 0;
    $batch_ts = time();
    $new_latest_update = 0;
    $objects = [];

    switch ( $table ) {
        case 'worklogs':
            $params = [
                'limit' => 1000,
                'updatedFrom' => $args['updatedFrom'] ?? date( 'Y-m-d', time() - 60 * 60 * 24 ),
            ];
        break;

        case 'plans':
            $params = [
                'limit' => 1000,
                'updatedFrom' => $args['updatedFrom'] ?? date( 'Y-m-d', time() - 60 * 60 * 24 ),
                'from' => date( 'Y-m-d', time() ), // from today
                'to' => date( 'Y-m-d', time() + 60 * 60 * 24 * 365 ), // to 1 year's time
            ];
        break;

        default:
            $params = [];
    }

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
                $ndjson .= \json_encode($item) . "\n";
            }

            // Upload the NDJSON to GCS
            $objects[] = $bucket->write_string_to_gcs( "tempo/$table/$batch_ts.$counter.staged.json", $ndjson );

            $logger->info( "Wrote file $counter with " . $data->metadata->count . " items (batch $batch_ts)." );
            echo "Wrote file $counter with " . $data->metadata->count . " items (batch $batch_ts).<br>";
        }

    } while ( isset( $data->metadata->next ) );

    $logger->info( "Completed extraction of batch $batch_ts. Wrote $counter files." );

    $bq = new BigQuery();

    foreach( $objects as $object ) {
        $bq->import_file( 'tempo', $table, $object->gcsUri() );
        $object->rename( \str_replace( '.staged.json', '.imported.json', $object->name() ) );
    }

    $logger->info( "Completed import of batch $batch_ts. All done." );

})->setName('tempo');
