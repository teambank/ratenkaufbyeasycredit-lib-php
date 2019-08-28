<?php
namespace Netzkollektiv\EasyCreditApi;

class Merchant
{
    protected $_config = null;
    protected $_clientFactory = null;
    protected $_logger = null;

    protected $_availableStatus = array(
        'LIEFERUNG_MELDEN','LIEFERUNG_MELDEN_AUSLAUFEND','ALLE','IN_ABRECHNUNG','ABGERECHNET','AUSLAUFEND'
    );

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

    public function search($search = null, $status = null, \DateTime $from = null, \DateTime $to = null) {
        $data = array_filter(array(
            'suche'        => $search,
            'status'     => $status,
            'von'         => ($from !== null) ? $from->format('Y-m-d') : null,
            'bis'         => ($to !== null) ? $to->format('Y-m-d') : null
        ),'strlen');

        if (null !== $status && !in_array($data['status'],$this->_availableStatus)) {
            throw new Exception('status '.$data['status'].' does not exist to filter transactions');
        }

        // use v0, because filter in v2 is not working properly
        $result = $this->_call('GET','https://app.easycredit.de/haendlerinterface-ws/rest/v0/haendlerinterface/suchen',$data);
        
        if (!isset($result->ergebnisse)) {
            return array();
        }
        return $result->ergebnisse;
    }
    public function confirmShipment($transactionId, \DateTime $shipmentDate = null) {
        $data = array_filter(array(
           'datum'    => ($shipmentDate !== null) ? $shipmentDate->format('Y-m-d') : null
        ));

        return $this->_call('POST','transaktionen/'.$transactionId.'/lieferung');
    }

    public function cancelOrder($transactionId, $reason, \DateTime $date, $amount = null) {
        $data = array_filter(array(
            'grund'    => $reason,
            'betrag'   => $amount,
            'datum'    => ($date !== null) ? $date->format('Y-m-d') : null
        ));

        return $this->_call('POST','transaktionen/'.$transactionId.'/rueckabwicklung',$data);
    }

    protected function _call($method, $resource, $data = array(), $webShopId = null, $webShopToken = null) {

        if ($webShopId === null) {
           $webShopId = $this->_config->getWebshopId();
        }
        if ($webShopToken === null) {
            $webShopToken = $this->_config->getWebshopToken();
        }

        $url = $this->_config->getMerchantApiUrl($resource);
        
        $method = strtoupper($method);

        $this->_logger->logDebug($data);

        $client = $this->_clientFactory->getClient($url, array(
            'keepalive' => true
        ));

        if (strpos($resource,'http')===0) {
            $client->setUri($resource);
            $client->setAuth($webShopId, $webShopToken);
        }

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

                    throw new \Exception('ratenkauf by easyCredit: '.$userMessage);
            }
        }
        unset($result->wsMessages);
    }

    protected function _throw($msg) {
        $this->_logger->log($msg);
        throw new \Exception($msg);
    }
}
