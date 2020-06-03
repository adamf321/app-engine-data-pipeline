<?php

namespace Nolte\Metrics\DataPipeline;

use Google\Cloud\Logging\LoggingClient;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;

/**
 * A logger for logging to the GCP log, https://console.cloud.google.com/logs.
 * Filter on logName="projects/nolte-metrics/logs/data-pipline" to see these logs.
 */
class Logger
{

    private $logger;
    private $mail;
    private $job_name;

   /**
    * Constructor
    */
    public function __construct(string $job_name)
    {
        $logging = new LoggingClient();
        $this->logger = $logging->psrLogger('data-pipeline');

        $this->mail = new PostmarkClient(get_secret('POSTMARK_AUTH_TOKEN'));

        $this->job_name = $job_name;
    }

  /**
    * Log an info message.
    *
    * @param string $msg The message to log.
    * @return void
    */
    public function info(string $msg)
    {
        $this->logger->info($msg);
    }

   /**
    * Log an error message. Also send an alert email.
    *
    * @param string $msg The message to log.
    * @return void
    */
    public function error(string $msg)
    {
        $this->mail->sendEmail(
            'developer@getmoxied.net',
            getenv('ALERT_EMAIL'),
            "ERROR: Pipeline Failure: $this->job_name",
            "The error message was: $msg"
        );
      
        $this->logger->error($msg);
    }
}
