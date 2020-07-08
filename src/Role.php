<?php

namespace Rockschtar\WordPress\Role;

use RuntimeException;

abstract class Role
{

    protected static $instances = [];
    /**
     * @var int|null
     */
    protected $level = null;

    /**
     * @return static
     */
    protected static function &init()
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        $class = \get_called_class();
        if (self::$instances[$class] === null) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    abstract public function roleName(): string;

    abstract public function displayName(): string;
    
    abstract public function capabilities(): array;

    final public static function register(): void
    {
        $instance = self::init();

        do_action('rswpr_before_register_role', $instance);

        $subscriberRole = get_role('subscriber');

        if ($subscriberRole === null) {
            throw new RuntimeException('Fatal: Subscriber Role is not available');
        }

        $wpRole = get_role($instance->roleName());

        $defaultCapabilities = $subscriberRole->capabilities;

        if ($instance->level !== null) {
            $defaultCapabilities['level_' . $instance->level] = true;
        }

        if ($wpRole === null) {
            add_role($instance->roleName(), $instance->displayName(), $defaultCapabilities);
        }

        $role = $instance->getWPRole();

        foreach ($instance->capabilities() as $capability) {
            $role->add_cap($capability);
        }

        do_action('rswpr_after_register_role', $instance);
    }

    final public static function unregister(): void
    {
        $instance = self::init();
        do_action('rswpr_before_unregister_role', $instance);

        remove_role($instance->roleName());

        do_action('rswpr_after_unregister_role', $instance);
    }

    final public function getWPRole(): \WP_Role
    {
        $instance = self::init();
        $wpRole = get_role($instance->roleName());

        if ($wpRole === null) {
            throw new RuntimeException(sprintf('Fatal: %s role is not available', $instance->roleName()));
        }

        return apply_filters('rswp_get_wp_role', $wpRole);
    }


}