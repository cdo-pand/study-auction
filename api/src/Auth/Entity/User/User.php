<?php

declare(strict_types=1);

namespace App\Auth\Entity\User;

use App\Auth\Service\PasswordHasher;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="auth_users")
 */
class User
{
    /**
     * @ORM\Column(type="auth_user_id")
     * @ORM\Id
     */
    private Id $id;

    /** @ORM\Column(type="datetime_immutable") */
    private DateTimeImmutable $date;

    /** @ORM\Column(type="auth_user_email", unique=true) */
    private Email $email;

    /** @ORM\Column(type="string", nullable=true) */
    private ?string $passwordHash = null;

    /** @ORM\Column(type="auth_user_status", length=16) */
    private Status $status;

    /** @ORM\Embedded(class="Token") */
    private ?Token $joinConfirmToken = null;

    /** @ORM\Embedded(class="Token") */
    private ?Token $passwordResetToken = null;

    /** @ORM\Column(type="auth_user_email", nullable=true) */
    private ?Email $newEmail = null;

    /** @ORM\Embedded(class="Token") */
    private ?Token $newEmailToken = null;

    /** @ORM\Column(type="auth_user_role", length=16) */
    private Role $role;

    /** @ORM\OneToMany(targetEntity="UserNetwork", mappedBy="user", cascade={"all"}, orphanRemoval=true) */
    private Collection $networks;

    /**
     * User constructor.
     * @param Id                $id
     * @param DateTimeImmutable $date
     * @param Email             $email
     * @param Status            $status
     */
    private function __construct(Id $id, DateTimeImmutable $date, Email $email, Status $status)
    {
        $this->id = $id;
        $this->date = $date;
        $this->email = $email;
        $this->status = $status;
        $this->role = Role::user();
        $this->networks = new ArrayCollection();
    }

    /**
     * Named constructor for create join user with email
     * @param Id                $id
     * @param DateTimeImmutable $date
     * @param Email             $email
     * @param string            $passwordHash
     * @param Token             $token
     * @return self
     */
    public static function requestJoinByEmail(
        Id $id,
        DateTimeImmutable $date,
        Email $email,
        string $passwordHash,
        Token $token
    ): self {
        $user = new self($id, $date, $email, Status::wait());
        $user->passwordHash = $passwordHash;
        $user->joinConfirmToken = $token;
        return $user;
    }

    /**
     * Named constructor for create join user with social networks
     * @param Id                $id
     * @param DateTimeImmutable $date
     * @param Email             $email
     * @param Network           $network
     * @return self
     */
    public static function joinByNetwork(
        Id $id,
        DateTimeImmutable $date,
        Email $email,
        Network $network
    ): self {
        $user = new self($id, $date, $email, Status::active());
        $user->networks->add(new UserNetwork($user, $network));
        return $user;
    }

    /**
     * Attach social network to user
     * @param Network $network
     * @throws DomainException
     */
    public function attachNetwork(Network $network): void
    {
        /** @var UserNetwork $existing */
        foreach ($this->networks as $existing) {
            if ($existing->getNetwork()->isEqualTo($network)) {
                throw new DomainException('Network is already attached.');
            }
        }
        $this->networks->add(new UserNetwork($this, $network));
    }

    /**
     * Will save token only if not yet exists
     * @param Token             $token
     * @param DateTimeImmutable $date
     * @throws DomainException
     */
    public function requestPasswordReset(Token $token, DateTimeImmutable $date): void
    {
        if (!$this->isActive()) {
            throw new DomainException('User is not active.');
        }
        if ($this->passwordResetToken !== null && !$this->passwordResetToken->isExpiredTo($date)) {
            throw new DomainException('Resetting is already requested.');
        }
        $this->passwordResetToken = $token;
    }

    /**
     * @param string            $token
     * @param DateTimeImmutable $date
     * @param string            $hash
     * @throws DomainException
     */
    public function resetPassword(string $token, DateTimeImmutable $date, string $hash): void
    {
        if ($this->passwordResetToken === null) {
            throw new DomainException('Resetting is not requested.');
        }
        $this->passwordResetToken->validate($token, $date);
        $this->passwordResetToken = null;
        $this->passwordHash = $hash;
    }

