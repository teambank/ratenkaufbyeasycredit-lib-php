<?php
namespace Netzkollektiv\EasyCreditApi;

interface LoggerInterface {

	public function log($msg);
	public function logDebug($msg);
	public function logInfo($msg);
	public function logError($msg);

}
