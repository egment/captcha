<?php

namespace Egment\Traits;

trait SlideCaptchaConfigure
{

    protected $options;

    public function init(array $options = [])
    {
        $this->options = $options;
        $this->setStorePath(@$options['store_path'] ?: "");
        $this->setBgPath(@$options['bg_path'] ?: "");
        $this->setPartSize(@$options['part_size'] ?: "");
        if (isset($options['bg_exts']) && is_array($options['bg_exts'])) {
            $this->setScanBgExtension($options['bg_exts']);
        }
    }

}
