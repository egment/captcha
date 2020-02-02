<?php
/*
 * @Description: This class part of egment/Image package.
 * @Author: J.W.
 * @Version 0.1-rc-202
 * @Date: 2020-02-02 18:57:59
 */

namespace Egment;

use ErrorException;

class Image
{

    const DEFAUTL_BARE_WIDTH = 100;
    const DEFAULT_BARE_HEIGHT = 100;

    //存储路径
    protected $storeagePath = './';

    protected $im;
    //原图路径
    protected $path;
    protected $alpha;

    //原图参数
    protected $width;
    protected $height;

    public function __construct($path = null)
    {
        if (is_string($path)) {
            $this->path = $path;
            list($this->width, $this->height) = getimagesize($path);
            $this->create($path);
        } else if (is_resource($path)) {
            $this->im = $path;
        }
    }

    /**
     * 创建true color
     *
     * @param [type] $width
     * @param [type] $height
     * @return Object
     */
    public function createTrueColor($width, $height)
    {
        return imageCreateTrueColor($width, $height);
    }

    /**
     * 创建im资源
     *
     * @param [type] $path
     * @return Object
     */
    public function create($path = null)
    {
        if ($path) {
            $this->path = $path;
            list($this->width, $this->height) = getimagesize($path);
        } else {
            $path = $this->path;
        }
        $type = \exif_imagetype($path);
        $allowedTypes = [
            1, // [] gif
            2, // [] jpg
            3, // [] png
            6, // [] bmp
        ];
        if (!in_array($type, $allowedTypes)) {
            return false;
        }
        switch ($type) {
            case 1:
                $this->im = imageCreateFromGif($path);
                break;
            case 2:
                $this->im = imageCreateFromJpeg($path);
                break;
            case 3:
                $this->im = imageCreateFromPng($path);
                break;
            case 6:
                $this->im = imageCreateFromBmp($path);
                break;
        }
        return $this->im;
    }

    /**
     * 创建裸图像
     *
     * @param integer $width
     * @param integer $height
     * @return Object
     */
    public function bareCreate($width = 0, $height = 0)
    {
        $width = $width ?: self::DEFAUTL_BARE_WIDTH;
        $height = $height ?: self::DEFAULT_BARE_HEIGHT;
        return imagecreatetruecolor($width, $height);
    }

    /**
     * 保存图像
     *
     * @param [type] $name
     * @param string $path
     * @return String
     */
    public function save($name = null, $path = './')
    {
        return $this->store(null, $name, $path);
    }

    /**
     * 展示图像
     *
     * @review need
     * @optimized need
     * @param [type] $im
     * @param boolean $store
     * @param [type] $name
     * @param string $path
     * @return Object
     */
    public function show($im = null)
    {
        $this->im = $im ?: $this->im;
        if (!$this->path) {
            if (is_resource($this->im)) {
                header('Content-Type:image/png');
                //裸图默认返回格式
                imagepng($this->im);
                imagedestroy($this->im);
            }
            throw new ErrorException("无可用im资源，无法展示图像！");
        }
        $ext = pathinfo($this->path, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
            case 'jpe':
                header('Content-Type:image/jpeg');
                imagejpeg($this->im);
                break;
            case 'png':
                header('Content-Type:image/png');
                imagepng($this->im);
                break;
            case 'gif':
                header('Content-Type:image/gif');
                imagegif($this->im);
                break;
            case 'bmp':
            case 'wbmp':
                header('Content-Type:image/bmp');
                imagebmp($this->im);
                break;
        }
        imagedestroy($this->im);
    }

