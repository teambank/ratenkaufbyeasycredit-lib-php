<?php
namespace Netzkollektiv\EasyCreditApi\Rest;

interface CustomerInterface {

    public function getPrefix();
    public function getFirstname();
    public function getLastname();
    public function getEmail();
    public function getDob();

    public function getCompany();

	public function getTelephone();

    public function isLoggedIn();
    public function getCreatedAt();
    public function getOrderCount();

}
