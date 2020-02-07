# <center>egment/captcha</center>

### Quick Start

- The simplest way to use it

```php
use Egment/captcha;

$path = (new captcha)->store();
//Above return value looks like this:
[
    'master_path' => 'foo.jpg',
    'part_path' => 'bar.jpg'
]

//also we can specified a type parameter to add base64 code value.
(new captcha)->store(1);
//This return value will be looks like this:
[
    'master_path' => 'foo.jpg',
    'part_path' => 'bar.jpg',
    'master_base64' => 'MASTER_WHAT_BASE64CODE_LOOKSLIKE',
    'part_base64' => 'MASTER_WHAT_BASE64CODE_LOOKSLIKE',
]
```



- Use configure options

```php
use Egment/captcha
$options = [
    'store_path' => './', //captcha image store path
    'bg_path' => './',	//background pictures that egment/captcha will use
    'master_name' => 'foo' . time(),
    'part_name' => 'bar' . time(),
    'part_size' => 30, //part captcha size
    'bg_exts' => ['jpg','jpeg','png'] //allowed background pictures extensions
    
]
$captcha = new Capthca($options)
$result = $captcha->store();

```



- Use auth method to authenticate input parameters.

```php
$captcha->auth($positionParameters, function ($params, $instance) {
    return $instance->authSlidePosition($params['position'], $instance->getPartMiddlePoint()[0]);
});
```