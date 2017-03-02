# Docs for `nailsapp/driver-cdn-google-cloud-storage`
> Documentation is a WIP.


Enable this driver by setting the `APP_CDN_DRIVER` constant to `nailsapp/driver-cdn-google-cloud-storage`.


## Configuration

The following constants can must be defined in order to correctly configure the driver:

| Constant                                       | Description                                                                        | Default |
|------------------------------------------------|------------------------------------------------------------------------------------|---------|
| `APP_CDN_DRIVER_GOOGLE_KEY_FILE`               | The JSON key file used to authenticate. Can be a JSON string, or a file path       | `null`  |
| `APP_CDN_DRIVER_GOOGLE_BUCKET_{{ENVIRONMENT}}` | The Cloud Storage bucket to use for the environment specified in `{{ENVIRONMENT}}` | `null`  |


Optionally, you can further configure the driver with the following options:

| Constant                                   | Description                                                     | Default                                     |
|--------------------------------------------|-----------------------------------------------------------------|---------------------------------------------|
| `APP_CDN_DRIVER_GOOGLE_URI_SERVE`          | The URI to serve objects from.                                  | `http://{{bucket}}.storage.googleapis.com`  |
| `APP_CDN_DRIVER_GOOGLE_URI_SERVE_SECURE`   | The secure URI to serve objects from.                           | `https://{{bucket}}.storage.googleapis.com` |
| `APP_CDN_DRIVER_GOOGLE_URI_PROCESS`        | The URI to serve objects from which will be transformed.        | `site_url('cdn')`                           |
| `APP_CDN_DRIVER_GOOGLE_URI_PROCESS_SECURE` | The secure URI to serve objects from which will be transformed. | `site_url('cdn', true)`                     |


## Credentials

In order for the SDK to authenticate properly with Google a "Service account" will need to be created for the application. You can do this in the Cloud Console:

https://console.cloud.google.com/apis/credentials

Download and save the key somewhere safe then make it available to the driver via the `APP_CDN_DRIVER_GOOGLE_KEY_FILE` constant. 
