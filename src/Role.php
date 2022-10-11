<?php

namespace Rockschtar\WordPress\Role;

use RuntimeException;

abstract class Role
{

    protected static $instances = [];

    /**
     * @return static
     */
    protected static function &init()
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        $class = \get_called_class();
        if (!array_key_exists($class, self::$instances) || self::$instances[$class] === null) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    abstract public static function roleName(): string;

    abstract public static function displayName(): string;

    abstract public static function capabilities(): array;

    protected function inheritFrom() : string {
        return apply_filters('rswpr_default_inherit_from_role', 'subscriber');
    }

    final public static function register(): void
    {
        $instance = self::init();

        do_action('rswpr_before_register_role', $instance);

        $defaultCapabilities = [];

        if ($instance->inheritFrom()) {
            $inheritFromRole = get_role($instance->inheritFrom());

            if ($inheritFromRole === null) {
                throw new RuntimeException(sprintf('Fatal: %s Role is not available', $instance->inheritFrom()));
            }

            $defaultCapabilities = $inheritFromRole->capabilities;
        }

        $wpRole = get_role($instance::roleName());

        if ($wpRole === null) {
            add_role($instance::roleName(), $instance::displayName(), $defaultCapabilities);
        }

        $role = $instance->getWPRole();

        foreach ($instance::capabilities() as $capability) {
            $role->add_cap($capability);
        }

        do_action('rswpr_after_register_role', $instance);
    }

    final public static function unregister(): void
    {
        $instance = self::init();
        do_action('rswpr_before_unregister_role', $instance);

        remove_role($instance::roleName());

        do_action('rswpr_after_unregister_role', $instance);
    }

    final public function getWPRole(): \WP_Role
    {
        $instance = self::init();
        $wpRole = get_role($instance::roleName());

        if ($wpRole === null) {
            throw new RuntimeException(sprintf('Fatal: %s role is not available', $instance::roleName()));
        }

        return apply_filters('rswp_get_wp_role', $wpRole);
    }




}
