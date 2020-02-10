<?php

namespace Egment\Contracts;

interface Configureable
{
    public function init(array $options);

    public function setBgPath(string $path);

    public function setStorePath(string $path);

    public function getBgPath();

    public function getStorePath();
}
