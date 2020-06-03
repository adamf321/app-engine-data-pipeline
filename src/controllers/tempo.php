<?php

namespace Nolte\Metrics\DataPipeline;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

const TEMPO_PATH = '/tempo/{table:worklogs|plans|accounts}[/{updatedFrom}]';

/**
 * Extract Tempo data and import into BigQuery.
 */
$app->get(TEMPO_PATH, function (Request $request, Response $response, array $args) {

    // Only allow requests from App Engine Cron if running in the App Engine env
    // (ie skip this check if running locally).
    if (getenv('GAE_ENV') !== false) {
        $request_headers = getallheaders();
        if (! isset($request_headers['X-Appengine-Cron'])) {
            return $response->withStatus(401);
        }
    }

    $table = $args['table'];

    $logger = new Logger();
    $bucket = new Bucket();

    $counter = 0;
    $batch_ts = time();
    $objects = [];

    switch ($table) {
        case 'worklogs':
            $params = [
                'limit' => 1000,
                'updatedFrom' => $args['updatedFrom'] ?? date('Y-m-d', time() - 60 * 60 * 24),
            ];
            break;

        case 'plans':
            $params = [
                'limit' => 1000,
                'updatedFrom' => $args['updatedFrom'] ?? date('Y-m-d', time() - 60 * 60 * 24),
                'from' => date('Y-m-d', time()), // from today
                'to' => date('Y-m-d', time() + 60 * 60 * 24 * 365), // to 1 year's time
            ];
            break;

        default:
            $params = [];
    }

    $base_url = "https://api.tempo.io/core/3/$table?" . http_build_query($params);
    $temp_auth_token = get_secret('TEMPO_AUTH_TOKEN');

    $logger->info("Starting batch $batch_ts with base_url $base_url...");

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
        $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response_code > 299) {
            $logger->error(
                "Call to Tempo API failed with http response code $response_code. Request URL was $base_url."
            );
            return $response->withStatus(500);
        }

        $data = \json_decode($data_str);

        if (is_null($data) || '' === $data) {
            $logger->error('Empty response received from the Tempo API.');
            return $response->withStatus(500);
        }

        if (isset($data->errors)) {
            $logger->error($data->errors);
            return $response->withStatus(500);
        }

        if ($data->metadata->count > 0) {
            // Convert the result to Newline Deliminated JSON, http://ndjson.org/
            $ndjson = '';
        
            foreach ($data->results as $item) {
                $item->_batch_ts = $batch_ts;
                $ndjson .= \json_encode($item) . "\n";
            }

            // Upload the NDJSON to GCS
            try {
                $objects[] = $bucket->writeStringToGcs("tempo/$table/$batch_ts.$counter.staged.json", $ndjson);
            } catch (Exception $e) {
                $logger->error($e->getMessage());
            }

            $logger->info("Wrote file $counter with " . $data->metadata->count . " items (batch $batch_ts).");
            echo "Wrote file $counter with " . $data->metadata->count . " items (batch $batch_ts).<br>";
        } elseif (1 === $counter) {
            $logger->error('No items were imported. Does this look right?');
        }
    } while (isset($data->metadata->next));

    $logger->info("Completed extraction of batch $batch_ts. Wrote $counter files.");

    $bq = new BigQuery();

    foreach ($objects as $object) {
        try {
            $bq->importFile('tempo', $table, $object->gcsUri());
            $object->rename(\str_replace('.staged.json', '.imported.json', $object->name()));
        } catch (Exception $e) {
            $logger->error($e->getMessage());
        }
    }

    $logger->info("Completed import of batch $batch_ts. All done.");
})->setName('tempo');
