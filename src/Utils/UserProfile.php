<?php
    /**
     * Created by PhpStorm.
     * User: miroslav
     * Date: 14/05/2019
     * Time: 10:38
     */

    namespace Ataccama\Adapters\Utils;


    use Nette\SmartObject;


    /**
     * Class UserProfile
     * @package Ataccama\Adapters\Utils
     * @property-read string      $id
     * @property-read string      $name
     * @property-read string|null $email
     * @property-read string      $refreshToken
     * @property-read int         $refreshTokenExpiration
     * @property-read string[]    $roles
     * @property-read string      $username
     */
    class UserProfile
    {
        use SmartObject;

        /** @var string */
        protected string $username;
        protected string $name;
        protected string $id;
        protected string $refreshToken;
        protected ?string $email;

        /** @var string[] */
        protected $roles;

        /** @var int */
        protected int $refreshTokenExpiration;

        /**
         * UserProfile constructor.
         *
         * @param string      $id
         * @param string      $name
         * @param string|null $email
         * @param string      $refreshToken
         * @param int         $refreshTokenExpiration
         * @param string[]    $roles
         * @param string      $username
         */
        public function __construct(
            string $id,
            string $name,
            ?string $email,
            string $refreshToken,
            int $refreshTokenExpiration,
            array $roles,
            string $username
        ) {
            $this->id = $id;
            $this->name = $name;
            $this->email = $email;
            $this->refreshToken = $refreshToken;
            $this->refreshTokenExpiration = $refreshTokenExpiration;
            $this->roles = $roles;
            $this->username = $username;
        }

        /**
         * @return string
         */
        public function getUsername(): string
        {
            return $this->username;
        }

        /**
         * @return string
         */
        public function getId(): string
        {
            return $this->id;
        }

        /**
         * @return string
         */
        public function getName(): string
        {
            return $this->name;
        }

        /**
         * @return string|null
         */
        public function getEmail(): ?string
        {
            return $this->email;
        }

        /**
         * @return string
         */
        public function getRefreshToken(): string
        {
            return $this->refreshToken;
        }

        /**
         * @return int
         */
        public function getRefreshTokenExpiration(): int
        {
            return $this->refreshTokenExpiration;
        }

        /**
         * @return string[]
         */
        public function getRoles(): array
        {
            return $this->roles;
        }
    }