<?php
namespace Netzkollektiv\EasyCreditApi;

class Client
{
    protected $_customerPrefixMalePatterns = array('Herr','Mr','male','männlich');
    protected $_customerPrefixFemalePatterns = array('Frau','Ms','Miss','Mrs','female','weiblich');

    protected $_config = null;
    protected $_clientFactory = null;
    protected $_logger = null;

    public function __construct(
        Config $config, 
        Client\HttpClientFactory $clientFactory, 
        LoggerInterface $logger
    ) {
        $this->_config = $config;
        $this->_clientFactory = $clientFactory;
        $this->_logger = $logger;
    }

	public function getConfig() {
		return $this->_config;
	}

    public function getRedirectUrl($token) {
        return 'https://ratenkauf.easycredit.de/ratenkauf/content/intern/einstieg.jsf?vorgangskennung='.$token;
    }
    
    public function callProcessInit($quote, $cancelUrl, $returnUrl, $rejectUrl) {
        return $this->_call('POST', 'vorgang', 
            $this->_getProcessInitRequest($quote, $cancelUrl, $returnUrl, $rejectUrl)
        );
    }

    public function callModelCalculation($amount) {
        return $this->_call('GET', 'modellrechnung/durchfuehren', array(
            'webshopId' => $this->_config->getWebshopId(),
            'finanzierungsbetrag' => $amount,
        ));
    }

    public function callDecision($token) {
        return $this->_call('GET','vorgang/'.$token.'/entscheidung');
    }

    public function callStatus($token) {
        return $this->_call('GET','vorgang/'.$token);
    }

    public function callFinancing($token) {
        return $this->_call('GET','vorgang/'.$token.'/finanzierung');
    }

    public function callConfirm($token, $orderId = null) {
        $data = array();
        if ($orderId !== null) {
            $data = array(
                'shopVorgangskennung' => $orderId
            );
        }
        return $this->_call('POST','vorgang/'.$token.'/bestaetigen', $data);
    }

    public function callAgreement() {
        return $this->_call('GET','texte/zustimmung/'.$this->_config->getWebshopId());
    }

