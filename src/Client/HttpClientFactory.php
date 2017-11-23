<?php
namespace Netzkollektiv\EasyCreditApi\Client;

class HttpClientFactory {
	public function getClient($url, $params) {
		return new \Zend_Http_Client($url, $params);
	}
}