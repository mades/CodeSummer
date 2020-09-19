<?php

namespace CodeHuiter\Facilities\Module\Auth\Oauth;

use CodeHuiter\Modifier\Debugger;
use CodeHuiter\Modifier\StringModifier;
use CodeHuiter\Service\Logger;
use CodeHuiter\Service\Network;

class FbOAuthManager implements OAuthManager
{
    private const CALLBACK_SUCCESS_REDIRECT = '/auth/oauth_success/facebook'; /* call function login */
    private const CALLBACK_FAIL_REDIRECT = '/auth/oauth_cancel/facebook'; /* call function login */

    private const LOGGER_TAG = 'FB_OAUTH';

    /**
     * @var Network
     */
    private $network;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var string
     */
    private $siteUrl;

    /**
     * @var string
     */
    private $appId;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var string
     */
    private $iframeSecret;

    /**
     * @var string
     */
    private $lastErrorMessage = '';

    private $genderMapping = [
        'female' => OAuthData::GENDER_FEMALE,
        'male' => OAuthData::GENDER_MALE,
    ];

    public function getLastErrorMessage(): string
    {
        return $this->lastErrorMessage;
    }


    public function __construct(
        Network $network,
        Logger $logger,
        string $siteUrl,
        string $appId,
        string $secret,
        string $iframeSecret
    ) {
        $this->network = $network;
        $this->logger = $logger;
        $this->siteUrl = $siteUrl;
        $this->appId = $appId;
        $this->secret = $secret;
        $this->iframeSecret = $iframeSecret;
    }

    public function addPermission(array $permissions): void
    {
    }

    public function getSourceAccessLink(): string
    {
        $successCallbackUrl = urlencode($this->siteUrl . self::CALLBACK_SUCCESS_REDIRECT);
        $url = 'https://www.facebook.com/dialog/oauth?';
        $url .= 'client_id='.$this->appId;
        $url .= '&display=popup';
        $url .= '&redirect_uri='.$successCallbackUrl.'&response_type=code';
        $url .= '&auth_type=rerequest';
        $url .= '&scope=public_profile'; //,user_gender,user_birthday';
        $url .= '&state='.md5('test'.time());
        return $url;
    }

    public function login(array $getParams): ?OAuthData
    {
        $code = $getParams['code'] ?? '';
        if (!$code) {
            $this->lastErrorMessage = 'Facebook login fail! facebook not return auth code.';
            return null;
        }

        $responseJsonString = $this->network->httpRequest(
            'https://graph.facebook.com/oauth/access_token?client_id=' . $this->appId
            . '&redirect_uri=' . urlencode($this->siteUrl . self::CALLBACK_SUCCESS_REDIRECT)
            . '&client_secret=' . $this->secret . '&code=' . $code . '',
            Network::METHOD_GET
        );
        $response = StringModifier::jsonDecode($responseJsonString);
        $accessToken = $response['access_token'] ?? '';
        if (!$accessToken) {
            $this->lastErrorMessage = 'Facebook login fail! facebook not return access token.';
            return null;
        }
        return $this->getUserData($accessToken);
    }

    public function getUserData(string $accessToken, ?string $appSecretProof = null): ?OAuthData
    {
        $url = 'https://graph.facebook.com/me?'
            . 'fields=id,name,first_name,last_name,picture'
            . '&access_token='.$accessToken;
        if ($appSecretProof){
            $url .= '&appsecret_proof='.$appSecretProof;
        }
        $resp = $this->network->httpRequest($url,Network::METHOD_GET);
        $userData = StringModifier::jsonDecode($resp);

        Debugger::log($userData); exit();

        $userId = $userData['id'] ?? '';
        if (!$userId) {
            $this->lastErrorMessage = 'Facebook login fail! Code1003.';
            return null;
        }
        $birthday = $userData['birthday'] ?? '';
        $birthday = $birthday ? StringModifier::dateConvert($birthday, 'en-m') : '0000-01-01';



        return new OAuthData(
            'facebook',
            $userId,
            $userData['name'] ?? '',
            $userData['first_name'] ?? '',
            $userData['last_name'] ?? '',
            'https://graph.facebook.com/'.$userId.'/picture?type=large',
            $birthday,
            $this->genderMapping[$userData['gender'] ?? ''] ?? OAuthData::GENDER_UNKNOWN,
            []
        );
    }

    public function iFramedLogin(string $accessToken): ?OAuthData
    {
        $appSecretProof= hash_hmac('sha256', $accessToken, $this->iframeSecret);
        return $this->getUserData($accessToken, $appSecretProof);
    }

    public function parse_signed_request($signed_request): ?array
    {
        [$encoded_sig, $payload] = explode('.', $signed_request, 2);
        // decode the data
        $sig = $this->base64_url_decode($encoded_sig);
        $data = StringModifier::jsonDecode($this->base64_url_decode($payload));
        // confirm the signature
        $expected_sig = hash_hmac('sha256', $payload, $this->iframeSecret, $raw = true);
        if ($sig !== $expected_sig) {
            $this->lastErrorMessage = 'Bad Signed JSON signature!';
            $this->logger->withTag(self::LOGGER_TAG)->notice($this->lastErrorMessage);
            return null;
        }
        return $data;
    }

    private function base64_url_decode($input) {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
