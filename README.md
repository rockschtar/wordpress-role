# rockschtar/wordpress-role

[![CI](https://github.com/rockschtar/wordpress-role/actions/workflows/ci.yml/badge.svg)](https://github.com/rockschtar/wordpress-role/actions/workflows/ci.yml)

A PHP library that lets you define WordPress roles as typed classes. Instead of scattering `add_role()` / `remove_role()` calls across your plugin, each role becomes a self-contained class that knows its own name, display label, and capabilities. Register and unregister it with a single static call.

Designed for composer-based WordPress projects such as [roots/bedrock](https://github.com/roots/bedrock) or [johnpbloch/wordpress](https://github.com/johnpbloch/wordpress).

## Requirements

- PHP 8.3
- [Composer](https://getcomposer.org/)

## Installation

```bash
composer require rockschtar/wordpress-role
```

## How to

### 1. Define a role

Extend `Role` and implement the three required methods. All methods must be `static`.

```php
use Rockschtar\WordPress\Role\Role;

class FAQManagerRole extends Role
{
    public static function roleName(): string
    {
        return 'faq_manager';
    }

    public static function displayName(): string
    {
        return __('FAQ Manager', 'my-textdomain');
    }

    public static function capabilities(): array
    {
        return [
            'edit_faq',
            'read_faq',
            'delete_faq',
        ];
    }
}
```

By default the role inherits all capabilities from the built-in `subscriber` role. Override `inheritFrom()` to use a different base:

```php
protected function inheritFrom(): string
{
    return 'editor';
}
```

### 2. Register and unregister

Wire the role to your plugin's activation and deactivation hooks:

```php
register_activation_hook(MY_PLUGIN_FILE, [FAQManagerRole::class, 'register']);
register_deactivation_hook(MY_PLUGIN_FILE, [FAQManagerRole::class, 'unregister']);
```

When your plugin defines several roles, group them together:

```php
const ROLES = [
    FAQManagerRole::class,
    EditorPlusRole::class,
];

register_activation_hook(MY_PLUGIN_FILE, function () {
    foreach (ROLES as $role) {
        $role::register();
    }
});

register_deactivation_hook(MY_PLUGIN_FILE, function () {
    foreach (ROLES as $role) {
        $role::unregister();
    }
});
```

## Examples

**Check if the current user has the role:**

```php
if (current_user_can(FAQManagerRole::roleName())) {
    // ...
}
```

**Assign the role to a user**, e.g. after registration:

```php
$user->set_role(FAQManagerRole::roleName());
```

**Use the role in a REST API permission callback:**

```php
register_rest_route('my-plugin/v1', '/faqs', [
    'methods'             => 'GET',
    'callback'            => 'my_plugin_get_faqs',
    'permission_callback' => fn() => current_user_can(FAQManagerRole::roleName()),
]);
```


## Available hooks and filters

| Hook | Type | Description |
|---|---|---|
| `rswpr_before_register_role` | action | Fires before a role is registered. Receives the `Role` instance. |
| `rswpr_after_register_role` | action | Fires after a role is registered. Receives the `Role` instance. |
| `rswpr_before_unregister_role` | action | Fires before a role is removed. Receives the `Role` instance. |
| `rswpr_after_unregister_role` | action | Fires after a role is removed. Receives the `Role` instance. |
| `rswpr_default_inherit_from_role` | filter | Override the default inherited role (`subscriber`). |
| `rswp_get_wp_role` | filter | Filter the `WP_Role` object returned by `getWPRole()`. |

## License

rockschtar/wordpress-role is open source and released under the MIT license. See [LICENSE](LICENSE) for details.
