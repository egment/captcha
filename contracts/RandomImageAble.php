<?php

namespace Egment\Contracts;

use Egment\Image;

interface RandomImageAble
{
    /**
     * Create random image
     *
     * @param string $path
     * @return Image
     */
    public function createRandomImage(\Closure $fn = null);

}
