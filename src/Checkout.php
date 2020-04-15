<?php
namespace Netzkollektiv\EasyCreditApi;

class Checkout implements CheckoutInterface {

    public function __construct(Client $client, StorageInterface $storage) {
        $this->_api = $client;
        $this->_storage = $storage;
    }

    public function getRedirectUrl() {
        return $this->_api->getRedirectUrl(
            $this->_getToken()
        );
    }

    public function start(
        Rest\QuoteInterface $quote,
        $cancelUrl,
        $returnUrl,
        $rejectUrl
    ) {
        $result = $this->_api->callProcessInit(
            $quote,
            $cancelUrl,
            $returnUrl,
            $rejectUrl
        );

        $this->_storage
            ->set('token',$result->tbVorgangskennung)
            ->set('transaction_id',$result->fachlicheVorgangskennung)
            ->set('authorized_amount',$quote->getGrandTotal())
            ->set('address_hash',$this->_hashAddress($quote->getShippingAddress()));

        return $this;
    }

	public function getConfig() {
		return $this->_api->getConfig();
	}

    public function isInitialized() {
        try {
            $this->_getToken();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function _getToken() {
        $token = $this->_storage->get('token');

        if (empty($token)) {
            throw new Exception('EasyCredit payment not initialized');
        }
        return $token;
    }
    
    public function isApproved() {
        $result = $this->_api->callDecision(
            $this->_getToken()
        );

        if (!isset($result->entscheidung->entscheidungsergebnis)
            || $result->entscheidung->entscheidungsergebnis != 'GRUEN'
        ) {
            return false;
        }
        return true;
    }

    public function loadFinancingInformation() {

        /* get transaction status from api */
        $result = $this->_api->callStatus(
            $this->_getToken()
        );

        $this->_storage->set(
            'pre_contract_information_url',
            (string)$result->allgemeineVorgangsdaten->urlVorvertraglicheInformationen
        );

        /* get financing info from api */
        $result = $this->_api->callFinancing(
            $this->_getToken()
        );

        $this->_storage->set(
            'interest_amount',
            (float)$result->ratenplan->zinsen->anfallendeZinsen
        )->set(
            'payment_plan',
            $this->_formatPaymentPlan($result->ratenplan->zahlungsplan)
        );
    }

    public function capture($token = null, $orderId = null) {
        if (is_null($token)) {
            $token = $this->_getToken();
        }

        $this->_api->callConfirm($token, $orderId);

        $this->_storage->set(
            'is_captured', 1
        );

    }

    public function getInstallmentValues($amount) {
        $result = $this->_api
            ->callModelCalculation($amount);

        $values = array();
        foreach ($result->ergebnis as $installment) {
            $values[] = array(
                'label' => $this->_formatPaymentPlan($installment->zahlungsplan),
                'value' => (int)$installment->zahlungsplan->anzahlRaten
            );
        }
        return $values;
    }

    public function getAgreement() {
        return $this->_api->callAgreement()
            ->zustimmungDatenuebertragungPaymentPage;
    }

    public function verifyCredentials($apiKey, $apiToken) {
        return $this->_api->verifyCredentials($apiKey, $apiToken);
    }

    protected function _formatPaymentPlan($paymentPlan) {
        if (!is_object($paymentPlan)) {
            $paymentPlan = (object)$paymentPlan;
        }
        return sprintf('%d Raten à %0.2f€ (%d x %0.2f€, %d x %0.2f€)',
            (int)   $paymentPlan->anzahlRaten,
            (float) $paymentPlan->betragRate,
            (int)   $paymentPlan->anzahlRaten - 1,
            (float) $paymentPlan->betragRate,
            1,
            (float) $paymentPlan->betragLetzteRate
        );
    }

    public function getIsCustomerSameAsBilling(Rest\QuoteInterface $quote) {
        if (!$quote->getCustomer()->isLoggedIn()) {
            return true;
        }

        if (trim($quote->getCustomer()->getFirstname()) != trim($quote->getBillingAddress()->getFirstname()) ) {
            return false;
        }

        if (trim($quote->getCustomer()->getLastname()) != trim($quote->getBillingAddress()->getLastname()) ) {
            return false;
        }

        return true;
    }

    protected function _hashAddress(Rest\AddressInterface $address) {
        return sha1(json_encode(
            $this->_api->convertAddress($address, true)
        ));
    }

    public function verifyAddressNotChanged(Rest\QuoteInterface $quote) {

        $currentHash = $this->_hashAddress(
            $quote->getShippingAddress()
        );
        $initialHash = $this->_storage->get('address_hash');

        return ($currentHash === $initialHash);
    }

    public function sameAddresses(Rest\QuoteInterface $quote) {
        $diff = array_diff_assoc(
            $this->_api->convertAddress($quote->getShippingAddress(), true), 
            $this->_api->convertAddress($quote->getBillingAddress(), true)
        );
        return (count($diff) === 0);
    }

    public function isAvailable(Rest\QuoteInterface $quote) {

        if (!$this->getIsCustomerSameAsBilling($quote)) {
            throw new Exception('Zur Zahlung mit ratenkauf by easyCredit, müssen der Rechnungsempfänger und der Inhaber des Kundenkontos identisch sein.
                Bitte ändern Sie den Namen des Rechnungsempfängers entsprechend ab.');
        }

        if (!$this->sameAddresses($quote)) {
            throw new AddressException('Zur Zahlung mit ratenkauf by easyCredit muss die Rechnungsadresse mit der Lieferadresse übereinstimmen.');
        }

        $company = $quote->getCustomer()->getCompany();
        if (trim($company) != '') {
            throw new AddressException('ratenkauf by easyCredit ist nur für Privatpersonen möglich.');
        }

        try {
            $this->getInstallmentValues($quote->getGrandTotal());
        } catch (\Exception $e) {
            $msg = str_replace('ratenkauf by easyCredit:','',$e->getMessage());
            throw new Exception($msg);
        }
        return true;
    }

    public function isAmountValid(Rest\QuoteInterface $quote) {
        $amount = (float) $quote->getGrandTotal();
        $authorizedAmount = (float) $this->_storage->get('authorized_amount');
        $interestAmount = (float) $this->_storage->get('interest_amount');

        if (
            $authorizedAmount > 0
            && $interestAmount > 0
            && round($amount, 2) != round($authorizedAmount + $interestAmount, 2)
        ) {
            return false;
        }
        return true;
    }

    public function isPrefixValid($prefix) {
        return ($this->_api->convertCustomerPrefix($prefix) !== '');
    }

    public function clear() {
        $this->_storage->clear();
    }
}
