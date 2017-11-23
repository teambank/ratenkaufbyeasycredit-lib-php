<?php
namespace Netzkollektiv\EasyCreditApi\Rest;

interface ShippingAddressInterface extends AddressInterface {

    public function getFirstname();
    public function getLastname();

    public function getIsPackstation();
}