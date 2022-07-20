<?php
    /**
     * Created by PhpStorm.
     * User: miroslav
     * Date: 16/04/2019
     * Time: 16:25
     */

    namespace Ataccama\Adapters;

    use Ataccama\Exceptions\MissingParameter;
    use Ataccama\Exceptions\NotDefined;
    use Ataccama\Utils\AccessToken;
    use Ataccama\Utils\KeycloakAPI;
    use Ataccama\Utils\RefreshToken;
    use Nette\SmartObject;


    /**
     * @property-read string       $host
     * @property-read string       $realmId
     * @property-read string       $clientId
     * @property-read AccessToken  $accessToken
     * @property-read RefreshToken $refreshToken;
     * @property-read string       $loginUrl
     * @property-read string|null  $clientSecret
     */
    class Keycloak
    {
        use SmartObject;

        /** @var string */
        protected $host, $realmId, $clientId;

        /** @var string|null */
        protected $clientSecret;

        public $redirectUri;

        /** @var AccessToken */
        protected $accessToken;

        /** @var RefreshToken */
        protected $refreshToken;

        /**
         * Keycloak constructor.
         * @param array $parameters
         * @throws MissingParameter
         */
        public function __construct(array $parameters)
        {
            $requiredParameters = ["host", "realmId", "clientId"];

            foreach ($requiredParameters as $requiredParameter) {
                if (empty($parameters[$requiredParameter])) {
                    throw new MissingParameter(__CLASS__ .
                        " needs parameter called '$requiredParameter', set the parameter in config.neon");
                } else {
                    $this->$requiredParameter = $parameters[$requiredParameter];
                }
            }

            if (isset($parameters["clientSecret"])) {
                $this->clientSecret = $parameters["clientSecret"];
            }
        }

        /**
         * @return string
         * @throws NotDefined
         */
        public function getLoginUrl(): string
        {
            if (empty($this->redirectUri)) {
                throw new NotDefined("Parameter 'redirectUri' is not defined.");
            }

            return "$this->host/realms/$this->realmId/protocol/openid-connect/auth?client_id=$this->clientId&response_type=code&redirect_uri=" .
                urlencode($this->redirectUri);
        }

        /**
         * @return string
         * @throws NotDefined
         */
        public function getRegistrationUrl(): string
        {
            if (empty($this->redirectUri)) {
                throw new NotDefined("Parameter 'redirectUri' is not defined.");
            }

            return "$this->host/realms/$this->realmId/protocol/openid-connect/registrations?client_id=$this->clientId&response_type=code&scope=openid%20email&redirect_uri=" .
                urlencode($this->redirectUri) . "&kc_locale=en";
        }

        /**
         * @return string
         */
        public function getHost()
        {
            return $this->host;
        }

        /**
         * @return string
         */
        public function getRealmId()
        {
            return $this->realmId;
        }

        /**
         * @return string
         */
        public function getClientId()
        {
            return $this->clientId;
        }

        /**
         * @return AccessToken
         * @throws NotDefined
         */
        public function getAccessToken(): AccessToken
        {
            if (!isset($this->accessToken)) {
                throw new NotDefined("AccessToken is missing.");
            }

            return $this->accessToken;
        }

        /**
         * @return RefreshToken
         * @throws NotDefined
         */
        public function getRefreshToken(): RefreshToken
        {
            if (!isset($this->refreshToken)) {
                throw new NotDefined("RefreshToken is missing.");
            }

            return $this->refreshToken;
        }

        /**
         * @param string $authorizationCode
         * @return AccessToken
         * @throws \Ataccama\Exceptions\CurlException
         * @throws \Ataccama\Exceptions\UnknownError
         */
        public function authorize(string $authorizationCode): AccessToken
        {
            $response = KeycloakAPI::getAuthorization($this, $authorizationCode);
            $this->accessToken = $response->accessToken;
            $this->refreshToken = $response->refreshToken;

            $_SESSION['auth']['access_token']['expiration'] = $this->accessToken->expiration;

            return $this->accessToken;
        }

        /**
         * @return string|null
         */
        public function getClientSecret(): ?string
        {
            return $this->clientSecret;
        }
    }