    /**
     * 存储图像
     *
     * @param [type] $im
     * @param [type] $name
     * @param string $path
     * @return String
     */
    public function store($im = null, $name = null, $path = null)
    {
        $this->im = $im ?: $this->im;
        $path = $path ?: $this->storeagePath;
        if (!$this->path) {
            if (is_resource($this->im)) {
                $name = $name ?: md5(mt_rand() . time());
                $fullPath = $path . '/' . $name . '.png';
                imagepng($this->im, $fullPath);
                imagedestroy($this->im);
                $realPath = realpath($fullPath);
                return $realPath;
            }
            throw new ErrorException("缺少可用的im,不能存储！");
        }
        $ext = pathinfo($this->path, PATHINFO_EXTENSION);
        $name = $name ?: md5(mt_rand() . time());
        $fullPath = $path . $name . '.' . $ext;

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
            case 'jpe':
                imagejpeg($this->im, $fullPath);
                break;
            case 'png':
                imagepng($this->im, $fullPath);
                break;
            case 'git':
                imagegif($this->im, $fullPath);
                break;
            case 'bmp':
            case 'wbmp':
                imagebmp($this->im, $fullPath);
                break;
        }
        imagedestroy($this->im);
        $realPath = realpath($fullPath);
        return $realPath;
    }

    /**
     * 获取当前图像原图的基本信息
     *
     * @return array
     */
    public function getInfo()
    {
        if (!$this->im || !$this->path) {
            throw new ErrorException("无效的资源对象！");
        }
        $info = getimagesize($this->path);
        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];
        return ['width' => $width, 'height' => $height, 'resource' => $this->im, 'mime' => $mime];

    }

    /**
     * 在图上截取一个矩形
     *
     * @param array $startPoint
     * @param array $endPoint
     * @return Object
     */
    public function cutRectangle(array $startPoint, int $width, int $height)
    {
        $endPoint = [$startPoint[0] + $width, $startPoint[1] + $height];
        $mergedIm = imagecreatetruecolor($endPoint[0] - $startPoint[0], $endPoint[1] - $startPoint[1]);
        $alpha = $this->alpha ?: 100;
        imagecopymerge($mergedIm, $this->im, 0, 0, $startPoint[0], $startPoint[1], ($endPoint[0] - $startPoint[0]), ($endPoint[1] - $startPoint[1]), $alpha);
        $this->im = $mergedIm;
        return $this;
    }

    /**
     * 画一个矩形
     *
     * @param array $startPoint
     * @param [type] $width
     * @param [type] $height
     * @param [type] $color
     * @return Object
     */
    public function drawRectangle(array $startPoint, $width, $height, $color, $alpha = 0)
    {
        $color = imagecolorallocatealpha($this->im, $color[0], $color[1], $color[2], $alpha);
        imagefilledrectangle($this->im, $startPoint[0], $startPoint[1], $startPoint[0] + $width, $startPoint[1] + $height, $color);
        return $this;
    }

    /**
     * 画一个椭圆
     *
     * @param array $startPoint
     * @param [type] $width
     * @param [type] $height
     * @param [type] $color
     * @return Object
     */
    public function drawEllipse(array $startPoint, $width, $height, $color, $alpha = 0)
    {
        $color = imagecolorallocatealpha($this->im, $color[0], $color[1], $color[2], $alpha);
        imagefilledellipse($this->im, $startPoint[0], $startPoint[1], $width, $height, $color);
        return $this;
    }

    /**
     * 填充
     *
     * @param [type] $color
     * @param integer $alpha
     * @return void
     */
    public function fill($color, $alpha = 0)
    {
        // $color = imagecolorallocatealpha($im, 255, 0, 0, 100);
        $bg = imagecolorallocatealpha($this->im, $color[0], $color[1], $color[2], $alpha);
        imagefill($this->im, 0, 0, $bg);
    }

    /**
     * 透明填充
     *
     * @return void
     */
    public function fillTransparent()
    {
        $this->fill([255, 0, 0], 127);
        imagesavealpha($this->im, true);
    }

    public function copy($srcIm, array $destStart, array $srcStart, $width, $height)
    {
        $destIm = $this->im;
        return imagecopy($destIm, $srcIm, $destStart[0], $destStart[1], $srcStart[0], $srcStart[1], $width, $height);
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function getIm()
    {
        return $this->im;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setAlpha(int $value)
    {
        $this->alpha = $value;
        return $this;
    }

    public function __clone()
    {
        return new Image($this->path);
    }

    public function __destruct()
    {
        if ($this->im && is_resource($this->im)) {
            imagedestroy($this->im);
        }
    }

}
