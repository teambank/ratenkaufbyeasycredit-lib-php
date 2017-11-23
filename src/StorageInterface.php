<?php
namespace Netzkollektiv\EasyCreditApi;

interface StorageInterface {

    public function set($key, $value);
    public function get($key);
    public function clear();

}