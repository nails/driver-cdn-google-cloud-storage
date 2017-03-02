<?php

namespace Nails\Cdn\Driver;

use Google\Cloud\Storage\StorageClient;
use Nails\Cdn\Exception\DriverException;
use Nails\Cdn\Interfaces\Driver;
use Nails\Common\Traits\ErrorHandling;
use Nails\Environment;

class Google implements Driver
{
    use ErrorHandling;

    // --------------------------------------------------------------------------

    /**
     * The Google Cloud SDK
     * @var StorageClient
     */
    protected $oSdk;

    /**
     * The Google Storage bucket where items will be stored (not to be confused with internal buckets)
     * @var string
     */
    protected $sBucket;

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

        if (is_file(APP_CDN_DRIVER_GOOGLE_KEY_FILE)) {
            $sKey = file_get_contents(APP_CDN_DRIVER_GOOGLE_KEY_FILE);
        } else {
            $sKey = APP_CDN_DRIVER_GOOGLE_KEY_FILE;
        }
        $aKey = json_decode($sKey, true);

        //  Instantiate the SDK
        $this->oSdk = new StorageClient([
            'keyFile' => $aKey,
        ]);

        //  Set the bucket we're using
        $this->sBucket = constant('APP_CDN_DRIVER_GOOGLE_BUCKET_' . Environment::get());

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
                    $this->{$sProp} = str_replace('{{bucket}}', $this->sBucket, addTrailingSlash(constant($sConst)));
                } else {
                    $this->{$sProp} = str_replace('{{bucket}}', $this->sBucket, addTrailingSlash($sDefault));
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
     * @param  \stdClass $oData Data to create the object with
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
                ->bucket($this->sBucket)
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
                ->bucket($this->sBucket)
                ->object($sObject)
                ->copy(
                    $this->sBucket,
                    [
                        'name'          => $sObjectDl,
                        'predefinedAcl' => 'publicRead',
                    ]
                );

            //  Apply new meta data to download version
            $this->oSdk
                ->bucket($this->sBucket)
                ->object($sObjectDl)
                ->update(
                    [
                        'contentType'        => 'application/octet-stream',
                        'contentDisposition' => 'attachment; filename="' . str_replace('"', '', $sName) . '" ',
                    ]
                );

            return true;

        } catch (\Exception $e) {

            $this->setError('GOOGLE-SDK EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage());

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object exists or not
     * @param  string $sFilename The object's filename
     * @param  string $sBucket The bucket's slug
     * @return boolean
     */
    public function objectExists($sFilename, $sBucket)
    {
        return $this->oSdk
            ->bucket($this->sBucket)
            ->object($sBucket . '/' . $sFilename)
            ->exists();
    }

    // --------------------------------------------------------------------------

    /**
     * Destroys (permanently deletes) an object
     * @param  string $sObject The object's filename
     * @param  string $sBucket The bucket's slug
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
                ->bucket($this->sBucket)
                ->object($sObject)
                ->delete();

            //  Delete "download" version
            $this->oSdk
                ->bucket($this->sBucket)
                ->object($sObjectDl)
                ->delete();

            return true;

        } catch (\Exception $e) {

            $this->setError('GOOGLE-SDK EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage());

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object
     * @param  string $sBucket The bucket's slug
     * @param  string $sFilename The filename
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
                    ->bucket($this->sBucket)
                    ->object($sBucket . '/' . $sFilename . $sExtension)
                    ->downloadToFile($sSrcFile);

                return $sSrcFile;

            } catch (\Exception $e) {

                //  Clean up
                if (file_exists($sSrcFile)) {
                    unlink($sSrcFile);
                }

                //  Note the error
                $this->setError('GOOGLE-SDK EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage());

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
     * @param  string $sBucket The bucket's slug
     * @return boolean
     */
    public function bucketCreate($sBucket)
    {
        try {

            if (!$this->objectExists($sBucket, '')) {
                $this->oSdk
                    ->bucket($this->sBucket)
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

            $this->setError('GOOGLE-SDK ERROR: ' . $e->getMessage());

            return false;

        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an existing bucket
     * @param  string $sBucket The bucket's slug
     * @return boolean
     */
    public function bucketDestroy($sBucket)
    {
        //  @todo - consider the implications of bucket deletion; maybe prevent deletion of non-empty buckets
        dumpanddie('@todo');
        try {

            $this->oSdk
                ->bucket($this->sBucket)
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
     * Generates the correct URL for serving a file
     * @param  string $sObject The object to serve
     * @param  string $sBucket The bucket to serve from
     * @param  boolean $bForceDownload Whether to force a download
     * @return string
     */
    public function urlServe($sObject, $sBucket, $bForceDownload = false)
    {
        $sUrl       = $this->urlServeScheme($bForceDownload);
        $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
        $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));

        //  Sub in the values
        $sUrl = str_replace('{{bucket}}', $sBucket, $sUrl);
        $sUrl = str_replace('{{filename}}', $sFilename, $sUrl);
        $sUrl = str_replace('{{extension}}', $sExtension, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the correct URL for serving a file direct from the file system
     * @param  string $sObject The object to serve
     * @param  string $sBucket The bucket to serve from
     * @return string
     */
    public function urlServeRaw($sObject, $sBucket)
    {
        return $this->urlServe($sObject, $sBucket);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'serve' URLs
     * @param  boolean $bForceDownload Whether or not to force download
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
     * Generates a URL for serving zipped objects
     * @param  string $sObjectIds A comma separated list of object IDs
     * @param  string $sHash The security hash
     * @param  string $sFilename The filename to give the zip file
     * @return string
     */
    public function urlServeZipped($sObjectIds, $sHash, $sFilename)
    {
        $sUrl = $this->urlServeZippedScheme();

        //  Sub in the values
        $sUrl = str_replace('{{ids}}', $sObjectIds, $sUrl);
        $sUrl = str_replace('{{hash}}', $sHash, $sUrl);
        $sUrl = str_replace('{{filename}}', urlencode($sFilename), $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'zipped' urls
     * @return  string
     */
    public function urlServeZippedScheme()
    {
        return $this->urlMakeSecure(
            addTrailingSlash(
                $this->sUriProcess . 'zip/{{ids}}/{{hash}}/{{filename}}'
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the crop utility
     * @param   string $sBucket The bucket which the image resides in
     * @param   string $sObject The filename of the image we're cropping
     * @param   integer $iWidth The width of the cropped image
     * @param   integer $iHeight The height of the cropped image
     * @return  string
     */
    public function urlCrop($sObject, $sBucket, $iWidth, $iHeight)
    {
        $sUrl       = $this->urlCropScheme();
        $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
        $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));

        //  Sub in the values
        $sUrl = str_replace('{{width}}', $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', $iHeight, $sUrl);
        $sUrl = str_replace('{{bucket}}', $sBucket, $sUrl);
        $sUrl = str_replace('{{filename}}', $sFilename, $sUrl);
        $sUrl = str_replace('{{extension}}', $sExtension, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'crop' urls
     * @return  string
     */
    public function urlCropScheme()
    {
        return $this->urlMakeSecure(
            addTrailingSlash(
                $this->sUriProcess . 'crop/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}'
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the scale utility
     * @param   string $sBucket The bucket which the image resides in
     * @param   string $sObject The filename of the image we're 'scaling'
     * @param   integer $iWidth The width of the scaled image
     * @param   integer $iHeight The height of the scaled image
     * @return  string
     */
    public function urlScale($sObject, $sBucket, $iWidth, $iHeight)
    {
        $sUrl       = $this->urlScaleScheme();
        $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
        $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));

        //  Sub in the values
        $sUrl = str_replace('{{width}}', $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', $iHeight, $sUrl);
        $sUrl = str_replace('{{bucket}}', $sBucket, $sUrl);
        $sUrl = str_replace('{{filename}}', $sFilename, $sUrl);
        $sUrl = str_replace('{{extension}}', $sExtension, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'scale' urls
     * @return  string
     */
    public function urlScaleScheme()
    {
        return $this->urlMakeSecure(
            addTrailingSlash(
                $this->sUriProcess . 'scale/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}'
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the placeholder utility
     * @param   integer $iWidth The width of the placeholder
     * @param   integer $iHeight The height of the placeholder
     * @param   integer $iBorder The width of the border round the placeholder
     * @return  string
     */
    public function urlPlaceholder($iWidth, $iHeight, $iBorder = 0)
    {
        $sUrl = $this->urlPlaceholderScheme();

        //  Sub in the values
        $sUrl = str_replace('{{width}}', $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', $iHeight, $sUrl);
        $sUrl = str_replace('{{border}}', $iBorder, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'placeholder' urls
     * @return  string
     */
    public function urlPlaceholderScheme()
    {
        return $this->urlMakeSecure(
            addTrailingSlash(
                $this->sUriProcess . 'placeholder/{{width}}/{{height}}/{{border}}'
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for a blank avatar
     * @param  integer $iWidth The width fo the avatar
     * @param  integer $iHeight The height of the avatar§
     * @param  string|integer $mSex What gender the avatar should represent
     * @return string
     */
    public function urlBlankAvatar($iWidth, $iHeight, $mSex = '')
    {
        $sUrl = $this->urlBlankAvatarScheme();

        //  Sub in the values
        $sUrl = str_replace('{{width}}', $iWidth, $sUrl);
        $sUrl = str_replace('{{height}}', $iHeight, $sUrl);
        $sUrl = str_replace('{{sex}}', $mSex, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'blank_avatar' urls
     * @return  string
     */
    public function urlBlankAvatarScheme()
    {
        return $this->urlMakeSecure(
            addTrailingSlash(
                $this->sUriProcess . 'blank_avatar/{{width}}/{{height}}/{{sex}}'
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a properly hashed expiring url
     * @param  string $sBucket The bucket which the image resides in
     * @param  string $sObject The object to be served
     * @param  integer $iExpires The length of time the URL should be valid for, in seconds
     * @param  boolean $bForceDownload Whether to force a download
     * @return string
     */
    public function urlExpiring($sObject, $sBucket, $iExpires, $bForceDownload = false)
    {
        $sUrl = $this->urlExpiringScheme();

        //  Hash the expiry time
        $sToken = $sBucket . '|' . $sObject . '|' . $iExpires . '|' . time() . '|';
        $sToken .= md5(time() . $sBucket . $sObject . $iExpires . APP_PRIVATE_KEY);
        $sToken = get_instance()->encrypt->encode($sToken, APP_PRIVATE_KEY);
        $sToken = urlencode($sToken);

        //  Sub in the values
        $sUrl = str_replace('{{token}}', $sToken, $sUrl);
        $sUrl = str_replace('{{download}}', $bForceDownload ? 1 : 0, $sUrl);

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'expiring' urls
     * @return  string
     */
    public function urlExpiringScheme()
    {
        return $this->urlMakeSecure(
            site_url('serve?token={{token}}&dl={{download}}')
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a URL and makes it secure if needed
     * @param  string $sUrl The URL to secure
     * @param  boolean $bIsProcessing Whether it's a processing type URL
     * @return string
     */
    protected function urlMakeSecure($sUrl, $bIsProcessing = true)
    {
        if (isPageSecure()) {
            if ($bIsProcessing) {
                $sSearch  = $this->sUriProcess;
                $sReplace = $this->sUriProcessSecure;
            } else {
                $sSearch  = $this->sUriServe;
                $sReplace = $this->sUriServeSecure;
            }
            $sUrl = str_replace($sSearch, $sReplace, $sUrl);
        }

        return $sUrl;
    }
}
