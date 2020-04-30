<?php
    /**
     * Created by PhpStorm.
     * User: miroslav
     * Date: 23/04/2019
     * Time: 14:51
     */

    namespace Ataccama\Auth;


    use Ataccama\Adapters\Keycloak;
    use Ataccama\Adapters\Utils\UserProfile;
    use Ataccama\Utils\AuthorizationResponse;
    use Ataccama\Utils\KeycloakAPI;
    use Ataccama\Utils\RefreshToken;
    use Nette\Utils\Random;


    /**
     * Class Auth
     * @package Ataccama\Auth
     */
    abstract class Auth
    {
        /** @var Keycloak */
        protected $keycloak;

        /**
         * Re-authentication loads Keycloak, so keep this number as high as possible.
         * This means the re-authentication process with Refresh Token will be skipped for 30 seconds after last
         * re-authentication.
         *
         * @var int
         */
        public $reAuthSleepTime = 30;

        /**
         * Auth constructor.
         * @param Keycloak $keycloak
         */
        public function __construct(Keycloak $keycloak)
        {
            $this->keycloak = $keycloak;
        }

        /**
         * Authorizes and returns TRUE or FALSE.
         * And triggers method authorized() and setAuthorized()
         *
         * @param string|null $authorizationCode
         * @return bool
         * @throws \Ataccama\Exceptions\CurlException
         * @throws \Ataccama\Exceptions\UnknownError
         */
        public function authorize(string $authorizationCode = null): bool
        {
            if (empty($authorizationCode)) {
                return false;
            }

            $this->beforeAuthorization();

            $response = KeycloakAPI::getAuthorization($this->keycloak, $authorizationCode);

            // triggers
            $this->setAuthorized(true);
            $this->authorized($this->getUserProfile($response));

            return true;
        }

        private function beforeAuthorization()
        {
            $this->keycloak->redirectUri = $this->getRedirectUri();
        }

        /**
         * @param string $redirectUri
         */
        public function setRedirectUri(string $redirectUri)
        {
            $this->keycloak->redirectUri = $redirectUri;
        }

        /**
         * Authorizes and returns TRUE or FALSE.
         * And triggers method authorized() and setAuthorized()
         *
         * @return bool
         * @throws \Ataccama\Exceptions\NotDefined
         */
        public function invokeForceAuthorization(): bool
        {
            $refreshToken = $this->getRefreshToken();

            // waiting for next re-auth
            if (time() < ($this->getLastReAuth() + $this->reAuthSleepTime)) {
                return true;
            }

            $this->beforeAuthorization();
            try {
                $response = KeycloakAPI::reauthorize($this->keycloak, $refreshToken);
                $this->setAuthorized(true);
                $this->authorized($this->getUserProfile($response));
                $this->notifyReAuth();
            } catch (\Exception $e) {
                header("Location: " . $this->getLoginUrl());
                exit();
            }

            return true;
        }

        /**
         * @param AuthorizationResponse $response
         * @return UserProfile
         */
        private function getUserProfile(AuthorizationResponse $response): UserProfile
        {
            $userIdentity = $response->accessToken->getUserIdentity();

            return new UserProfile($userIdentity->getId(), $userIdentity->getName(), $userIdentity->getEmail(),
                $response->refreshToken->refreshToken, $response->refreshToken->expiration,
                $userIdentity->getRoles($this->keycloak->clientId), $userIdentity->username);
        }

        /**
         * @throws \Ataccama\Exceptions\CurlException
         */
        public function logoutSSO()
        {
            $refreshToken = $this->getRefreshToken();
            KeycloakAPI::logout($this->keycloak, $refreshToken);
        }

        /**
         * Returns a login URL to Keycloak.
         *
         * @return string
         * @throws \Ataccama\Exceptions\NotDefined
         */
        public function getLoginUrl(): string
        {
            $this->keycloak->redirectUri = $this->getRedirectUri();

            return $this->keycloak->getLoginUrl();
        }

        /**
         * @return string
         * @throws \Ataccama\Exceptions\NotDefined
         */
        public function getRegistrationUrl(): string
        {
            $this->keycloak->redirectUri = $this->getRedirectUri();

            return $this->keycloak->getRegistrationUrl();
        }

        /**
         * @return bool
         */
        abstract public function isAuthorized(): bool;

        /**
         * @param bool $authorized
         * @return bool
         */
        abstract protected function setAuthorized(bool $authorized): bool;

        /**
         * @param UserProfile $userProfile
         * @return bool
         */
        abstract protected function authorized(UserProfile $userProfile): bool;

        /**
         * @return string
         */
        abstract public function getRedirectUri(): string;

        public function setAuthSleep(int $seconds)
        {

        }

        /**
         * @return int
         */
        private function getLastReAuth(): int
        {
            if (isset($_SESSION['auth']['lastReAuth'])) {
                return $_SESSION['auth']['lastReAuth'];
            }

            return 0;
        }

        private function notifyReAuth()
        {
            $_SESSION['auth']['lastReAuth'] = time();
        }

        /**
         * @return RefreshToken
         */
        abstract protected function getRefreshToken(): RefreshToken;
    }