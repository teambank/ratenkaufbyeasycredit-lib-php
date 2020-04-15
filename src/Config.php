<?php
namespace Netzkollektiv\EasyCreditApi;

abstract class Config implements \Netzkollektiv\EasyCreditApi\ConfigInterface {

    const BASE_URL = 'https://ratenkauf.easycredit.de/ratenkauf-ws/rest';
    const VERSION = 'v2';

    const MERCHANT_BASE_URL = 'https://app.easycredit.de/ratenkauf/transaktionsverwaltung-ws/rest';
    const MERCHANT_VERSION = 'v2';

    const API_MODEL_CALCULATION = 'modellrechnung/guenstigsterRatenplan/';
    const API_TEXT_CONSENT = 'texte/zustimmung';

    public function getApiUrl($resource) {
		$version = self::VERSION;
		if (preg_match('~^webshop/.+?/restbetragankaufobergrenze$~',$resource) === 1) {
			$version = 'v1';
		}

        return implode('/',array(
            self::BASE_URL,
            $version,
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
