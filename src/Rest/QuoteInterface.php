<?php
namespace Netzkollektiv\EasyCreditApi\Rest;

interface QuoteInterface {

	public function getId();
	public function getShippingMethod();

    public function getGrandTotal();

    public function getBillingAddress();
    public function getShippingAddress();

    public function getCustomer();

    public function getItems();

	public function getSystem();
}
