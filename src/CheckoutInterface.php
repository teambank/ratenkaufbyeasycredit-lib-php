<?php
namespace Netzkollektiv\EasyCreditApi;

interface CheckoutInterface {
    public function getRedirectUrl();
    public function start(
        Rest\QuoteInterface $quote,
        $cancelUrl,
        $returnUrl,
        $rejectUrl
    );
    public function getConfig();
    public function isInitialized();
    public function isApproved();
    public function loadFinancingInformation();
    public function capture($token = null, $orderId = null);
    public function getInstallmentValues($amount);
    public function getAgreement();
    public function verifyCredentials($apiKey, $apiToken);
    public function getIsCustomerSameAsBilling(Rest\QuoteInterface $quote);
    public function verifyAddressNotChanged(Rest\QuoteInterface $quote);
    public function sameAddresses(Rest\QuoteInterface $quote);
    public function isAmountValid(Rest\QuoteInterface $quote);
    public function clear();
}
