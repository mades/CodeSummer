<?php

namespace CodeHuiter\Pattern\Modules\Auth\Models;

interface UserInterface
{
    /**
     * @return int
     */
    public function getId();

    /**
     * @param int $id
     */
    public function setId(int $id): void;

    /**
     * @return string
     */
    public function getLogin(): string;

    /**
     * @param string $login
     */
    public function setLogin(string $login): void;

    /**
     * @return string
     */
    public function getEmail(): string;

    /**
     * @param string $email
     */
    public function setEmail(string $email): void;

    /**
     * @return bool
     */
    public function getEmailConfirmed(): bool;

    /**
     * @param bool $confirmed
     */
    public function setEmailConfirmed(bool $confirmed): void;

    /**
     * @return string
     */
    public function getPassHash(): string;

    /**
     * @param string $passHash
     */
    public function setPassHash(string $passHash): void;

    /**
     * @return string
     */
    public function getTimezone(): string;

    /**
     * @return string
     */
    public function getSignature(): string;

    /**
     * @param string $signature
     */
    public function setSignature(string $signature): void;

    /**
     * @return int
     */
    public function getSignatureTime(): int;

    /**
     * @param int $timestamp
     */
    public function setSignatureTime(int $timestamp): void;

    /**
     * @return int
     */
    public function getLastActive(): int;

    /**
     * @param int $lastActive
     */
    public function setLastActive(int $lastActive): void;

    /**
     * @return string
     */
    public function getLastIp(): string;

    /**
     * @param string $ip
     */
    public function setLastIp(string $ip): void;

    /**
     * @return array
     */
    public function getDataInfo(): array;

    /**
     * @param array $data
     */
    public function setDataInfo(array $data): void;

    /**
     * @param int $groupCode
     * @return bool
     */
    public function isInGroup(int $groupCode): bool;

    /**
     * @param int[] $groups
     * @param bool $withSave
     */
    public function setGroups(array $groups, bool $withSave = true): void;

    /**
     * @param int $group
     */
    public function addGroup(int $group): void;

    /**
     * @param int $group
     */
    public function removeGroup(int $group): void;

    /**
     * @return int[]
     */
    public function getGroups(): array;

    /**
     * @return UserInterface
     */
    public function saveUser(): UserInterface;

    /**
     * @return void
     */
    public function deleteUser(): void;
}
