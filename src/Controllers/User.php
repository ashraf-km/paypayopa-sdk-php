<?php

namespace PayPay\OpenPaymentAPI\Controller;

use PayPay\OpenPaymentAPI\Client;
use PayPay\OpenPaymentAPI\Models\AccountLinkPayload;
use \Firebase\JWT\JWT;
use GuzzleHttp\Exception\RequestException;

class User extends Controller
{
    /**
     * Stores User Auth Id for operations
     *
     * @var string
     */
    private $userAuthorizationId;
    /**
     * Initializes Code class to manage creation and deletion of data for QR Code generation
     *
     * @param Client $MainInstance Instance of invoking client class
     * @param Array $auth API credentials
     */


    /**
     * Sets user authorization for this controller
     *
     * @param string $userAuthorizationId
     * @return void
     */
    public function setUserAuthorizationId($userAuthorizationId)
    {
        $this->userAuthorizationId = $userAuthorizationId;
    }

    /**
     * Unlink a user from the client
     *
     * @param string|boolean $userAuthorizationId User authorization id. Leave empty if already set.
     * @return mixed
     */
    public function unlinkUser($userAuthorizationId = false)
    {
        if (!$userAuthorizationId) {
            $userAuthorizationId = $this->userAuthorizationId;
        }
        $url = $this->api_url . $this->main()->GetEndpoint('USER_AUTH') . "/$userAuthorizationId";
        $endpoint = 'v2' . $this->main()->GetEndpoint('USER_AUTH') . "/$userAuthorizationId";
        $options = $this->HmacCallOpts('DELETE', $endpoint);

        return $this->doCall(false, 'delete', $url, [], $options);
    }

    /**
     * Create a ACCOUNT LINK QR and display it to the user
     *
     * @param AccountLinkPayload $payload
     * @return mixed
     */
    public function createAccountLinkQrCode($payload)
    {
        $url = $this->api_url . $this->main()->GetEndpoint('SESSIONS');
        $url = str_replace('v2', 'v1', $url);
        $endpoint = '/v1' . $this->main()->GetEndpoint('SESSIONS');
        $data = $payload->serialize();
        $options = $this->HmacCallOpts('POST', $endpoint, 'application/json;charset=UTF-8;', $data);


        $options['TIMEOUT'] = 10;
        if ($data) {
            return $this->doCall(false, 'post', $url, $data, $options);
        }
    }
    /**
     * Decode User Authorization data from token after user is redirected back
     *
     * @param string $encodedString
     * @return array
     */
    public function decodeUserAuth($encodedString)
    {
        $key = base64_decode($this->auth['API_SECRET']);
        $jwt = new JWT();
        return (array) $jwt->decode($encodedString, $key, array('HS256'));
    }
    /**
     * Get the authorization status of a user
     *
     * @param string $userAuthorizationId
     * @return mixed
     */
    public function getUserAuthorizationStatus($userAuthorizationId)
    {
        if (!$userAuthorizationId) {
            $userAuthorizationId = $this->userAuthorizationId;
        }
        $url = $this->api_url . $this->main()->GetEndpoint('USER_AUTH');
        $endpoint = '/v2' . $this->main()->GetEndpoint('USER_AUTH');
        $options = $this->HmacCallOpts('GET', $endpoint);

        return $this->doAuthCall("v2_fetchUserProfileForWebApp", $url, $options, $userAuthorizationId);
    }

    /**
     * Get the masked phone number of the user
     *
     * @param string $userAuthorizationId
     * @return mixed
     */
    public function getMaskedUserProfile($userAuthorizationId)
    {
        if (!$userAuthorizationId) {
            $userAuthorizationId = $this->userAuthorizationId;
        }
        $url = $this->api_url . $this->main()->GetEndpoint('USER_PROFILE_SECURE');
        $endpoint = '/v2' . $this->main()->GetEndpoint('USER_PROFILE_SECURE');
        $options = $this->HmacCallOpts('GET', $endpoint);

        return $this->doAuthCall("v2_fetchUserProfileForWebApp", $url, $options, $userAuthorizationId);
    }
    /**
     * Generic HTTP call function
     *
     * @param string $url
     * @param array $options
     * @param string $userAuthorizationId
     * @return array
     */
    private function doAuthCall($apiId, $url, $options, $userAuthorizationId)
    {
        try {
            $apiInfo = $this->main()->GetApiMapping($apiId);
            $response = $this->main()->http()->get(
                $url,
                [
                    'headers' => $options["HEADERS"],
                    'query' =>  ['userAuthorizationId' => $userAuthorizationId]
                ]
            );
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
            }
        } finally {
            $responseData = json_decode($response->getBody(), true);
            $resultInfo = $responseData["resultInfo"];
            $this->parseResultInfo($apiInfo, $resultInfo, $response->getStatusCode());
            return $responseData;
        }
    }
}
