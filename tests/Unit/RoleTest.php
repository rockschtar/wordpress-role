<?php

namespace Rockschtar\WordPress\Role\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Rockschtar\WordPress\Role\Role;
use RuntimeException;
use WP_Role;

class RoleTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $reflection = new \ReflectionProperty(Role::class, 'instances');
        $reflection->setValue(null, []);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // --- register() ---

    public function testRegisterCreatesNewRole(): void
    {
        $subscriberRole = new WP_Role('subscriber', ['read' => true]);
        $faqRole = new WP_Role('faq_manager', ['read' => true]);

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('do_action')->justReturn();
        Functions\expect('add_role')
            ->once()
            ->with('faq_manager', 'FAQ Manager', ['read' => true]);

        $faqManagerCalls = 0;
        Functions\when('get_role')->alias(
            function (string $role) use ($subscriberRole, $faqRole, &$faqManagerCalls): ?WP_Role {
                if ($role === 'subscriber') {
                    return $subscriberRole;
                }
                return ++$faqManagerCalls > 1 ? $faqRole : null;
            }
        );

        FaqManagerRole::register();
    }

    public function testRegisterSkipsAddRoleWhenRoleAlreadyExists(): void
    {
        $faqRole = new WP_Role('faq_manager', ['read' => true]);

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('do_action')->justReturn();
        Functions\when('get_role')->justReturn($faqRole);
        Functions\expect('add_role')->never();

        FaqManagerRole::register();
    }

    public function testRegisterThrowsWhenInheritedRoleIsMissing(): void
    {
        Functions\when('apply_filters')->returnArg(2);
        Functions\when('do_action')->justReturn();
        Functions\when('get_role')->justReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/subscriber/i');

        FaqManagerRole::register();
    }

    public function testRegisterAddsCapabilitiesToRole(): void
    {
        $subscriberRole = new WP_Role('subscriber', ['read' => true]);
        $faqRole = \Mockery::mock(WP_Role::class);
        $faqRole->shouldReceive('add_cap')->with('edit_faqs')->once();
        $faqRole->shouldReceive('add_cap')->with('delete_faqs')->once();

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('do_action')->justReturn();
        Functions\when('add_role')->justReturn();
        Functions\when('get_role')->alias(
            function (string $role) use ($subscriberRole, $faqRole): ?WP_Role {
                return $role === 'subscriber' ? $subscriberRole : $faqRole;
            }
        );

        FaqManagerRole::register();
    }

    public function testRegisterFiresHooks(): void
    {
        $faqRole = new WP_Role('faq_manager', ['read' => true]);

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('get_role')->justReturn($faqRole);
        Functions\when('add_role')->justReturn();
        Functions\expect('do_action')->with('rswpr_before_register_role', \Mockery::any())->once();
        Functions\expect('do_action')->with('rswpr_after_register_role', \Mockery::any())->once();

        FaqManagerRole::register();
    }

    // --- unregister() ---

    public function testUnregisterRemovesRole(): void
    {
        Functions\when('do_action')->justReturn();
        Functions\expect('remove_role')->once()->with('faq_manager');

        FaqManagerRole::unregister();
    }

    public function testUnregisterFiresHooks(): void
    {
        Functions\when('remove_role')->justReturn();
        Functions\expect('do_action')->with('rswpr_before_unregister_role', \Mockery::any())->once();
        Functions\expect('do_action')->with('rswpr_after_unregister_role', \Mockery::any())->once();

        FaqManagerRole::unregister();
    }

    // --- getWPRole() ---

    public function testGetWPRoleReturnsRole(): void
    {
        $faqRole = new WP_Role('faq_manager', []);

        Functions\when('get_role')->justReturn($faqRole);
        Functions\when('apply_filters')->returnArg(2);

        $result = (new FaqManagerRole())->getWPRole();

        self::assertSame($faqRole, $result);
    }

    public function testGetWPRoleThrowsWhenRoleNotFound(): void
    {
        Functions\when('get_role')->justReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/faq_manager/i');

        (new FaqManagerRole())->getWPRole();
    }

    public function testGetWPRoleAppliesFilter(): void
    {
        $original = new WP_Role('faq_manager', []);
        $filtered = new WP_Role('faq_manager', ['extra' => true]);

        Functions\when('get_role')->justReturn($original);
        Functions\expect('apply_filters')
            ->with('rswp_get_wp_role', $original)
            ->andReturn($filtered);

        $result = (new FaqManagerRole())->getWPRole();

        self::assertSame($filtered, $result);
    }

    // --- inheritFrom() ---

    public function testInheritFromDefaultIsSubscriber(): void
    {
        Functions\expect('apply_filters')
            ->with('rswpr_default_inherit_from_role', 'subscriber')
            ->andReturn('subscriber');

        $result = (new \ReflectionMethod(FaqManagerRole::class, 'inheritFrom'))
            ->invoke(new FaqManagerRole());

        self::assertSame('subscriber', $result);
    }

    public function testInheritFromCanBeOverriddenViaFilter(): void
    {
        Functions\expect('apply_filters')
            ->with('rswpr_default_inherit_from_role', 'subscriber')
            ->andReturn('editor');

        $result = (new \ReflectionMethod(FaqManagerRole::class, 'inheritFrom'))
            ->invoke(new FaqManagerRole());

        self::assertSame('editor', $result);
    }
}

// Stable class name required for singleton key and LSB calls ($instance::roleName())
class FaqManagerRole extends Role
{
    public static function roleName(): string
    {
        return 'faq_manager';
    }

    public static function displayName(): string
    {
        return 'FAQ Manager';
    }

    public static function capabilities(): array
    {
        return ['edit_faqs', 'delete_faqs'];
    }
}
