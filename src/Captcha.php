<?php
/*
 * @Description: This class is part of egment/captcha.
 * @Author: Egment
 * @Email egment@163.com
 * @Date: 2020-02-02 21:36:15
 */
namespace Egment;

use ErrorException;

class SlideCaptcha
{
    //拼图部分大小
    const PART_SIZE = 100;

    //master是否创建
    protected $masterCreated = false;
    //part是否创建
    protected $partCreated = false;

    protected $image;
    protected $master;
    //master蒙版
    protected $masterMask;
    //captcha part
    protected $part;
    //slider captcha大小
    protected $partSize;
    //slider part 半径大小
    protected $partRadious;
    //origin image resouce.
    protected $originImage;

    protected $offsetStartX = 0;
    protected $offsetStartY = 0;

    //Max captcha image size.
    protected $maxWidth = 1024;
    protected $maxHeight = 1024;

    //Captcha part properties.
    protected $partMiddlePoint;
    protected $partLeftPoint;

    protected $slideGap = 10;

    public $partPoints = [];
    //矩形起点
    public $rectangleStartPoint = [];
    //因子起点
    public $ellipseStartPoint;

    public function __construct(Image $image)
    {
        $this->image = $image;
        $this->originImage = new Image($this->image->getPath());
    }

    /**
     * Create cpatcha master.
     *
     * @param [type] $size
     * @return void
     */
    public function createMaster($size = null)
    {
        if (!$this->image && !$this->image->getPath()) {
            throw new ErrorException("No valid image resources");
        }
        $info = $this->image->getInfo();
        if ($info['width'] > $this->maxWidth || $info['height'] > $this->maxHeight) {
            throw new ErrorException("Image size exceeds maximum.");
        }
        $size = $size ?: floor($this->image->getHeight() / 5);
        //截图部分凸起因子偏移
        $factorOffset = $size / 2;
        $this->partRadious = $radious = floor($size / 2.5);
        $startX = mt_rand($this->offsetStartX + $radious, $info['width'] - $size - $radious);
        $startY = mt_rand($this->offsetStartY + $radious, $info['height'] - $size - $radious);
        $this->partSize = $size;

        $this->partPoints = $points = [
            [$startX + $factorOffset, $startY],
            [$startX, $startY + $factorOffset],
            [$startX + $size, $startY + $factorOffset],
            [$startX + $factorOffset, $startY + $size],
        ];
        $this->rectangleStartPoint = [$startX, $startY];
        $ellipseStartPointIndex = mt_rand(0, count($points) - 1);
        $this->ellipseStartPoint = $points[$ellipseStartPointIndex];

        $this->partMiddlePoint = [$startX + $size / 2, $startY + $size / 2];
        $this->partLeftPoint = $ellipseStartPointIndex == 1 ? [$points[1][0] - $radious, $points[1][1]] : [$startX, $startY + $size / 2];

        $color = [255, 255, 255];
        $this->image->drawRectangle($this->rectangleStartPoint, $size, $size, $color);
        $this->image->drawEllipse($this->ellipseStartPoint, $radious, $radious, $color);
        $this->masterCreated = true;
        return $this->image;
    }

    /**
     * Create captcha part image resouce.
     *
     * @return void
     */
    public function createPart()
    {
        $width = $this->image->getWidth();
        $height = $this->image->getHeight();
        //master mask im
        $im = $this->image->bareCreate($this->image->getWidth(), $this->image->getHeight());
        //part im
        $partIm = $this->image->bareCreate($this->image->getWidth(), $this->image->getHeight());
        //creates master mask
        $white = [255, 255, 255];
        $masterMask = new Image($im);
        $masterMask->fillTransparent();
        $masterMask->drawRectangle($this->rectangleStartPoint, $this->partSize, $this->partSize, $white);
        $masterMask->drawEllipse($this->ellipseStartPoint, $this->partRadious, $this->partRadious, $white);
        $this->masterMask = $masterMask;
        // $this->originImage->save();
        $this->part = new Image($partIm);
        for ($i = 0; $i < $width; $i++) {
            for ($j = 0; $j < $height; $j++) {
                //value of master mask.
                $rgb = imagecolorat($this->masterMask->getIm(), $i, $j);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r + $g + $b == 255 * 3) {
                    $color = imagecolorat($this->originImage->getIm(), $i, $j);
                    imagesetpixel($this->part->getIm(), $i, $j, $color);
                }
            }
        }
        $alpha = imagecolorallocatealpha($this->part->getIm(), 0, 0, 0, 127);
        imagecolortransparent($this->part->getIm(), $alpha);
        $this->partCreated = true;
        return $this->part;
    }

    /**
     * Auth function
     *
     * @param [type] $unauth
     * @param \Closure $fn
     * @return mixed
     */
    public function auth($unauth, \Closure $fn)
    {
        return $fn($unauth, $this);
    }

    /**
     * Authenticate input parameters position.
     *
     * @param [type] $benchmark
     * @return void
     */
    public function authSlidePosition($input, $benchmark)
    {
        if ($input < ($benchmark - $this->slideGap) || $input > ($benchmark + $this->slideGap)) {
            return false;
        }
        return true;
    }

    /**
     * Set slide gap
     *
     * @return void
     */
    public function setSlideGap($value)
    {
        $this->slideGap = $value;
    }

    /**
     * Set slide gap
     *
     * @return int
     */
    public function getSlideGap()
    {
        return $this->slideGap;
    }
    /**
     * Set captcha max width.
     *
     * @param integer $width
     * @return void
     */
    public function setMaxWidth(int $width)
    {
        $this->maxWidth = $width;
    }

    /**
     * Set captcha max height.
     *
     * @param integer $height
     * @return void
     */
    public function setMaxHeight(int $height)
    {
        $this->maxHeight = $height;
    }

    /**
     * Get captcha max width.
     *
     * @return void
     */
    public function getMaxWidth()
    {
        return $this->maxWidth;
    }

    /**
     * Get captcha max height.
     *
     * @return void
     */
    public function getMaxHeight()
    {
        return $this->maxHeight;
    }

    /**
     * Get captcha part middle point.
     *
     * @return array
     */
    public function getPartMiddlePoint()
    {
        return $this->partMiddlePoint;
    }

    /**
     * Get captcha part left point.
     *
     * @return array
     */
    public function getPartLeftPoint()
    {
        return $this->partLeftPoint;
    }
}
