<?php

namespace Nolte\Metrics\DataPipeline;

use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;


/**
 * Get a secret from the GCP Secrets Manager.
 * https://console.cloud.google.com/security/secret-manager/secret/TEMPO_AUTH_TOKEN?project=nolte-metrics.
 *
 * @param string $name The name of the secret.
 * @return void
 */
function get_secret(string $name)
{
    $secrets = new SecretManagerServiceClient();
    $secret_name = $secrets->secretVersionName(getenv('GC_PROJECT'), $name, 'latest');
    $secret = $secrets->accessSecretVersion($secret_name);
   
    return $secret->getPayload()->getData();
}

