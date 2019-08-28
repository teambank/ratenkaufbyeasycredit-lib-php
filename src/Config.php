<?php
namespace Netzkollektiv\EasyCreditApi;

abstract class Config implements \Netzkollektiv\EasyCreditApi\ConfigInterface {

    const BASE_URL = 'https://ratenkauf.easycredit.de/ratenkauf-ws/rest';
    const VERSION = 'v1';

    const MERCHANT_BASE_URL = 'https://app.easycredit.de/ratenkauf/transaktionsverwaltung-ws/rest';
    const MERCHANT_VERSION = 'v2';

    const API_MODEL_CALCULATION = 'modellrechnung/guenstigsterRatenplan/';
    const API_TEXT_CONSENT = 'texte/zustimmung';

    public function getApiUrl($resource) {
        return implode('/',array(
            self::BASE_URL,
            self::VERSION,
            $resource
        ));
    }

    public function getMerchantApiUrl($resource) {
         return implode('/',array(
            self::MERCHANT_BASE_URL,
            self::MERCHANT_VERSION,
            $resource
        ));
    }
}
