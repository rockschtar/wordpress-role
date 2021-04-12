# rockschtar/wordpress-role

## Description

WordPress Role abstraction. Developed for usage  with composer based WordPress projects
([roots/bedrock](https://github.com/roots/bedrock) or[johnpbloch/wordpress](https://github.com/johnpbloch/wordpress)).

## Requirements

- PHP 7.1
- [Composer](https://getcomposer.org/) to install

## Install

```
composer require rockschtar/wordpress-role
```

## Usage

### Basic Example
```php

// describe role
use Rockschtar\WordPress\Role\Role;

class FAQManagerRole extends Role
{
    public function roleName(): string
    {
        return 'faq-manager';
    }

    public function capabilities(): array
    {
        return [
          'edit_faq',
          'read_faq',
          'delelte_faq'
        ];
    }

    public function displayName(): string
    {
        return __('FAQ Manager', 'my-textdomain');
    }
}

//register role hook
register_activation_hook(MY_PLUGIN_FILE, 'myprefix_register_faq_role');

function myprefix_register_faq_role() {
    FAQManagerRole::register();
}

//unregister role hook
register_deactivation_hook(MY_PLUGIN_FILE, 'myprefix_unregister_faq_role');

function myprefix_unregister_faq_role() {
    FAQManagerRole::unregister();
}
```


## License
rockschtar/wordpress-role is open source and released under MIT
license. See [LICENSE.md](LICENSE.md) file for more info.

