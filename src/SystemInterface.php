<?php
namespace Netzkollektiv\EasyCreditApi;

interface SystemInterface {

	public function getSystemVendor();
	public function getSystemVersion();
	public function getModuleVersion();
	public function getIntegration();
    
}