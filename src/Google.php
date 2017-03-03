<?php

namespace Nails\Cdn\Driver;

use Google\Cloud\Storage\StorageClient;
use Nails\Cdn\Exception\DriverException;
use Nails\Environment;

class Google extends Local
{
    /**
     * The Google Cloud SDK
     * @var StorageClient
     */
    protected $oSdk;

    /**
     * The Google Storage bucket where items will be stored (not to be confused with internal buckets)
     * @var string
     */
    protected $sGSBucket;

    /**
     * The endpoint for serving items from storage
     * @var string
     */
    protected $sUriServe;

    /**
     * The endpoint for securely serving items from storage
     * @var string
     */
    protected $sUriServeSecure;

    /**
     * The endpoint for serving items which are to be processed
     * @var string
     */
    protected $sUriProcess;

    /**
     * The endpoint for securely serving items which are to be processed
     * @var string
     */
    protected $sUriProcessSecure;

    // --------------------------------------------------------------------------

    /**
     * Google constructor.
     * @throws DriverException
     */
    public function __construct()
    {
        $aConstants = [
            'APP_CDN_DRIVER_GOOGLE_KEY_FILE',
            'APP_CDN_DRIVER_GOOGLE_BUCKET_' . Environment::get(),
        ];
        foreach ($aConstants as $sConstant) {
            if (!defined($sConstant)) {
                throw new DriverException(
                    'Constant "' . $sConstant . '" is not defined'
                );
            }
        }

        // --------------------------------------------------------------------------

        //  Instantiate the SDK
        $sKeyFile = constant('APP_CDN_DRIVER_GOOGLE_KEY_FILE');
        if (is_file($sKeyFile)) {
            $sKey = file_get_contents($sKeyFile);
        } else {
            $sKey = $sKeyFile;
        }
        $aKey = json_decode($sKey, true);

        $this->oSdk = new StorageClient([
            'keyFile' => $aKey,
        ]);

        //  Set the bucket we're using
        $this->sGSBucket = constant('APP_CDN_DRIVER_GOOGLE_BUCKET_' . Environment::get());

        // --------------------------------------------------------------------------

        //  Set default values
        $aProperties = [
            ['sUriServe', 'APP_CDN_DRIVER_GOOGLE_URI_SERVE', 'http://{{bucket}}.storage.googleapis.com'],
            ['sUriServeSecure', 'APP_CDN_DRIVER_GOOGLE_URI_SERVE_SECURE', 'https://{{bucket}}.storage.googleapis.com'],
            ['sUriProcess', 'APP_CDN_DRIVER_GOOGLE_URI_PROCESS', site_url('cdn')],
            ['sUriProcessSecure', 'APP_CDN_DRIVER_GOOGLE_URI_PROCESS_SECURE', site_url('cdn', true)],
        ];
        foreach ($aProperties as $aProperty) {
            list($sProp, $sConst, $sDefault) = $aProperty;
            if (is_null($this->{$sProp})) {
                if (defined($sConst)) {
                    $this->{$sProp} = str_replace('{{bucket}}', $this->sGSBucket, addTrailingSlash(constant($sConst)));
                } else {
                    $this->{$sProp} = str_replace('{{bucket}}', $this->sGSBucket, addTrailingSlash($sDefault));
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * OBJECT METHODS
     */

    /**
     * Creates a new object
     *
     * @param  \stdClass $oData Data to create the object with
     *
     * @return boolean
     */
    public function objectCreate($oData)
    {
        $sBucket       = !empty($oData->bucket->slug) ? $oData->bucket->slug : '';
        $sFilenameOrig = !empty($oData->filename) ? $oData->filename : '';
        $sFilename     = strtolower(substr($sFilenameOrig, 0, strrpos($sFilenameOrig, '.')));
        $sExtension    = strtolower(substr($sFilenameOrig, strrpos($sFilenameOrig, '.')));
        $sSource       = !empty($oData->file) ? $oData->file : '';
        $sMime         = !empty($oData->mime) ? $oData->mime : '';
        $sName         = !empty($oData->name) ? $oData->name : 'file' . $sExtension;
        $sObject       = $sBucket . '/' . $sFilename . $sExtension;
        $sObjectDl     = $sBucket . '/' . $sFilename . '-download' . $sExtension;

        // --------------------------------------------------------------------------

        try {

            //  Create "normal" version
            $this->oSdk
                ->bucket($this->sGSBucket)
                ->upload(
                    fopen($sSource, 'r'),
                    [
                        'name'          => $sObject,
                        'predefinedAcl' => 'publicRead',
                        'metadata'      => [
                            'contentType' => $sMime,
                        ],
                    ]
                );

            //  Create "download" version
            $this->oSdk
                ->bucket($this->sGSBucket)
                ->object($sObject)
                ->copy(
                    $this->sGSBucket,
                    [
                        'name'          => $sObjectDl,
                        'predefinedAcl' => 'publicRead',
                    ]
                );

            //  Apply new meta data to download version
            $this->oSdk
                ->bucket($this->sGSBucket)
                ->object($sObjectDl)
                ->update(
                    [
                        'contentType'        => 'application/octet-stream',
                        'contentDisposition' => 'attachment; filename="' . str_replace('"', '', $sName) . '" ',
                    ]
                );

            return true;

        } catch (\Exception $e) {
            $this->setError('GOOGLE-SDK EXCEPTION [objectCreate]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object exists or not
     *
     * @param  string $sFilename The object's filename
     * @param  string $sBucket   The bucket's slug
     *
     * @return boolean
     */
    public function objectExists($sFilename, $sBucket)
    {
        return $this->oSdk
            ->bucket($this->sGSBucket)
            ->object($sBucket . '/' . $sFilename)
            ->exists();
    }

    // --------------------------------------------------------------------------

    /**
     * Destroys (permanently deletes) an object
     *
     * @param  string $sObject The object's filename
     * @param  string $sBucket The bucket's slug
     *
     * @return boolean
     */
    public function objectDestroy($sObject, $sBucket)
    {
        try {

            $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
            $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));
            $sObject    = $sBucket . '/' . $sFilename . $sExtension;
            $sObjectDl  = $sBucket . '/' . $sFilename . '-download' . $sExtension;

            //  Delete "normal" version
            $this->oSdk
                ->bucket($this->sGSBucket)
                ->object($sObject)
                ->delete();

            //  Delete "download" version
            $this->oSdk
                ->bucket($this->sGSBucket)
                ->object($sObjectDl)
                ->delete();

            return true;

        } catch (\Exception $e) {
            $this->setError('GOOGLE-SDK EXCEPTION [objectDestroy]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object
     *
     * @param  string $sBucket   The bucket's slug
     * @param  string $sFilename The filename
     *
     * @return mixed             String on success, false on failure
     */
    public function objectLocalPath($sBucket, $sFilename)
    {
        //  Do we have the original source file?
        $sExtension = strtolower(substr($sFilename, strrpos($sFilename, '.')));
        $sFilename  = strtolower(substr($sFilename, 0, strrpos($sFilename, '.')));
        $sSrcFile   = DEPLOY_CACHE_DIR . $sBucket . '-' . $sFilename . '-SRC' . $sExtension;

        //  Check filesystem for source file
        if (file_exists($sSrcFile)) {

            //  Yup, it's there, so use it
            return $sSrcFile;

        } else {

            //  Doesn't exist, attempt to fetch from Google Cloud Storage
            try {


                $this->oSdk
                    ->bucket($this->sGSBucket)
                    ->object($sBucket . '/' . $sFilename . $sExtension)
                    ->downloadToFile($sSrcFile);

                return $sSrcFile;

            } catch (\Exception $e) {

                //  Clean up
                if (file_exists($sSrcFile)) {
                    unlink($sSrcFile);
                }

                //  Note the error
                $this->setError('GOOGLE-SDK EXCEPTION [objectLocalPath]: ' . $e->getMessage());
                return false;
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * BUCKET METHODS
     */

    /**
     * Creates a new bucket
     *
     * @param  string $sBucket The bucket's slug
     *
     * @return boolean
     */
    public function bucketCreate($sBucket)
    {
        try {

            if (!$this->objectExists($sBucket, '')) {
                $this->oSdk
                    ->bucket($this->sGSBucket)
                    ->upload(
                        '',
                        [
                            'name'          => $sBucket,
                            'predefinedAcl' => 'publicRead',
                        ]
                    );
            }

            return true;

        } catch (\Exception $e) {
            $this->setError('GOOGLE-SDK EXCEPTION: [bucketCreate]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an existing bucket
     *
     * @param  string $sBucket The bucket's slug
     *
     * @return boolean
     */
    public function bucketDestroy($sBucket)
    {
        //  @todo - consider the implications of bucket deletion; maybe prevent deletion of non-empty buckets
        dumpanddie('@todo');
        try {

            $this->oSdk
                ->bucket($this->sGSBucket)
                ->object($sBucket)
                ->delete();

            return true;

        } catch (\Exception $e) {
            $this->setError('GOOGLE-SDK ERROR: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * URL GENERATOR METHODS
     */

    /**
     * Generate the correct URL for serving a file direct from the file system
     *
     * @param  string $sObject The object to serve
     * @param  string $sBucket The bucket to serve from
     *
     * @return string
     */
    public function urlServeRaw($sObject, $sBucket)
    {
        return $this->urlServe($sObject, $sBucket);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'serve' URLs
     *
     * @param  boolean $bForceDownload Whether or not to force download
     *
     * @return string
     */
    public function urlServeScheme($bForceDownload = false)
    {
        $sUrl = addTrailingSlash($this->sUriServe . '{{bucket}}');

        /**
         * If we're forcing the download we need to reference a slightly different file.
         * On upload two instances were created, the "normal" streaming type one and
         * another with the appropriate content-types set so that the browser downloads
         * as opposed to renders it
         */
        if ($bForceDownload) {
            $sUrl .= '{{filename}}-download{{extension}}';
        } else {
            $sUrl .= '{{filename}}{{extension}}';
        }

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a properly hashed expiring url
     *
     * @param  string  $sBucket        The bucket which the image resides in
     * @param  string  $sObject        The object to be served
     * @param  integer $iExpires       The length of time the URL should be valid for, in seconds
     * @param  boolean $bForceDownload Whether to force a download
     *
     * @return string
     */
    public function urlExpiring($sObject, $sBucket, $iExpires, $bForceDownload = false)
    {
        //  @todo - consider generating a Google expiring/signed URL instead.
        return parent::urlExpiring($sObject, $sBucket, $iExpires, $bForceDownload);
    }
}
