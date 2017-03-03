# Google Cloud Storage Driver for Nails CDN Module

This is the Google Cloud Storage driver for the Nails CDN module, it allows the CDN to use Google Storage buckets as a storage mechanism.

http://nailsapp.co.uk/modules/cdn/driver/google-cloud-storage


## Installing

    composer require nailsapp/driver-cdn-google
    
    
## Configure

The driver can be enabled and configured via the admin interface.


## Credentials

In order for the SDK to authenticate properly with Google a "Service account" will need to be created for the application. You can do this in the Cloud Console:

https://console.cloud.google.com/apis/credentials

Download and save the key somewhere safe then make it available to the driver by either pasting the file's contents into the appropriate place in settings, or by placing it on your server and setting the file's path in driver settings.
