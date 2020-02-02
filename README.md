# <center>egment/captcha</center>


Firstly, We should choice a image to create image resouce for Captcha.

```php
use Egment/captcha;

$path = './assets/images/spacex3.jpeg';
$image = new Image();
$image->create($path);
```



Then use follow methods to store two images.

```php
use Egment/SlideCaptcha;

$captcha = new SlideCaptcha($image);
$masterPath = $captcha->createMaster()->store();
$partPath = $captcha->createPart()->store();
```

Or you can get base64encode image resource through `storeWithBase64` method

```php
use Egment/SlideCaptcha;

$captcha = new SlideCaptcha($image);
$masterBase64Encode = $captcha->createMaster()->storeWithBase64();
$partBase64Encode = $captcha->createPart()->storeWithBase64();
```


Finally, use auth method to authenticate input parameters.

```php
$captcha->auth($positionParameters, function ($params, $instance) {
    return $instance->authSlidePosition($params['position'], $instance->getPartMiddlePoint()[0]);
});
```





