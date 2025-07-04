<?php

namespace Nails\Cdn\Driver;

use Exception;
use Google\Cloud\Storage\StorageClient;
use Nails\Cdn\Exception\DriverException;
use Nails\Common\Exception\EnvironmentException;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Helper\Strings;
use Nails\Common\Service\FileCache;
use Nails\Environment;
use Nails\Factory;
use stdClass;

class Google extends Local
{
    /**
     * The Google Cloud SDK
     */
    protected StorageClient $oSdk;

    /**
     * The Google Storage bucket where items will be stored (not to be confused with internal buckets)
     */
    protected string $sGSBucket = '';

    // --------------------------------------------------------------------------

    /**
     * Returns an instance of the Google Cloud SDK
     *
     * @return StorageClient
     * @throws DriverException
     */
    protected function sdk(): StorageClient
    {
        if (empty($this->oSdk)) {

            $sKeyFile = $this->getSetting('key_file');
            if (is_file($sKeyFile)) {
                $sKey = file_get_contents($sKeyFile);
            } else {
                $sKey = $sKeyFile;
            }
            $aKey = json_decode($sKey, true);

            $this->oSdk = new StorageClient([
                'keyFile' => $aKey,
            ]);

            $this->sGSBucket = $this->getBucket();
        }

        return $this->oSdk;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the Google Storage bucket for this environment
     *
     * @return string
     * @throws DriverException
     */
    protected function getBucket(): string
    {
        if (empty($this->sGSBucket)) {
            $aBuckets = json_decode($this->getSetting('buckets'), true);
            if (empty($aBuckets)) {
                throw new DriverException('Google Storage Buckets have not been defined.');

            } elseif (empty($aBuckets[Environment::get()])) {
                throw new DriverException('No bucket defined for the ' . Environment::get() . ' environment.');

            } else {
                $this->sGSBucket = $aBuckets[Environment::get()];
            }
        }

        return $this->sGSBucket;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the requested URI and replaces {{bucket}} with the Google Storage bucket being used
     *
     * @param string $sUriType The type of URI
     *
     * @throws DriverException
     */
    protected function getUri(string $sUriType): string
    {
        return str_replace('{{bucket}}', $this->getBucket(), $this->getSetting('uri_' . $sUriType));
    }

    // --------------------------------------------------------------------------

    /**
     * OBJECT METHODS
     */

    /**
     * Creates a new object
     *
     * @param stdClass $oData Data to create the object with
     */
    public function objectCreate(stdClass $oData): bool
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

            //  Create a "normal" version
            $this->sdk()
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

            //  Create a "download" version
            $this->sdk()
                ->bucket($this->sGSBucket)
                ->object($sObject)
                ->copy(
                    $this->sGSBucket,
                    [
                        'name'          => $sObjectDl,
                        'predefinedAcl' => 'publicRead',
                    ]
                );

            //  Apply new meta-data to the download version
            $this->sdk()
                ->bucket($this->sGSBucket)
                ->object($sObjectDl)
                ->update(
                    [
                        'contentType'        => 'application/octet-stream',
                        'contentDisposition' => 'attachment; filename="' . str_replace('"', '', $sName) . '" ',
                    ]
                );

            return true;

        } catch (Exception $e) {
            $this->setError('GOOGLE-SDK EXCEPTION [objectCreate]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object exists or not
     *
     * @param string $sFilename The object's filename
     * @param string $sBucket   The bucket's slug
     */
    public function objectExists(string $sFilename, string $sBucket): bool
    {
        try {

            return $this->sdk()
                ->bucket($this->sGSBucket)
                ->object($sBucket . '/' . $sFilename)
                ->exists();

        } catch (Exception $e) {
            $this->setError('GOOGLE-SDK EXCEPTION [objectExists]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Move an object
     *
     * @param string $sSourceObject The source object's filename
     * @param string $sSourceBucket The source bucket's slug
     * @param string $sTargetObject The target object's filename
     * @param string $sTargetBucket The target bucket's slug
     */
    public function objectMove(
        string $sSourceObject,
        string $sSourceBucket,
        string $sTargetObject,
        string $sTargetBucket
    ): bool {
        try {

            throw new Exception('The Google Cloud Storage CDN driver does not support moving objects.');

        } catch (Exception $e) {
            $this->setError('GOOGLE-SDK EXCEPTION [objectMove]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Copy an object
     *
     * @param string $sSourceObject The source object's filename
     * @param string $sSourceBucket The source bucket's slug
     * @param string $sTargetObject The target object's filename
     * @param string $sTargetBucket The target bucket's slug
     */
    public function objectCopy(
        string $sSourceObject,
        string $sSourceBucket,
        string $sTargetObject,
        string $sTargetBucket
    ): bool {
        try {

            throw new Exception('The Google Cloud Storage CDN driver does not support copying objects.');

        } catch (Exception $e) {
            $this->setError('GOOGLE-SDK EXCEPTION [objectCopy]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Destroys (permanently deletes) an object
     *
     * @param string $sObject The object's filename
     * @param string $sBucket The bucket's slug
     */
    public function objectDestroy(string $sObject, string $sBucket): bool
    {
        try {

            $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
            $sExtension = strtolower(substr($sObject, strrpos($sObject, '.')));
            $sObject    = $sBucket . '/' . $sFilename . $sExtension;
            $sObjectDl  = $sBucket . '/' . $sFilename . '-download' . $sExtension;

            //  Delete "normal" version
            $this->sdk()
                ->bucket($this->sGSBucket)
                ->object($sObject)
                ->delete();

            //  Delete "download" version
            $this->sdk()
                ->bucket($this->sGSBucket)
                ->object($sObjectDl)
                ->delete();

            return true;

        } catch (Exception $e) {
            $this->setError('GOOGLE-SDK EXCEPTION [objectDestroy]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object
     *
     * @param string $sBucket   The bucket's slug
     * @param string $sFilename The filename
     *
     * @return bool|string String on success, false on failure
     * @throws FactoryException
     */
    public function objectLocalPath(string $sBucket, string $sFilename): bool|string
    {
        /** @var FileCache $oFileCache */
        $oFileCache = Factory::service('FileCache');

        //  Do we have the original source file?
        $sExtension = strtolower(substr($sFilename, strrpos($sFilename, '.')));
        $sFilename  = strtolower(substr($sFilename, 0, strrpos($sFilename, '.')));
        $sSrcFile   = $oFileCache->getDir() . $sBucket . '-' . $sFilename . '-SRC' . $sExtension;

        //  Check filesystem for a source file
        if (file_exists($sSrcFile)) {
            return $sSrcFile;

        }

        //  Doesn't exist, attempt to fetch from Google Cloud Storage
        try {

            $this->sdk()
                ->bucket($this->sGSBucket)
                ->object($sBucket . '/' . $sFilename . $sExtension)
                ->downloadToFile($sSrcFile);

            return $sSrcFile;

        } catch (Exception $e) {

            //  Clean up
            if (file_exists($sSrcFile)) {
                unlink($sSrcFile);
            }

            //  Note the error
            $this->setError('GOOGLE-SDK EXCEPTION [objectLocalPath]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * BUCKET METHODS
     */

    /**
     * Creates a new bucket
     *
     * @param string $sBucket The bucket's slug
     */
    public function bucketCreate(string $sBucket): bool
    {
        try {

            if (!$this->objectExists($sBucket, '')) {
                $this->sdk()
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

        } catch (Exception $e) {
            $this->setError('GOOGLE-SDK EXCEPTION: [bucketCreate]: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an existing bucket
     *
     * @param string $sBucket The bucket's slug
     */
    public function bucketDestroy(string $sBucket): bool
    {
        try {

            $this->sdk()
                ->bucket($this->sGSBucket)
                ->object($sBucket)
                ->delete();

            return true;

        } catch (Exception $e) {
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
     * @param string $sObject The object to serve
     * @param string $sBucket The bucket to serve from
     */
    public function urlServeRaw(string $sObject, string $sBucket): string
    {
        return $this->urlServe($sObject, $sBucket);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'serve' URLs
     *
     * @param bool $bForceDownload Whether to force download
     *
     * @throws DriverException
     */
    public function urlServeScheme(bool $bForceDownload = false): string
    {
        $sUrl = Strings::addTrailingSlash($this->getUri('serve') . '/{{bucket}}');

        /**
         * If we're forcing the download, we need to reference a slightly different file.
         * On upload two instances were created, the "normal" streaming type one and
         * another with the appropriate Content-Types set so that the browser downloads
         * as opposed to renders it
         */
        if ($bForceDownload) {
            $sUrl .= '{{filename}}-download{{extension}}';
        } else {
            $sUrl .= '{{filename}}{{extension}}';
        }

        return $this->urlMakeSecure($sUrl, false);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a properly hashed expiring url
     *
     * @param string $sBucket        The bucket which the image resides in
     * @param string $sObject        The object to be served
     * @param int    $iExpires       The length of time the URL should be valid for, in seconds
     * @param bool   $bForceDownload Whether to force a download
     *
     * @throws FactoryException
     * @throws EnvironmentException
     */
    public function urlExpiring(string $sObject, string $sBucket, int $iExpires, bool $bForceDownload = false): string
    {
        //  @todo - consider generating a Google expiring/signed URL instead.
        return parent::urlExpiring($sObject, $sBucket, $iExpires, $bForceDownload);
    }
}
