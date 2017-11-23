<?php
namespace Netzkollektiv\EasyCreditApi\Rest;

interface ItemInterface {

	public function getSku();
	public function getName();
	public function getQty();
	public function getPrice();
	public function getManufacturer();
	public function getCategory();

}