<?php
    /**
     * Created by PhpStorm.
     * User: miroslav
     * Date: 23/04/2019
     * Time: 09:00
     */

    namespace Ataccama\Utils;


    use Ataccama\Adapters\Keycloak;
    use Ataccama\Adapters\KeycloakExtended;
    use Ataccama\Adapters\Utils\UserProfile;
    use Ataccama\Clients\Keycloak\Env\Users\User;
    use Ataccama\Exceptions\CurlException;
    use Ataccama\Exceptions\NotDefined;
    use Ataccama\Exceptions\UnknownError;


    /**
     * Class KeycloakAPI
     * @package Ataccama\Utils
     */
    class KeycloakAPI
    {
        /**
         * @param Keycloak $keycloak
         * @param string   $authorizationCode
         * @return AuthorizationResponse
         * @throws CurlException
         * @throws UnknownError
         */
        public static function getAuthorization(Keycloak $keycloak, string $authorizationCode): AuthorizationResponse
        {
            $request = [
                'grant_type'   => 'authorization_code',
                'code'         => $authorizationCode,
                'client_id'    => $keycloak->clientId,
                'redirect_uri' => $keycloak->redirectUri
            ];

            if (!empty($keycloak->clientSecret)) {
                $request["client_secret"] = $keycloak->clientSecret;
            }

            $response = Curl::post("$keycloak->host/realms/$keycloak->realmId/protocol/openid-connect/token", [
                "Content-Type" => "application/x-www-form-urlencoded"
            ], $request);

            if (isset($response->body->error)) {
                throw new CurlException($response->body->error . ": " . $response->body->error_description);
            }

            if (isset($response->body->access_token)) {
                return new AuthorizationResponse($response->body);
            }

            throw new UnknownError("???");
        }

        /**
         * @param KeycloakExtended $keycloak
         * @return AuthorizationResponse
         * @throws CurlException
         * @throws UnknownError
         */
        public static function getApiAuthorization(KeycloakExtended $keycloak): AuthorizationResponse
        {
            $response = Curl::post("$keycloak->host/realms/$keycloak->realmId/protocol/openid-connect/token", [
                "Content-Type" => "application/x-www-form-urlencoded"
            ], [
                'grant_type'    => 'password',
                'client_id'     => $keycloak->apiClientId,
                'client_secret' => $keycloak->apiClientSecret,
                'username'      => $keycloak->apiUsername,
                'password'      => $keycloak->apiPassword
            ]);

            if (isset($response->body->error)) {
                throw new CurlException($response->body->error . ": " . $response->body->error_description);
            }

            if (isset($response->body->access_token)) {
                return new AuthorizationResponse($response->body);
            }

            throw new UnknownError("???");
        }

        /**
         * @param KeycloakExtended $keycloak
         * @param string           $username
         * @param string           $firstname
         * @param string           $lastname
         * @param string           $email
         * @param bool             $enabled
         * @param array            $groups
         * @param bool             $emailVerified
         * @return bool
         * @throws CurlException
         */
        public static function createUser(
            KeycloakExtended $keycloak,
            string $username,
            string $firstname,
            string $lastname,
            string $email,
            bool $enabled = true,
            array $groups = ['default-group'],
            bool $emailVerified = false
        ): bool {
            $request = [
                'username'  => $username,
                'firstName' => $firstname,
                'lastName'  => $lastname,
                "email"     => $email,
                "enabled"   => $enabled,
                "groups"    => $groups
            ];
            if ($emailVerified) {
                $request["emailVerified"] = true;
            }

            $response = Curl::post("$keycloak->host/admin/realms/$keycloak->realmId/users", [
                "Content-Type"  => "application/json",
                "Authorization" => "Bearer " . $keycloak->apiAccessToken->bearer
            ], json_encode($request));

            if ($response->code == 201) {
                return true;
            }

            throw new CurlException("User creation failed. HTTP response code: $response->code");
        }

        /**
         * @param KeycloakExtended $keycloak
         * @param string           $email
         * @return User
         * @throws CurlException
         */
        public static function getUserByEmail(
            KeycloakExtended $keycloak,
            string $email
        ): User {
            $response = Curl::get("$keycloak->host/admin/realms/$keycloak->realmId/users?email=" .
                urlencode($email), [
                "Content-Type"  => "application/json",
                "Authorization" => "Bearer " . $keycloak->apiAccessToken->bearer
            ]);

            if ($response->code == 200) {
                foreach ($response->body as $user) {
                    if ($user->email == $email) {
                        return new User($user->id, $user->firstName, $user->lastName, $user->email);
                    }
                }

                throw new CurlException("An user identified by an email $email does not exist.");
            }

            throw new CurlException("Getting user by email failed. HTTP response code: $response->code ($response->error)");
        }

        /**
         * @param Keycloak     $keycloak
         * @param RefreshToken $userRefreshToken
         * @return AuthorizationResponse
         * @throws CurlException
         * @throws UnknownError
         */
        public static function reauthorize(Keycloak $keycloak, RefreshToken $userRefreshToken): AuthorizationResponse
        {
            $response = Curl::post("$keycloak->host/realms/$keycloak->realmId/protocol/openid-connect/token", [
                "Content-Type" => "application/x-www-form-urlencoded"
            ], [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $userRefreshToken->refreshToken,
                'client_id'     => $keycloak->clientId,
                'client_secret' => $keycloak->clientSecret,
                'redirect_uri'  => $keycloak->redirectUri
            ]);

            if (isset($response->body->error)) {
                throw new CurlException($response->body->error . ": " . $response->body->error_description ?? "no error description");
            }

            if (isset($response->body->access_token)) {
                return new AuthorizationResponse($response->body);
            }

            throw new UnknownError("Unknown error");
        }

        /**
         * @param Keycloak     $keycloak
         * @param RefreshToken $userRefreshToken
         * @return bool
         * @throws CurlException
         */
        public static function logout(Keycloak $keycloak, RefreshToken $userRefreshToken): bool
        {
            $data = [
                "refresh_token" => $userRefreshToken->refreshToken,
                "client_id"     => $keycloak->clientId
            ];

            if (!empty($keycloak->clientSecret)) {
                $data["client_secret"] = $keycloak->clientSecret;
            }

            $response = Curl::post("$keycloak->host/realms/$keycloak->realmId/protocol/openid-connect/logout", [
                "Content-Type" => "application/x-www-form-urlencoded"
            ], $data);

            if ($response->code == 200 || $response->code == 204) {
                return true;
            }

            if (isset($response->body->error)) {
                throw new CurlException("HTTP $response->code: " . $response->body->error . ": " .
                    $response->body->error_description);
            } else {
                throw new CurlException("HTTP $response->code: " . $response->error);
            }
        }

        public static function userExists(KeycloakExtended $keycloak, string $email): bool
        {
            $response = Curl::get("$keycloak->host/admin/realms/$keycloak->realmId/users?email=" .
                urlencode($email), [
                "Authorization" => "Bearer " . $keycloak->apiAccessToken->bearer
            ]);

            if (is_array($response->body)) {
                foreach ($response->body as $user) {
                    if ((isset($user->username)) && ($email == $user->username) ||
                        (isset($user->email)) && ($email == $user->email)) {
                        return true;
                    }
                }
            }

            return false;
        }

        public static function getUsernameByEmail(KeycloakExtended $keycloak, string $email): ?string
        {
            $response = Curl::get("$keycloak->host/admin/realms/$keycloak->realmId/users?email=" .
                urlencode($email), [
                "Authorization" => "Bearer " . $keycloak->apiAccessToken->bearer
            ]);

            return (isset($response->body[0]->username) && isset($response->body[0]->email) &&
                ($email == $response->body[0]->email)) ? $response->body[0]->username : null;
        }

        /**
         * @param KeycloakExtended $keycloak
         * @param string           $keycloakId
         * @param string           $password
         * @param bool             $temporary
         * @return bool
         * @throws CurlException
         */
        public static function setPassword(
            KeycloakExtended $keycloak,
            string $keycloakId,
            string $password,
            bool $temporary = false
        ) {
            $response = Curl::put("$keycloak->host/admin/realms/$keycloak->realmId/users/$keycloakId/reset-password",
                [
                    "Content-Type"  => "application/json",
                    "Authorization" => "Bearer " . $keycloak->apiAccessToken->bearer,
                ], json_encode([
                    "temporary" => $temporary,
                    "type"      => "password",
                    "value"     => $password
                ]));

            if ($response->code == 204) {
                return true;
            }

            if (isset($response->body->error)) {
                throw new CurlException("HTTP $response->code: " . $response->body->error . ": " .
                    $response->body->error_description);
            } else {
                throw new CurlException("HTTP $response->code: " . $response->error);
            }
        }

        /**
         * @param Keycloak $keycloak
         * @param string   $username
         * @param string   $password
         * @return mixed
         * @throws CurlException
         * @throws NotDefined
         */
        public static function logIn(
            Keycloak $keycloak,
            string $username,
            string $password
        ): UserProfile {
            if (empty($keycloak->clientSecret)) {
                throw new NotDefined("Optional parameter 'clientSecret' is not defined. You have to define it for logging via API.");
            }

            $response = Curl::post("$keycloak->host/realms/$keycloak->realmId/protocol/openid-connect/token", [
                "Content-Type" => "application/x-www-form-urlencoded",
            ], [
                "grant_type"    => "password",
                "client_id"     => $keycloak->clientId,
                "client_secret" => $keycloak->clientSecret,
                "username"      => $username,
                "password"      => $password,
                "scope"         => "openid"
            ]);

            if ($response->code == 200) {
                $response = new AuthorizationResponse($response->body);
                $userIdentity = $response->accessToken->getUserIdentity();

                return new UserProfile($userIdentity->getId(), $userIdentity->getName(), $userIdentity->getEmail(),
                    $response->refreshToken->refreshToken, $response->refreshToken->expiration,
                    $userIdentity->getRoles($keycloak->clientId), $userIdentity->username);
            }

            if (isset($response->body->error)) {
                throw new CurlException("HTTP $response->code: " . $response->body->error . ": " .
                    $response->body->error_description);
            } else {
                throw new CurlException("HTTP $response->code: " . $response->error);
            }
        }
    }
