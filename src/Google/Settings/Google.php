<?php

namespace Nails\Cdn\Driver\Goolge\Settings;

use Nails\Common\Helper\Form;
use Nails\Common\Interfaces;
use Nails\Common\Service\FormValidation;
use Nails\Components\Setting;
use Nails\Factory;

/**
 * Class Google
 *
 * @package Nails\Cdn\Driver\Google\Settings
 */
class Google implements Interfaces\Component\Settings
{
    const KEY_ACCESS_KEY_FILE    = 'key_file';
    const KEY_BUCKETS            = 'buckets';
    const KEY_URL_SERVE          = 'uri_serve';
    const KEY_URL_SERVE_SECURE   = 'uri_serve_secure';
    const KEY_URL_PROCESS        = 'uri_process';
    const KEY_URL_PROCESS_SECURE = 'uri_process_secure';

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return 'CDN: Google Cloud Storage';
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        /** @var Setting $oKeyFile */
        $oKeyFile = Factory::factory('ComponentSetting');
        $oKeyFile
            ->setKey(static::KEY_ACCESS_KEY_FILE)
            ->setType(Form::FIELD_TEXTAREA)
            ->setLabel('Key File')
            ->setEncrypted(true)
            ->setInfo('This can be the key file contents, or a path to the key file')
            ->setFieldset('Credentials')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oBuckets */
        $oBuckets = Factory::factory('ComponentSetting');
        $oBuckets
            ->setKey(static::KEY_BUCKETS)
            ->setType(Form::FIELD_TEXTAREA)
            ->setLabel('Buckets')
            ->setFieldset('Buckets')
            ->setInfo('Buckets should be specified as a JSON object with the environment as the key, and the bucket as the value. e.g. <code>{"PRODUCTION":"my-bucket"}</code>')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oUrlServe */
        $oUrlServe = Factory::factory('ComponentSetting');
        $oUrlServe
            ->setKey(static::KEY_URL_SERVE)
            ->setLabel('Serving URL')
            ->setFieldset('URLs')
            ->setDefault('https://{{bucket}}.storage.googleapis.com')
            ->setInfo('Value will be passed into <code>siteUrl()</code>')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oUrlServeSecure */
        $oUrlServeSecure = Factory::factory('ComponentSetting');
        $oUrlServeSecure
            ->setKey(static::KEY_URL_SERVE_SECURE)
            ->setLabel('Serving URL (Secure)')
            ->setFieldset('URLs')
            ->setDefault('https://{{bucket}}.storage.googleapis.com')
            ->setInfo('Value will be passed into <code>siteUrl()</code>')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oUrlProcess */
        $oUrlProcess = Factory::factory('ComponentSetting');
        $oUrlProcess
            ->setKey(static::KEY_URL_PROCESS)
            ->setLabel('Processing URL')
            ->setFieldset('URLs')
            ->setDefault('cdn')
            ->setInfo('Value will be passed into <code>siteUrl()</code>')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oUrlProcessSecure */
        $oUrlProcessSecure = Factory::factory('ComponentSetting');
        $oUrlProcessSecure
            ->setKey(static::KEY_URL_PROCESS_SECURE)
            ->setLabel('Processing URL (Secure)')
            ->setFieldset('URLs')
            ->setDefault('cdn')
            ->setInfo('Value will be passed into <code>siteUrl()</code>')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        return [
            $oKeyFile,
            $oBuckets,
            $oUrlServe,
            $oUrlServeSecure,
            $oUrlProcess,
            $oUrlProcessSecure,
        ];
    }
}
