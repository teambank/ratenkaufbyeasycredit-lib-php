<?php
namespace Netzkollektiv\EasyCreditApi;

abstract class Config implements \Netzkollektiv\EasyCreditApi\ConfigInterface {

    const BASE_URL = 'https://ratenkauf.easycredit.de/ratenkauf-ws/rest';
    const VERSION = 'v1';

    const API_MODEL_CALCULATION = 'modellrechnung/guenstigsterRatenplan/';
    const API_TEXT_CONSENT = 'texte/zustimmung';

    public function getApiUrl($resource) {
        return implode('/',array(
            self::BASE_URL,
            self::VERSION,
            $resource
        ));
    }
}
