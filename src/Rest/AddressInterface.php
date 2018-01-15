<?php
namespace Netzkollektiv\EasyCreditApi\Rest;

interface AddressInterface {

    public function getFirstname();
    public function getLastname();
    public function getStreet();
    public function getStreetAdditional();
    public function getPostcode();
    public function getCity();
    public function getCountryId();

}
