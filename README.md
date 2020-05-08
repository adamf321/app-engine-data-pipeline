# Data Pipeline for Nolte
This app provides a number of endpoints which are designed to run on Google App Engine, using the App Engine cron. They extract data from the Tempo API and load it into our data warehouse, BigQuery


## Local Development
In order for the app to run locally you will need to set-up a [service account](https://cloud.google.com/iam/docs/creating-managing-service-accounts) for yourself with the required permissions on the GCS bucket, Secrets Manager and BigQuery. Make sure to download the json file with the key.

1. Clone this repo
2. `composer install`
3. Run the app using the below command:
   
```
GOOGLE_APPLICATION_CREDENTIALS=/Users/adam/Downloads/NolteMetrics-34cc803fcfc5.json \
GC_PROJECT="nolte-metrics" \
GC_BUCKET="nolte-metrics.appspot.com" \
php -d variables_order=EGPCS -S localhost:8085 -t . index.php
```

The GOOGLE_APPLICATION_CREDENTIALS env variable overrides the default user the app is running with. This allows you to authenticate against GCP from your local environment. ** Replace /Users/adam/Downloads/NolteMetrics-34cc803fcfc5.json with the path to your key file. **

The other variables simulate the ones your find in app.yml.


## Deployment
Make sure you have the [gcloud CLI](https://cloud.google.com/sdk/docs/quickstarts) installed and authenticated. You will also need the appropriate permissions for App Engine on the nolte-metrics project.

´´´
gcloud --project nolte-metrics app deploy app.yaml
´´´

## What the app does

### Tempo