    /**
     * @param string         $current current password
     * @param string         $new     new password
     * @param PasswordHasher $hasher
     * @throws DomainException
     */
    public function changePassword(string $current, string $new, PasswordHasher $hasher): void
    {
        if ($this->passwordHash === null) {
            throw new DomainException('User does not have an old password.');
        }
        if (!$hasher->validate($current, $this->passwordHash)) {
            throw new DomainException('Incorrect current password.');
        }
        $this->passwordHash = $hasher->hash($new);
    }

    /**
     * Request for change email
     * @param Token             $token
     * @param DateTimeImmutable $date
     * @param Email             $email
     */
    public function requestEmailChanging(Token $token, DateTimeImmutable $date, Email $email): void
    {
        if (!$this->isActive()) {
            throw new DomainException('User is not active.');
        }
        if ($this->email->isEqualTo($email)) {
            throw new DomainException('Email is already same.');
        }
        if ($this->newEmailToken !== null && !$this->newEmailToken->isExpiredTo($date)) {
            throw new DomainException('Changing is already requested.');
        }
        $this->newEmail = $email;
        $this->newEmailToken = $token;
    }

    /**
     * Confirm for change email
     * @param string            $token
     * @param DateTimeImmutable $date
     * @noinspection PhpFieldAssignmentTypeMismatchInspection
     */
    public function confirmEmailChanging(string $token, DateTimeImmutable $date): void
    {
        if ($this->newEmail === null || $this->newEmailToken === null) {
            throw new DomainException('Changing is not requested.');
        }
        $this->newEmailToken->validate($token, $date);
        $this->email = $this->newEmail;
        $this->newEmail = null;
        $this->newEmailToken = null;
    }

    /**
     * @param Role $role
     */
    public function changeRole(Role $role): void
    {
        $this->role = $role;
    }

    /**
     * Check if user active
     * @throws DomainException if user in active status
     */
    public function remove(): void
    {
        if (!$this->isWait()) {
            throw new DomainException('Unable to remove active user.');
        }
    }

    /**
     * @return bool
     */
    public function isWait(): bool
    {
        return $this->status->isWait();
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return Id
     */
    public function getId(): Id
    {
        return $this->id;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * @return Email
     */
    public function getEmail(): Email
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    /**
     * @return Token|null
     */
    public function getJoinConfirmToken(): ?Token
    {
        return $this->joinConfirmToken;
    }

    /**
     * @return Token|null
     */
    public function getPasswordResetToken(): ?Token
    {
        return $this->passwordResetToken;
    }

    /**
     * Confirm and reset token
     * @param string            $token
     * @param DateTimeImmutable $date
     * @throws DomainException
     */
    public function confirmJoin(string $token, DateTimeImmutable $date): void
    {
        if ($this->joinConfirmToken === null) {
            throw new DomainException('Confirmation is not required.');
        }

        $this->joinConfirmToken->validate($token, $date);
        $this->status = Status::active();
        $this->joinConfirmToken = null;
    }

    /**
     * @return Network[]
     */
    public function getNetworks(): array
    {
        /**
         * @var Network[]
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        return $this->networks->map(static function (UserNetwork $network) {
            return $network->getNetwork();
        })->toArray();
    }

    /**
     * @return Email|null
     */
    public function getNewEmail(): ?Email
    {
        return $this->newEmail;
    }

    /**
     * @return Token|null
     */
    public function getNewEmailToken(): ?Token
    {
        return $this->newEmailToken;
    }

    /**
     * @return Role
     */
    public function getRole(): Role
    {
        return $this->role;
    }

    /**
     * If an empty token value is returned from the database, make it null
     *
     * @ORM\PostLoad
     */
    public function checkEmbeds(): void
    {
        if ($this->joinConfirmToken && $this->joinConfirmToken->isEmpty()) {
            $this->joinConfirmToken = null;
        }

        if ($this->passwordResetToken && $this->passwordResetToken->isEmpty()) {
            $this->passwordResetToken = null;
        }

        if ($this->newEmailToken && $this->newEmailToken->isEmpty()) {
            $this->newEmailToken = null;
        }
    }
}
