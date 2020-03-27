<?php

namespace Egment\Traits;

use Egment\Contracts\VerifyAble;
use ErrorException;
use Exception;

trait CaptchaVerify
{

    public static $verifyIdentifierKey = 'identifier';
    public static $verifyPositionKey = 'position';

    protected $storageDriver;
    protected $verifyExpire = 60;
    protected $verifySlideGap = 3;

    protected $verifyIdentifierValue; //identifier value
    protected $verifyPositionValue; //position value

    protected $customIdentifierGenerator;

    public function verifyInit($storage, $options = [])
    {
        $this->setStorageDriver($storage);
        if (array_key_exists('verify_expire', $options) && $options['verify_expire'] !== null && $options['verify_expire'] !== '') {
            $this->verifyExpire = $options['verify_expire'];
        }
        if (array_key_exists('verify_slide_gap', $options) && $options['verify_slide_gap'] !== null && $options['verify_slide_gap'] !== '') {
            $this->verifySlideGap = $options['verify_slide_gap'];
        }

    }

    public function setStorageDriver(VerifyAble $driver)
    {
        $this->storageDriver = $driver;
    }

    public function withRecord($expire = null)
    {
        $this->verifyIdentifierValue = $this->generateIdentifier();
        $this->verifyExpire = $expire ?: $this->verifyExpire;
        try {
            $this->storageDriver->set($this->verifyIdentifierValue, $this->getPartLeftPoint()[0], $this->verifyExpire);
        } catch (\Exception $e) {
            throw new Exception("An error has occurred in storage driver with called SET() at withRecord() DETAIL" . $e->getMessage());
        }
        return $this;
    }

    public function verify($verifiedPosition = null, $slide = null)
    {
        $this->verifySlideGap = $slide ?: $this->verifySlideGap;
        try {
            $verifiedPosition = $verifiedPosition ?: $_POST[self::$verifyPositionKey] ?: $_GET[self::$verifyPositionKey] ?: "";
            $verifiedKey = $_POST[self::$verifyIdentifierKey] ?: $_GET[self::$verifyIdentifierKey] ?: "";
        } catch (\Exception $e) {
            throw new ErrorException("Invalid request parameters, Lack of POSITION or IDENTIFIED parameter " . $e->getMessage());
        }
        if (is_null($verifiedPosition) || $verifiedPosition === '') {
            return false;
        }
        if (is_null($verifiedKey) || $verifiedKey === '') {
            return false;
        }
        $this->verifyPositionValue = $cachePosition = $this->storageDriver->get($verifiedKey);
        if (is_null($cachePosition) && $cachePosition === '') {
            return false;
        }
        if (abs($verifiedPosition - $cachePosition) > $this->verifySlideGap) {
            $this->storageDriver->remove($verifiedKey);
            return false;
        }
        $this->storageDriver->remove($verifiedKey);
        return true;
    }

    //生成标识
    public function generateIdentifier(\Closure $identifierGenerator = null)
    {
        if ($identifierGenerator) {
            $this->setIdentifierGenerator($identifierGenerator);
        }
        if ($this->customIdentifierGenerator) {
            return $this->customIdentifierGenerator($this->verifyKey);
        }
        return eg_str_random();
    }

    //设置标识产生器
    public function setIdentifierGenerator(\Closure $abstract)
    {
        $this->customIdentifierGenerator = $abstract;
        return $this;
    }

    public function getVerifyIdentifier()
    {
        return $this->verifyIdentifierValue;
    }

    public function getIdentifier()
    {
        return $this->verifyIdentifierValue;
    }

    public function getVerifyPosition()
    {
        return $this->verifyPositionValue;
    }

    public function getPosition()
    {
        return $this->getPartLeftPoint()[0];
    }

}
