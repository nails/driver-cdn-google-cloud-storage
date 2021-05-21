# Google Cloud Storage Driver for Nails CDN Module

![license](https://img.shields.io/badge/license-MIT-green.svg)
[![tests](https://github.com/nails/driver-cdn-google-cloud-storage/actions/workflows/build_and_test.yml/badge.svg)](https://github.com/nails/driver-cdn-google-cloud-storage/actions)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nails/driver-cdn-google-cloud-storage/badges/quality-score.png)](https://scrutinizer-ci.com/g/nails/driver-cdn-google-cloud-storage)

This is the Google Cloud Storage driver for the Nails CDN module, it allows the CDN to use Google Storage buckets as a storage mechanism.


## Installing

    composer require nails/driver-cdn-google


## Configure

The driver can be enabled and configured via the admin interface.


## Credentials

In order for the SDK to authenticate properly with Google a "Service account" will need to be created for the application. You can do this in the Cloud Console:

https://console.cloud.google.com/apis/credentials

Download and save the key somewhere safe then make it available to the driver by either pasting the file's contents into the appropriate place in settings, or by placing it on your server and setting the file's path in driver settings.
