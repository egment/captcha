<?php

namespace Egment\Traits;

use Egment\Image;
use ErrorException;

trait RandomImage
{
    //Whether scan the background images path
    protected $bgScaned = false;
    //Background image files
    protected $bgFiles = [];
    //Background path
    protected $bgPath;
    //Scan background extension
    protected $scanBgExtension = [];

    public function createRandomImage(\Closure $closure = null)
    {
        $bgFiles = true === $this->bgScaned ? $this->bgFiles : $this->scanBgPath();
        if (($amounts = count($bgFiles)) > 0) {
            return $closure ?
            $closure($this->getBgPath()) :
            new Image($bgFiles[mt_rand(0, $amounts - 1)]);
        }
        throw new ErrorException("Do not have any images at background image path ");
    }

    public function scanBgPath()
    {
        $scanBgExtension = $this->scanBgExtension ?: ['jpg', 'jpeg', 'bmp', 'png'];
        $prefix = $bgPath = $this->getBgPath();
        $this->bgFiles = arrayFilter(shallowScanDir($bgPath, $prefix), $scanBgExtension, function ($file) {
            if (false !== $position = strrpos($file, '.')) {
                return substr($file, $position + 1);
            }
        });
        $this->bgScaned = true;
        return $this->bgFiles;
    }

    public function setBgPath(string $path = '')
    {
        $this->bgPath = $path ? realpath($path) : realpath(self::DEFAULT_BG_PATH);
    }

    //get path
    public function getBgPath()
    {
        return $this->bgPath ?: realpath(self::DEFAULT_BG_PATH);
    }

    public function getBgFiles()
    {
        return $this->bgFiles;
    }

    public function setScanBgExtension(array $extension)
    {
        $this->scanBgExtension = $extension;
    }

    public function getScanBgExtension()
    {
        return $this->scanBgExtension;
    }

}
