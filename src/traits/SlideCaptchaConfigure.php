<?php

namespace Egment\traits;

trait SlideCaptchaConfigure
{

    protected $options;

    public function init(array $options = [])
    {
        $this->options = $options;
        $this->setStorePath(@$options['store_path'] ?: "");
        $this->setBgPath(@$options['bg_path'] ?: "");
        $this->setPartSize(@$options['part_size'] ?: "");
        $this->setScanBgExtension(
            count(@$options['bg_exts']) > 0 ? $options['bg_exts'] : []
        );
    }

}
