<?php
namespace Netzkollektiv\EasyCreditApi;

interface CacheInterface {

    public function get($key);
    public function save($key, $value);

}