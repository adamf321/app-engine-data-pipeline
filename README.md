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

The GOOGLE_APPLICATION_CREDENTIALS env variable overrides the default user the app is running with. This allows you to authenticate against GCP from your local environment. **Replace /Users/adam/Downloads/NolteMetrics-34cc803fcfc5.json with the path to your key file.**

The other variables simulate the ones your find in app.yml.


## Deployment
Make sure you have the [gcloud CLI](https://cloud.google.com/sdk/docs/quickstarts) installed and authenticated. You will also need the appropriate permissions for App Engine on the nolte-metrics project.

```
gcloud --project nolte-metrics app deploy app.yaml cron.yaml
```

## What the app does

### Tempo
Extracts worklogs, plans and accounts from the Tempo API and loads them into BigQuery. The steps of each extraction are:

1. Export the data into [NDJSON](http://ndjson.org/) files in GCS. For API results which ar paged we need to loop round and create a field for each page of results. Files names are of the format `tempo/[table]/[batch_timestamp].[sequence_number].staged.json`
2. Import each file one by one into BigQuery. Change the suffix to `.imported.json`.

We extract all files at the start to make it easier to debug and rerun the job if it fails. The file suffixes also show quickly which files were imported or not. Note that the bucket is configured to delete files older than 30 days.

The endpoints called to run the extract are `tempo/worklogs`, `tempo/plans` and `tempo/accounts`.

For worklogs and plans you can specify the optional upatedFrom param at the end of the URL, eg `tempo/worklogs/2019-01-01`. By default they will use yesterday's date as the jobs are meant to run every 24hrs to pull in records from the previous day. See `cron.yaml` for the job scheduling.

### Adding a new job
Please follow the same pattern for other jobs you create:
- Endpoint name format is `[dataset]/[table]{/[optional_additional_params]}`
- Files saved to GCS have format `[dataset]/[table]/[batch_timestamp].[sequence_number].staged.json`. And update `staged` to `imported` once imported.
- Add the controller to the `src/controllers` folder. Each controller represents a dataset.

