<?php

declare(strict_types=1);

namespace App\Auth\Test\Builder;

use App\Auth\Entity\User\Email;
use App\Auth\Entity\User\Id;
use App\Auth\Entity\User\Network;
use App\Auth\Entity\User\Token;
use App\Auth\Entity\User\User;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class UserBuilder
{
    private Id $id;
    private Email $email;
    private string $hash;
    private DateTimeImmutable $date;
    private Token $joinConfirmToken;
    private bool $active = false;
    private ?Network $networkIdentity = null;

    /**
     * UserBuilder constructor.
     */
    public function __construct()
    {
        $this->id = Id::generate();
        $this->email = new Email('mail@example.com');
        $this->hash = 'hash';
        $this->date = new DateTimeImmutable();
        $this->joinConfirmToken = new Token(Uuid::uuid4()->toString(), $this->date->modify('+1 day'));
    }

    /**
     * @param Token $token
     * @return self
     */
    public function withJoinConfirmToken(Token $token): self
    {
        $clone = clone $this;
        $clone->joinConfirmToken = $token;
        return $clone;
    }

    /**
     * @param Email $email
     * @return $this
     */
    public function withEmail(Email $email): self
    {
        $clone = clone $this;
        $clone->email = $email;
        return $clone;
    }

    /**
     * @param Network|null $identity
     * @return $this
     */
    public function viaNetwork(Network $network = null): self
    {
        $clone = clone $this;
        $clone->networkIdentity = $network ?? new Network('fb', '0000001');
        return $clone;
    }

    /**
     * @return self
     */
    public function active(): self
    {
        $clone = clone $this;
        $clone->active = true;
        return $clone;
    }

    /**
     * @return User
     */
    public function build(): User
    {
        if ($this->networkIdentity !== null) {
            return User::joinByNetwork(
                $this->id,
                $this->date,
                $this->email,
                $this->networkIdentity
            );
        }

        $user = User::requestJoinByEmail(
            $this->id,
            $this->date,
            $this->email,
            $this->hash,
            $this->joinConfirmToken
        );

        if ($this->active) {
            $user->confirmJoin(
                $this->joinConfirmToken->getValue(),
                $this->joinConfirmToken->getExpires()->modify('-1 day')
            );
        }

        return $user;
    }
}
