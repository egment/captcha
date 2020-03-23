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

    const DEFAULT_PART_FRAME_COLOR = [255, 255, 255];
    const DEFAULT_MASTER_FRAME_COLOR = [255, 255, 255];

    const REAL_PART_THICK = 1;

    const DEFAULT_MASTER_ALPHA = 20;

    const DEFAULT_MASTER_COlOR = [0, 0, 0];

    //Image store path
    protected $storePath;

    //master是否创建
    protected $masterCreated = false;
    //part是否创建
    protected $partCreated = false;

    protected $image;
    protected $imageInfo;
    protected $master;
    //master蒙版
    protected $masterMask;

    //captcha part
    protected $part;
    //slider part大小
    protected $partSize;

    //factor相对于part的偏移
    protected $factorOffset;
    //factor半径
    protected $factorDiameter;
    //origin image resouce.
    protected $originImage;
    //part偏移量
    protected $offsetStartX = 0;
    protected $offsetStartY = 0;

    //Max captcha image partSize.
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

    protected $part_thick = 1;

    protected $options;

    public static $factorDrawArcMap = [
        self::FACTOR_POSITION_RIGHT => [270, 90],
        self::FACTOR_POSITION_DOWN => [0, 180],
        self::FACTOR_POSITION_LEFT => [90, 270],
        self::FACTOR_POSITION_UP => [180, 0],
    ];

    public function __construct(array $options = [], Image $image = null)
    {
        $this->init($options);
        $this->imageInfo = $this->image->getInfo();
        $this->originImage = new Image($this->image->getPath());
    }

    public function init(array $options = [])
    {
        $this->options = $options;
        $this->setStorePath(@$options['store_path'] ?: "");
        $this->setBgPath(@$options['bg_path'] ?: "");
        if (isset($options['bg_exts']) && is_array($options['bg_exts'])) {
            $this->setScanBgExtension($options['bg_exts']);
        }
        $this->image = $this->createRandomImage();
        $this->setPartSize(@$options['part_size'] ?: $this->getDefaultPartSize());
    }

    /**
     * Create slide captcha
     *
     * @param integer $type
     * @param [integer] $partSize
     * @return array
     */
    public function create($type = 0, $partSize = null)
    {
        $partSize = $partSize ?: $this->getPartSize();
        $master = $this->createMaster($partSize);
        $part = $this->createPart($partSize);
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

    public function get($partSize = null)
    {
        $partSize = $partSize ?: $this->getPartSize();
        $master = $this->createMaster($partSize);
        $part = $this->createPart($partSize);
        $masterPath = $master->save(null, $this->getStorePath());
        $partPath = $part->save(null, $this->getStorePath());
        $masterBase64 = $master->toBase64($masterPath, '', 'img');
        $partBase64 = $part->toBase64($partPath, '', 'img');
        unlink($masterPath);
        unlink($partPath);
        return ['master_base64' => $masterBase64, 'part_base64' => $partBase64];
    }

    /**
     * Create cpatcha master.
     *
     * @param [type] $partSize
     * @return Image
     */
    public function createMaster($partSize = null)
    {
        $this->_masterCheck($partSize);
        $this->factorOffset = $partSize / 2; //part的一半
        $this->factorDiameter = floor($partSize / 2.5);
        [$partX, $partY] = $this->getPartStartPoint();
        // factor圆心
        $this->factorPoints = [
            //Right
            [$partX + $partSize, $partY + $this->factorOffset],
            //Down
            [$partX + $this->factorOffset, $partY + $partSize],
            //Lelt
            [$partX, $partY + $this->factorOffset],
            //Up
            [$partX + $this->factorOffset, $partY],
        ];
        $this->factorPointIndex = mt_rand(0, count($this->factorPoints) - 1); //factor索引
        $this->factorStartPoint = ($this->factorPoints)[$this->factorPointIndex]; //factor开始点【圆心】
        $this->setRectanglePoints($partX, $partY);
        $this->setPartPoints($partX, $partY);
        $this->setPivotPoints($partX, $partY);
        $color = @$this->options['master_color'] ?: self::DEFAULT_MASTER_COlOR;
        $alpha = @$this->options['master_alpha'] ?: self::DEFAULT_MASTER_ALPHA;
        $this->drawMasterRectangle($color, $alpha);
        $this->drawMasterFilledArc($color, $alpha);
        $this->drawMasterFrame();
        $this->masterCreated = true;
        return $this->image;
    }

    private function _masterCheck($partSize)
    {
        //检查图片资源
        if (!$this->image && !$this->image->getPath()) {
            throw new ErrorException("No valid image resources");
        }
        //背景图不能超过最呆尺寸
        if ($this->imageInfo['width'] > $this->maxWidth || $this->imageInfo['height'] > $this->maxHeight) {
            throw new ErrorException("Image partSize exceeds maximum.");
        }
        $this->partSize = $partSize = $partSize ?: $this->getPartSize() ?: $this->getDefaultPartSize();
        //part大小不能大于master高度的一半
        if ($this->partSize > ceil($this->image->getHeight() / 2)) {
            throw new ErrorException("Too large size specifid for this image");
        }
    }

    //获取默认part大小
    public function getDefaultPartSize(\Closure $closure = null)
    {
        $defaultPart = floor($this->image->getHeight() / 5);
        if (is_callable($closure)) {
            return $closure($defaultPart);
        }
        return $defaultPart;
    }

    // 获取part开始坐标
    public function getPartStartPoint()
    {
        $gap = 50;
        $partX = mt_rand($this->factorOffset + $this->factorDiameter + $gap, $this->imageInfo['width'] - ($this->partSize + $this->factorDiameter + $gap));
        $partY = mt_rand(10, $this->imageInfo['height'] - $this->partSize - $this->factorDiameter);
        // $partY = mt_rand($this->factorOffset + $this->factorDiameter + $gap, $this->imageInfo['height'] - $this->partSize - $this->factorDiameter);
        // $partY = intval($this->imageInfo['height'] / 2 - $this->partSize / 2);
        return [$partX, $partY];
    }

    protected function setRectanglePoints($partX, $partY)
    {
        //reactangle points
        $this->rectangleStartPoint = [$partX, $partY];
        $this->rectangleXPoint = [$partX + $this->partSize, $partY];
        $this->rectangleYPoint = [$partX, $partY + $this->partSize];
        $this->rectangleXYPoint = [$partX + $this->partSize, $partY + $this->partSize];
    }

    protected function setPartPoints($partX, $partY)
    {
        //part points
        $this->partRightPoint = $this->factorPointIndex == 0 ? [$this->factorPoints[0][0] + $this->factorDiameter / 2, $this->factorPoints[0][1]] : [$this->rectangleXPoint[0], $this->rectangleXPoint[1] + $this->factorOffset];
        $this->partDownPoint = $this->factorPointIndex == 1 ? [$this->factorPoints[1][0], $this->factorPoints[1][1] + $this->factorDiameter / 2] : [$this->rectangleYPoint[0] + $this->factorOffset, $this->rectangleYPoint[1]];
        $this->partMiddlePoint = [$partX + $this->partSize / 2, $partY + $this->partSize / 2];
        $this->partLeftPoint = $this->factorPointIndex == 2 ? [$this->factorPoints[2][0] - $this->factorDiameter / 2, $this->factorPoints[2][1]] : [$partX, $partY + $this->factorOffset];
        $this->partUpPoint = $this->factorPointIndex == 3 ? [$this->factorPoints[3][0], $this->factorPoints[3][1] - $this->factorDiameter / 2] : [$partX + $this->factorDiameter / 2, $partY];
    }

    protected function setPivotPoints($partX, $partY)
    {
        $radiousOffset = $this->factorDiameter / 2;
        //reactangele pivot points
        //up
        $this->rectPivotUpStart = [$this->factorPoints[self::FACTOR_POSITION_UP][0] - $radiousOffset, $partY];
        $this->rectPivotUpEnd = [$this->factorPoints[self::FACTOR_POSITION_UP][0] + $radiousOffset, $partY];
        //right
        $this->rectPivotRightStart = [$partX + $this->partSize, $this->factorPoints[self::FACTOR_POSITION_RIGHT][1] - $radiousOffset];
        $this->rectPivotRightEnd = [$partX + $this->partSize, $this->factorPoints[self::FACTOR_POSITION_RIGHT][1] + $radiousOffset];
        //down
        $this->rectPivotDownStart = [$this->factorPoints[self::FACTOR_POSITION_DOWN][0] + $radiousOffset, $partY + $this->partSize];
        $this->rectPivotDownEnd = [$this->factorPoints[self::FACTOR_POSITION_DOWN][0] - $radiousOffset, $partY + $this->partSize];
        //left
        $this->rectPivotLeftStart = [$partX, $this->factorPoints[self::FACTOR_POSITION_LEFT][1] + $radiousOffset];
        $this->rectPivotLeftEnd = [$partX, $this->factorPoints[self::FACTOR_POSITION_LEFT][1] - $radiousOffset];
    }

    public function getMaster()
    {
        return $this->image;
    }

    public function getMasterHeight()
    {
        return $this->image->getHeight();
    }

    public function getMasterWidth()
    {
        return $this->image->getWidth();
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
        $masterMask->drawEllipse($this->factorStartPoint, $this->factorDiameter, $this->factorDiameter, $white);
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
    protected function drawPartFrame(array $color = [], $thick = null, int $alpha = 0)
    {
        $color = $color ?: $this->options['part_frame_color'] ?: self::DEFAULT_PART_FRAME_COLOR;
        $this->part_thick = $thick = $thick ?: @$this->options['part_thick'] ?: 1;
        // $alpha = $alpha ?: @$this->options['part_alpha'] ?: 0;
        $this->drawFactorArc($color, $thick, $alpha);
        $this->drawPartUpLine($color, $thick, $alpha);
        $this->drawPartRightLine($color, $thick, $alpha);
        $this->drawPartDownLine($color, $thick, $alpha);
        $this->drawPartLeftLine($color, $thick, $alpha);
    }

    //画master frame框
    protected function drawMasterFrame(array $color = [], $thick = null, int $alpha = 0)
    {
        $color = $color ?: @$this->options['master_frame_color'] ?: self::DEFAULT_MASTER_FRAME_COLOR;
        $this->master_thick = $thick = $thick ?: @$this->options['master_thick'] ?: 1;
        $this->drawFactorArc($color, $thick, $alpha, true);
        $this->drawPartUpLine($color, $thick, $alpha, true);
        $this->drawPartRightLine($color, $thick, $alpha, true);
        $this->drawPartDownLine($color, $thick, $alpha, true);
        $this->drawPartLeftLine($color, $thick, $alpha, true);
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
        $width = $height = $this->partSize;
        $radious = ceil($this->factorDiameter / 2);
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
    protected function drawFactorArc(array $color = [], $thick = 1, int $alpha = 0, $isMaster = false)
    {
        $dynamicIm = $isMaster === false ? 'part' : 'image';
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        $degress = self::$factorDrawArcMap[$this->factorPointIndex];
        $this->{$dynamicIm}->drawArc($this->factorStartPoint, $this->factorDiameter, $this->factorDiameter, $degress[0], $degress[1], $color, $thick, $alpha);
    }

    protected function drawMasterFilledArc(array $color = [], int $alpha = 0)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        $degress = self::$factorDrawArcMap[$this->factorPointIndex];
        $this->image->drawFilledArc($this->factorStartPoint, $this->factorDiameter, $this->factorDiameter, $degress[0], $degress[1], $color, $alpha);
    }

    protected function drawMasterRectangle(array $color = [], int $alpha = 0)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        $this->image->drawRectangle($this->rectangleStartPoint, $this->partSize, $this->partSize, $color, $alpha);
    }

    /**
     * Draw part up line.
     *
     * @param array $color
     * @return bool
     */
    protected function drawPartUpLine(array $color = [], $thick = 1, int $alpha = 0, $isMaster = false)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        $dynamicIm = $isMaster ? 'image' : 'part';
        if ($this->factorPointIndex == self::FACTOR_POSITION_UP) {
            $resStart = $this->{$dynamicIm}->drawLine($this->rectangleStartPoint, $this->rectPivotUpStart, $color, $alpha);
            $resEnd = $this->{$dynamicIm}->drawLine($this->rectPivotUpEnd, $this->rectangleXPoint, $color, $alpha);
            return $resStart && $resEnd ? true : false;
        }
        return $this->{$dynamicIm}->drawLine($this->rectangleStartPoint, $this->rectangleXPoint, $color, $thick, $alpha);
    }

    protected function drawPartRightLine(array $color = [], $thick = 1, int $alpha = 0, $isMaster = false)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        $dynamicIm = $isMaster ? 'image' : 'part';

        if ($this->factorPointIndex == self::FACTOR_POSITION_RIGHT) {
            $resStart = $this->{$dynamicIm}->drawLine($this->rectangleXPoint, $this->rectPivotRightStart, $color, $alpha);
            $resEnd = $this->{$dynamicIm}->drawLine($this->rectPivotRightEnd, $this->rectangleXYPoint, $color, $alpha);
            return $resStart && $resEnd ? true : false;
        }
        return $this->{$dynamicIm}->drawLine($this->rectangleXPoint, $this->rectangleXYPoint, $color, $thick, $alpha);
    }

    protected function drawPartDownLine(array $color = [], $thick, int $alpha = 0, $isMaster = false)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        $dynamicIm = $isMaster ? 'image' : 'part';
        if ($this->factorPointIndex == self::FACTOR_POSITION_DOWN) {
            $resStart = $this->{$dynamicIm}->drawLine($this->rectangleXYPoint, $this->rectPivotDownStart, $color, $alpha);
            $resEnd = $this->{$dynamicIm}->drawLine($this->rectPivotDownEnd, $this->rectangleYPoint, $color, $alpha);
            return $resStart && $resEnd ? true : false;
        }
        return $this->{$dynamicIm}->drawLine($this->rectangleXYPoint, $this->rectangleYPoint, $color, $thick = 1, $alpha);
    }

    protected function drawPartLeftLine(array $color = [], $thick = 1, int $alpha = 0, $isMaster = false)
    {
        if (empty($color)) {
            $color = [255, 255, 255];
        }
        $dynamicIm = $isMaster ? 'image' : 'part';
        if ($this->factorPointIndex == self::FACTOR_POSITION_LEFT) {
            $resStart = $this->{$dynamicIm}->drawLine($this->rectangleYPoint, $this->rectPivotLeftStart, $color, $alpha);
            $resEnd = $this->{$dynamicIm}->drawLine($this->rectPivotLeftEnd, $this->rectangleStartPoint, $color, $alpha);
            return $resStart && $resEnd ? true : false;
        }
        return $this->{$dynamicIm}->drawLine($this->rectangleYPoint, $this->rectangleStartPoint, $color, $thick, $alpha);
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

    public function getUpPoint()
    {
        return $this->partUpPoint;
    }

    public function getTopOffset()
    {
        // if ($this->factorPointIndex == self::FACTOR_POSITION_UP) {
        //     return $this->image->getHeight() / 2 - $this->factorDiameter / 2 - $this->partSize / 2;
        // }
        // return $this->image->getHeight() / 2 - $this->partSize / 2;
        return $this->partUpPoint[1];

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
        if ($this->factorPointIndex == self::FACTOR_POSITION_DOWN || $this->factorPointIndex == self::FACTOR_POSITION_UP) {
            return $this->partSize + self::REAL_PART_THICK;
        } else {
            return $this->partSize + $this->factorDiameter / 2 + self::REAL_PART_THICK;
        }
        // return $this->partRightPoint[0] - $this->partLeftPoint[0];
    }

    /**
     * 获取part高度
     *
     * @return int
     */
    public function getPartHeight()
    {
        if ($this->factorPointIndex == self::FACTOR_POSITION_DOWN || $this->factorPointIndex == self::FACTOR_POSITION_UP) {
            return $this->partSize + $this->factorDiameter / 2 + self::REAL_PART_THICK;
        } else {
            return $this->partSize + self::REAL_PART_THICK;
        }
        // return $this->partDownPoint[1] - $this->partUpPoint[1];
    }

}
