<?php
/*
 * @Description: This class is part of egment/captcha.
 * @Author: Egment
 * @Email egment@163.com
 * @Version v0.2.0
 * @Date: 2020-02-02 21:36:15
 */
namespace Egment;

use Egment\Contracts\Configureable;
use Egment\Contracts\RandomImageAble;
use Egment\Image;
use Egment\Traits\RandomImage;
use Egment\Traits\SlideCaptchaConfigure;
use ErrorException;

class SlideCaptcha implements Configureable, RandomImageAble
{
    use SlideCaptchaConfigure, RandomImage;

    const PART_SIZE = 100;

    const FACTOR_POSITION_RIGHT = 0;
    const FACTOR_POSITION_DOWN = 1;
    const FACTOR_POSITION_LEFT = 2;
    const FACTOR_POSITION_UP = 3;

    const DEFAULT_BG_PATH = __DIR__ . '/../assets/images';
    const DEFAULT_STORE_PATH = './';

    protected $size;

    //Image store path
    protected $storePath;

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
    //part偏移量
    protected $offsetStartX = 0;
    protected $offsetStartY = 0;

    //Max captcha image size.
    protected $maxWidth = 1024;
    protected $maxHeight = 1024;

    //Captcha part properties.
    protected $partRightPoint;
    protected $partDownPoint;
    protected $partMiddlePoint;
    protected $partLeftPoint;
    protected $partUpPoint;

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

    public function __construct(array $options = [], Image $image = null)
    {
        $this->init($options);
        $this->image = $image ?: $this->createRandomImage();
        $this->originImage = new Image($this->image->getPath());
    }

    /**
     * Create slide captcha
     *
     * @param integer $type
     * @param [integer] $size
     * @return array
     */
    public function create($type = 0, $size = null)
    {
        $size = $size ?: $this->getPartSize();
        $master = $this->createMaster($size);
        $part = $this->createPart($size);
        $master_name = @$this->options['master_name'] ?: null;
        $part_name = @$this->options['part_name'] ?: null;
        $common = [
            'master_path' => $master->save($master_name, $this->getStorePath()),
            'part_path' => $part->save($part_name, $this->getStorePath()),
        ];
        return $type == 0 ? $common : $common + [
            'master_base64' => $master->toBase64($common['master_path'], '', 'img'),
            'part_base64' => $part->toBase64($common['part_path'], '', 'img'),
        ];
    }

    public function get($size = null)
    {
        $master = $this->createMaster($size);
        $part = $this->createPart($size);
        $partPath = $part->save(null, $this->getStorePath());
        $masterBase64 = $master->toBase64();
        $partBase64 = $part->toBase64($partPath, '', 'img');
        unlink($partPath);
        return ['masert_base64' => $masterBase64, 'part_base64' => $partBase64];
    }

    /**
     * Create cpatcha master.
     *
     * @param [type] $size
     * @return Image
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
        $this->size = $size = $size ?: $this->getPartSize() ?: floor($this->image->getHeight() / 5);
        if ($this->size > ceil($this->image->getHeight() / 2)) {
            throw new ErrorException("Too large size specifid for this image");
        }
        $factorOffset = $size / 2; //part的一半
        $this->factorRadious = $radious = floor($size / 2.5);
        // part出现范围
        $startX = mt_rand($this->offsetStartX + $radious, $info['width'] - $size - $radious);
        // $startY = mt_rand($this->offsetStartY + $radious, $info['height'] - $size - $radious);
        $startY = intval($info['height'] / 2 - $size / 2); //固定part的高度在master中间
        $this->partSize = $size; //part大小
        // factor圆心
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
        //factor索引
        $this->factorPointIndex = mt_rand(0, count($points) - 1);
        //factor开始点【圆心】
        $this->factorStartPoint = $points[$this->factorPointIndex];

        //reactangle points
        $this->rectangleStartPoint = [$startX, $startY];
        $this->rectangleXPoint = [$startX + $size, $startY];
        $this->rectangleYPoint = [$startX, $startY + $size];
        $this->rectangleXYPoint = [$startX + $size, $startY + $size];
        //part points
        $this->partRightPoint = $this->factorPointIndex == 0 ? [$points[0][0] + $radious, $points[0][1]] : [$this->rectangleXPoint[0], $this->rectangleXPoint[1] + $factorOffset];
        $this->partDownPoint = $this->factorPointIndex == 1 ? [$points[1][0], $points[1][1] + $radious] : [$this->rectangleYPoint[0] + $factorOffset, $this->rectangleYPoint[1]];
        $this->partMiddlePoint = [$startX + $size / 2, $startY + $size / 2];
        $this->partLeftPoint = $this->factorPointIndex == 2 ? [$points[2][0] - $radious, $points[2][1]] : [$startX, $startY + $factorOffset];
        $this->partUpPoint = $this->factorPointIndex == 3 ? [$points[3][0], $points[3][1] - $radious] : [$startX + $radious, $startY];

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

        //left
        $this->rectPivotLeftStart = [$startX, $points[self::FACTOR_POSITION_LEFT][1] + $radiousOffset];
        $this->rectPivotLeftEnd = [$startX, $points[self::FACTOR_POSITION_LEFT][1] - $radiousOffset];

        $color = [255, 255, 255];
        $this->image->drawRectangle($this->rectangleStartPoint, $size, $size, $color);
        $this->image->drawEllipse($this->factorStartPoint, $radious, $radious, $color);
        $this->masterCreated = true;
        return $this->image;
    }

    public function getMaster()
    {
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

    public function getPart()
    {
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

    public function setPartSize($partSize)
    {
        $this->partSize = $partSize;
    }

    public function getPartSize()
    {
        return $this->partSize;
    }

    public function setStorePath(string $path = '')
    {
        $this->storePath = realpath($path) ?: realpath(self::DEFAULT_STORE_PATH);
    }

    public function getStorePath()
    {
        return $this->storePath;
    }

    /**
     * 获取part的各个坐标点
     *
     * @return array
     */
    public function getPartPoints()
    {
        return [
            'right' => $this->partRightPoint,
            'down' => $this->partDownPoint,
            'middle' => $this->partMiddlePoint,
            'left' => $this->partLeftPoint,
            'up' => $this->partUpPoint,
        ];
    }

    /**
     * 获取part rectangele points。
     *
     * @return array
     */
    public function getRectanglePoints()
    {
        return [
            'o' => $this->rectangleStartPoint,
            'x' => $this->rectangleXPoint,
            'y' => $this->rectangleYPoint,
            'xy' => $this->rectangleXYPoint,
        ];
    }
    /**
     * 获取part宽度
     *
     * @return int
     */
    public function getPartWidth()
    {
        return $this->partRightPoint[0] - $this->partLeftPoint[0];
    }

    /**
     * 获取part高度
     *
     * @return int
     */
    public function getPartHeight()
    {
        return $this->partDownPoint[1] - $this->partUpPoint[1];
    }

}
