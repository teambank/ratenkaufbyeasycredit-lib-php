<?php
namespace Netzkollektiv\EasyCreditApi\Rest;

interface ShippingAddressInterface extends AddressInterface {

    public function getIsPackstation();
}
