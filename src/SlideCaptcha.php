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

    const FACTOR_POSITION_RIGHT = 0;
    const FACTOR_POSITION_DOWN = 1;
    const FACTOR_POSITION_LEFT = 2;
    const FACTOR_POSITION_UP = 3;

    protected $size;

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
    protected $factorRadious;
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

    public $factorPoints = [];

    //Captcha reactangle start properties
    protected $rectangleStartPoint = [];
    protected $rectangleXPoint = [];
    protected $rectangleYPoint = [];
    protected $rectangleXYPoint = [];

    //reactange pivot point
    //Up
    protected $rectPivotUpStart = [];
    protected $rectPivotUpEnd = [];
    //Right
    protected $rectPivotRightStart = [];
    protected $rectPivotRightEnd = [];
    //Down
    protected $rectPivotDownStart = [];
    protected $rectPivotDownEnd = [];
    //Left
    protected $rectPivotLeftStart = [];
    protected $rectPivotLeftEnd = [];

    //Captcha factor ellipese properties
    protected $factorStartPoint = [];
    protected $factorPointIndex;

    public static $factorDrawArcMap = [
        self::FACTOR_POSITION_RIGHT => [270, 90],
        self::FACTOR_POSITION_DOWN => [0, 180],
        self::FACTOR_POSITION_LEFT => [90, 270],
        self::FACTOR_POSITION_UP => [180, 0],
    ];

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
        $this->size = $size = $size ?: floor($this->image->getHeight() / 5);
        //截图部分凸起因子偏移
        $factorOffset = $size / 2;
        $this->factorRadious = $radious = floor($size / 2.5);
        $startX = mt_rand($this->offsetStartX + $radious, $info['width'] - $size - $radious);
        $startY = mt_rand($this->offsetStartY + $radious, $info['height'] - $size - $radious);
        $this->partSize = $size;

        $this->factorPoints = $points = [
            //Right
            [$startX + $size, $startY + $factorOffset],
            //Down
            [$startX + $factorOffset, $startY + $size],
            //Lelt
            [$startX, $startY + $factorOffset],
            //Up
            [$startX + $factorOffset, $startY],
        ];
        //factor points
        $this->factorPointIndex = mt_rand(0, count($points) - 1);
        $this->factorStartPoint = $points[$this->factorPointIndex];

        //reactangle points
        $this->rectangleStartPoint = [$startX, $startY];
        $this->rectangleXPoint = [$startX + $size, $startY];
        $this->rectangleYPoint = [$startX, $startY + $size];
        $this->rectangleXYPoint = [$startX + $size, $startY + $size];

        $radiousOffset = $radious / 2;

        //reactangele pivot points
        //up
        $this->rectPivotUpStart = [$points[self::FACTOR_POSITION_UP][0] - $radiousOffset, $startY];
        $this->rectPivotUpEnd = [$points[self::FACTOR_POSITION_UP][0] + $radiousOffset, $startY];

        //right
        $this->rectPivotRightStart = [$startX + $size, $points[self::FACTOR_POSITION_RIGHT][1] - $radiousOffset];
        $this->rectPivotRightEnd = [$startX + $size, $points[self::FACTOR_POSITION_RIGHT][1] + $radiousOffset];

        //down
        $this->rectPivotDownStart = [$points[self::FACTOR_POSITION_DOWN][0] + $radiousOffset, $startY + $size];
        $this->rectPivotDownEnd = [$points[self::FACTOR_POSITION_DOWN][0] - $radiousOffset, $startY + $size];
        // dd($points[self::FACTOR_POSITION_DOWN][0]);

        //left
        $this->rectPivotLeftStart = [$startX, $points[self::FACTOR_POSITION_LEFT][1] + $radiousOffset];
        $this->rectPivotLeftEnd = [$startX, $points[self::FACTOR_POSITION_LEFT][1] - $radiousOffset];

        $this->partMiddlePoint = [$startX + $size / 2, $startY + $size / 2];
        $this->partLeftPoint = $this->factorPointIndex == 1 ? [$points[1][0] - $radious, $points[1][1]] : [$startX, $startY + $size / 2];

        $color = [255, 255, 255];
        $this->image->drawRectangle($this->rectangleStartPoint, $size, $size, $color);
        $this->image->drawEllipse($this->factorStartPoint, $radious, $radious, $color);
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
        $masterMask->drawEllipse($this->factorStartPoint, $this->factorRadious, $this->factorRadious, $white);
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
        $this->drawPartFrame();
        $this->cutPart();
        $alpha = imagecolorallocatealpha($this->part->getIm(), 0, 0, 0, 127);
        imagecolortransparent($this->part->getIm(), $alpha);
        $this->partCreated = true;
        return $this->part;
    }

    /**
     * Draw a frame
     *
     * @param array $color
     * @param integer $alpha
     * @return void
     */
    protected function drawPartFrame(array $color = [], int $alpha = 0)
    {
        $this->drawFactorArc($color, $alpha);
        $this->drawPartUpLine($color, $alpha);
        $this->drawPartRightLine($color, $alpha);
        $this->drawPartDownLine($color, $alpha);
        $this->drawPartLeftLine($color, $alpha);
    }

    /**
     * Cut appropriate part size.
     *
     * @param integer $offset
     * @return void
     */
    public function cutPart($offset = 1)
    {
        $x = $this->rectangleStartPoint[0];
        $y = $this->rectangleStartPoint[1];
        $width = $height = $this->size;
        $radious = ceil($this->factorRadious / 2);
        if ($this->factorPointIndex == self::FACTOR_POSITION_UP) {
            $startPoint = [$x, $y - $radious];
            $height = $height + $radious;
        } else if ($this->factorPointIndex == self::FACTOR_POSITION_RIGHT) {
            $startPoint = [$x, $y];
            $width = $width + $radious;
        } else if ($this->factorPointIndex == self::FACTOR_POSITION_DOWN) {
            $startPoint = [$x, $y];
            $height = $height + $radious;
        } else {
            $startPoint = [$x - $radious, $y];
            $width = $width + $radious;
        }
        $this->part->cutRectangle($startPoint, $width + $offset, $height + $offset);
    }

    /**
     * Draw factor arc
     *
     * @param array $color
     * @return Image
     */
    protected function drawFactorArc(array $color = [], int $alpha = 0)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        $degress = self::$factorDrawArcMap[$this->factorPointIndex];
        $this->part->drawArc($this->factorStartPoint, $this->factorRadious, $this->factorRadious, $degress[0], $degress[1], $color, $alpha);
        return $this->part;
    }

    /**
     * Draw part up line.
     *
     * @param array $color
     * @return bool
     */
    protected function drawPartUpLine(array $color = [], int $alpha = 0)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        if ($this->factorPointIndex == self::FACTOR_POSITION_UP) {
            $resStart = $this->part->drawLine($this->rectangleStartPoint, $this->rectPivotUpStart, $color, $alpha);
            $resEnd = $this->part->drawLine($this->rectPivotUpEnd, $this->rectangleXPoint, $color, $alpha);
            return $resStart && $resEnd ? true : false;
        }
        return $this->part->drawLine($this->rectangleStartPoint, $this->rectangleXPoint, $color, $alpha);
    }

    protected function drawPartRightLine(array $color = [], int $alpha = 0)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        if ($this->factorPointIndex == self::FACTOR_POSITION_RIGHT) {
            $resStart = $this->part->drawLine($this->rectangleXPoint, $this->rectPivotRightStart, $color, $alpha);
            $resEnd = $this->part->drawLine($this->rectPivotRightEnd, $this->rectangleXYPoint, $color, $alpha);
            return $resStart && $resEnd ? true : false;
        }
        return $this->part->drawLine($this->rectangleXPoint, $this->rectangleXYPoint, $color, $alpha);
    }

    protected function drawPartDownLine(array $color = [], int $alpha = 0)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        if ($this->factorPointIndex == self::FACTOR_POSITION_DOWN) {
            $resStart = $this->part->drawLine($this->rectangleXYPoint, $this->rectPivotDownStart, $color, $alpha);
            $resEnd = $this->part->drawLine($this->rectPivotDownEnd, $this->rectangleYPoint, $color, $alpha);
            return $resStart && $resEnd ? true : false;
        }
        return $this->part->drawLine($this->rectangleXYPoint, $this->rectangleYPoint, $color, $alpha);
    }

    protected function drawPartLeftLine(array $color = [], int $alpha = 0)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        if ($this->factorPointIndex == self::FACTOR_POSITION_LEFT) {
            $resStart = $this->part->drawLine($this->rectangleYPoint, $this->rectPivotLeftStart, $color, $alpha);
            $resEnd = $this->part->drawLine($this->rectPivotLeftEnd, $this->rectangleStartPoint, $color, $alpha);
            return $resStart && $resEnd ? true : false;
        }
        return $this->part->drawLine($this->rectangleYPoint, $this->rectangleStartPoint, $color, $alpha);
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
     * @param [mixed] $benchmark
     * @return bool
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
