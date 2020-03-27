<?php
namespace Egment\Contracts;

interface VerifyAble {
    /**
     * @access public
     * @param  string
     * @return bool
     */
    public function has($name);

    /**
     * @access public
     * @param  string $name
     * @param  mixed  $default
     * @return mixed
     */
    public function get($name, $default = false);

    /**
     * @access public
     * @param  string    $name 
     * @param  mixed     $value  
     * @param  int       $expire
     * @return boolean
     */
    public function set($name, $value, $expire = null);


    /**
     * @access public
     * @param  string    $name
     * @return boolean
     */
    public function remove($name);

}