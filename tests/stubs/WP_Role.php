<?php

// Minimal stub so tests can instantiate WP_Role without a full WordPress install.
// Brain Monkey stubs WordPress functions; this covers the class itself.

class WP_Role
{
    public string $name;
    public array $capabilities;

    public function __construct(string $role, array $capabilities)
    {
        $this->name = $role;
        $this->capabilities = $capabilities;
    }

    public function add_cap(string $cap, bool $grant = true): void
    {
        $this->capabilities[$cap] = $grant;
    }

    public function remove_cap(string $cap): void
    {
        unset($this->capabilities[$cap]);
    }

    public function has_cap(string $cap): bool
    {
        return !empty($this->capabilities[$cap]);
    }
}