    public function verifyCredentials($apiKey, $apiToken) {
        try {
            $this->_call('GET', 'webshop/' . $apiKey .'/restbetragankaufobergrenze', 
                array(), $apiKey, $apiToken
            );
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    protected function _call($method, $resource, $data = array(), $webShopId = null, $webShopToken = null) {

        if ($webShopId === null) {
           $webShopId = $this->_config->getWebshopId();
        }
        if ($webShopToken === null) {
            $webShopToken = $this->_config->getWebshopToken();
        }

        $url = $this->_config->getApiUrl($resource);
        $method = strtoupper($method);

        $this->_logger->logDebug(array($method, $url,$data));

        $client = $this->_clientFactory->getClient($url, array(
            'keepalive' => true
        ));
        $client->setHeaders(array(
            'Accept' => 'application/json, text/plain, */*',
            'tbk-rk-shop' => $webShopId,
            'tbk-rk-token' => $webShopToken
        ));

        if ($method == 'POST') {
            $client->setRawData(
                json_encode($data),
                'application/json;charset=UTF-8'
            );
            $data = null;
        } else {
            $client->setParameterGet($data);
        }

        $response = $client->request($method);
        $result = $response->getBody();

        if (empty($result)) {
            $this->_throw('easyCredit API: returned an empty result');
        }

        $result = json_decode($result);
        $this->_logger->logDebug($result);

        if ($result == null) {
            $this->_throw('easyCredit API: result could not be parsed or is null');
        }

        if (isset($result->wsMessages)) {
            $this->_logger->logDebug($result->wsMessages);
            $this->_handleMessages($result);
        }

        if ($response->isError()) {
            $this->_logger->logError($response->getBody());
            $this->_throw('easyCredit API: returned an unspecified error');
        }

        return $result;
    }

    protected function _handleMessages($result) {
        if (!isset($result->wsMessages->messages)) {
            unset($result->wsMessages);
            return;
        }

        foreach ($result->wsMessages->messages as $message) {
            
            $devMessage = implode(': ',array_filter(array(
                $message->field,
                $message->key
            )));
            $devMessage = '('.$devMessage.')';
            if (isset($message->infoFuerBenutzer) && $message->infoFuerBenutzer) {
                $devMessage = $message->infoFuerBenutzer.' '.$devMessage;
            } else {
                $devMessage = $message->renderedMessage.' '.$devMessage;
            }

            $userMessage = (isset($message->infoFuerBenutzer) && $message->infoFuerBenutzer) ? $message->infoFuerBenutzer : 'An error occured';
            if ($message->field) {
                $userMessage.= ' ('.$message->field.')';
            }

            switch (trim($message->severity)) {
                case 'INFO':
                    $this->_logger->logInfo($devMessage);
                    break;
                case 'WARNING':
                    $this->_logger->logWarn($devMessage);
                    break;
                default:
                    $this->_logger->logError($devMessage);
                   
                    $key = str_replace('AdressdatenValidierenUndNormierenServiceActivityMsg.','',$message->key); 
                    if (in_array($key,array(
                        'Errors.ADRESSE_ERNEUT_EINGEBEN_VERBOTENE_ZEICHEN_VERWENDET',
                        'Errors.ADRESSE_UNBEKANNT',
                        'Errors.ADRESSE_MEHRDEUTIG',
                        'Errors.STRASSE_HNR_NICHT_ANGEGEBEN',
                        'Errors.STRASSE_HNR_HNR_FEHLT',
                        'Errors.STRASSE_HNR_POSTFACH',
                        'Errors.PLZ_NICHT_ANGEGEBEN',
                        'Errors.ORT_NICHT_ANGEGEBEN',
                        'Errors.LIEFERADRESSE_FEHLERHAFT',
                        'Errors.PATTERN_VALIDATION',
                    )) !== false) {
                        throw new AddressException($userMessage);
                    }
                    throw new Exception('ratenkauf by easyCredit: '.$userMessage);
            }
        }
        unset($result->wsMessages);
    }

    protected function _throw($msg) {
        $this->_logger->log($msg);
        throw new Exception($msg);
    }

    protected function _getFormattedDate($date)
    {
        return (strtotime($date) !== false) ? date('Y-m-d', strtotime($date)) : null;
    }

    protected function _getRequestContext($method, $postData = null) {

        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json, text/plain, */*',
            'tbk-rk-shop' => $this->_config->getWebshopId(),
            'tbk-rk-token' => $this->_config->getWebshopToken(),
        );

        $ctx = array('http' => array(
            'ignore_errors' => true
        ));

        if (!is_null($postData)) {
            $headers['Content-Type'] = 'application/json;charset=UTF-8';
            $ctx['http']['content'] = json_encode($postData);
        }

        foreach ($headers as $key => $header) {
            $headers[$key] = implode(': ', array($key, $header));
        }

        $ctx['http']['method'] = strtoupper($method);
        $ctx['http']['header'] = implode("\r\n", $headers);

        return stream_context_create($ctx);
    }

    /**
     * @param Quote $quote
     * @param $cancelUrl
     * @param $returnUrl
     * @param $rejectUrl
     * @return mixed
     */
    protected function _getProcessInitRequest($quote, $cancelUrl, $returnUrl, $rejectUrl)
    {
        $customer = $quote->getCustomer();
        
        return array_filter(array(
            'shopKennung' => $this->_config->getWebshopId(),
            'vorgangskennungShop' => is_string($quote->getId()) ? substr($quote->getId(),0,50) : $quote->getId(),
            'bestellwert' => $quote->getGrandTotal(),
            'ruecksprungadressen' => array(
                'urlAbbruch' => $cancelUrl,
                'urlErfolg' => $returnUrl,
                'urlAblehnung' => $rejectUrl
            ),
            'laufzeit' => 36,
            'personendaten' => array(
                'anrede' => $this->convertCustomerPrefix($customer->getPrefix()),
                'vorname' => $customer->getFirstname(),
                'nachname' => $customer->getLastname(),
                'geburtsdatum' => $this->_getFormattedDate($customer->getDob())
            ),
            'kontakt' => array(
                'email' => $customer->getEmail()
            ),
            'weitereKaeuferangaben' => array_filter(array(
                'telefonnummer' => (preg_match('/^[+0][\d -]+$/',$customer->getTelephone())) ? $customer->getTelephone() : '',
            )),
            'risikorelevanteAngaben' => $this->_convertRiskDetails($quote),
            'rechnungsadresse' => $this->convertAddress($quote->getBillingAddress()),
            'lieferadresse' => $this->convertAddress($quote->getShippingAddress()),
            'warenkorbinfos' => $this->_convertItems($quote->getItems()),
            'technischeShopparameter' => array(
                'shopSystemHersteller' => $quote->getSystem()->getSystemVendor().' '.$quote->getSystem()->getSystemVersion(),
                'shopSystemModulversion' => $quote->getSystem()->getModuleVersion()
            ),
            'integrationsart' => $quote->getSystem()->getIntegration(),            
        ));
    }

    /**
     * @param Quote $quote
     * @return array
     */
    protected function _convertRiskDetails($quote)
    {
        $details = array(
            'bestellungErfolgtUeberLogin' => $quote->getCustomer()->isLoggedIn(),
            'anzahlProdukteImWarenkorb' => count($quote->getItems())
        );

        if (trim($quote->getShippingMethod() != '')) {
            $details['logistikDienstleister'] = $quote->getShippingMethod();
        }

        if ($quote->getCustomer()->isLoggedIn()) {
            $customer = $quote->getCustomer();

            $details = array_merge($details, array(
                'kundeSeit' => $this->_getFormattedDate($customer->getCreatedAt()),
                'anzahlBestellungen' => $customer->getOrderCount()
            ));
        }
        return $details;
    }

    /**
     * @param array $items
     * @return array
     */
    protected function _convertItems($items)
    {
        $_items = array();

        foreach ($items as $item) {
            $_item = array(
                'produktbezeichnung' => $item->getName(),
                'menge' => $item->getQty(),
                'preis' => $item->getPrice(),
                'hersteller' => $item->getManufacturer(),
            );

            $_item['produktkategorie'] = $item->getCategory();

            $skus = $item->getSku();
            if (!is_array($skus)) {
                $skus = array('sku' => $skus); 
            }

            $_item['artikelnummern'] = array();
            foreach ($skus as $key => $value) {
                $_item['artikelnummern'][] = array(
                    'nummerntyp' => $key,
                    'nummer' => $value
                );
            }

            $_items[] = array_filter($_item);
        }
        return $_items;
    }

    public function convertAddress(Rest\AddressInterface $address, $convertForCompare = false)
    {
        $_address = array(
            'strasseHausNr' => $address->getStreet(),
            'plz' => $address->getPostcode(),
            'ort' => $address->getCity(),
            'land' => $address->getCountryId()
        );

        if ($address->getStreetAdditional()) {
            $_address['adresszusatz'] = $address->getStreetAdditional();

        }

        if (!$convertForCompare && $address instanceof Rest\ShippingAddressInterface) {
            $_address = array_merge($_address, array(
                'vorname' => $address->getFirstname(),
                'nachname'  => $address->getLastname()
            ));

            if ($address->getIsPackstation()) {
                $_address['packstation'] = true;
            }
        }

        return $_address;
    }

    public function convertCustomerPrefix($prefix)
    {
        foreach ($this->_customerPrefixMalePatterns as $pattern) {
            if (stripos($prefix, $pattern) !== false) {
                return 'HERR';
            }
        }
        foreach ($this->_customerPrefixFemalePatterns as $pattern) {
            if (stripos($prefix, $pattern) !== false) {
                return 'FRAU';
            }
        }
        return "";
    }
}
