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

        // Reset singleton instances between tests
        $reflection = new \ReflectionProperty(Role::class, 'instances');
        $reflection->setValue(null, []);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeRole(string $name, string $display, array $caps): Role
    {
        return new class ($name, $display, $caps) extends Role {
            public function __construct(
                private string $name,
                private string $display,
                private array $caps,
            ) {
            }

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
        };
    }

    // --- register() ---

    public function testRegisterCreatesNewRole(): void
    {
        $subscriberRole = new WP_Role('subscriber', ['read' => true]);
        $faqRole = new WP_Role('faq_manager', ['read' => true]);

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('do_action')->justReturn();
        Functions\expect('get_role')
            ->with('subscriber')->andReturn($subscriberRole)
            ->getMock();
        Functions\expect('get_role')
            ->with('faq_manager')->andReturn(null, $faqRole);
        Functions\expect('add_role')
            ->once()
            ->with('faq_manager', 'FAQ Manager', ['read' => true]);

        FaqManagerRole::register();
    }

    public function testRegisterSkipsAddRoleWhenRoleAlreadyExists(): void
    {
        $subscriberRole = new WP_Role('subscriber', ['read' => true]);
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
        Functions\expect('get_role')->with('subscriber')->andReturnNull();

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
        Functions\expect('get_role')
            ->with('subscriber')->andReturn($subscriberRole);
        Functions\expect('get_role')
            ->with('faq_manager')->andReturn(null, $faqRole);
        Functions\when('add_role')->justReturn();

        FaqManagerRole::register();
    }

    public function testRegisterFiresHooks(): void
    {
        $subscriberRole = new WP_Role('subscriber', ['read' => true]);
        $faqRole = new WP_Role('faq_manager', ['read' => true, 'edit_faqs' => true, 'delete_faqs' => true]);

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

        $instance = new FaqManagerRole();
        $result = $instance->getWPRole();

        self::assertSame($faqRole, $result);
    }

    public function testGetWPRoleThrowsWhenRoleNotFound(): void
    {
        Functions\when('get_role')->justReturnNull();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/faq_manager/i');

        $instance = new FaqManagerRole();
        $instance->getWPRole();
    }

    public function testGetWPRoleAppliesFilter(): void
    {
        $original = new WP_Role('faq_manager', []);
        $filtered = new WP_Role('faq_manager', ['extra' => true]);

        Functions\when('get_role')->justReturn($original);
        Functions\expect('apply_filters')
            ->with('rswp_get_wp_role', $original)
            ->andReturn($filtered);

        $instance = new FaqManagerRole();
        $result = $instance->getWPRole();

        self::assertSame($filtered, $result);
    }

    // --- inheritFrom() ---

    public function testInheritFromDefaultIsFilteredSubscriber(): void
    {
        Functions\expect('apply_filters')
            ->with('rswpr_default_inherit_from_role', 'subscriber')
            ->andReturn('subscriber');

        $instance = new FaqManagerRole();
        $result = (new \ReflectionMethod($instance, 'inheritFrom'))->invoke($instance);

        self::assertSame('subscriber', $result);
    }

    public function testInheritFromCanBeOverriddenViaFilter(): void
    {
        Functions\expect('apply_filters')
            ->with('rswpr_default_inherit_from_role', 'subscriber')
            ->andReturn('editor');

        $instance = new FaqManagerRole();
        $result = (new \ReflectionMethod($instance, 'inheritFrom'))->invoke($instance);

        self::assertSame('editor', $result);
    }
}

// Concrete test double — defined outside the test class so it has a stable class name
// (needed for the singleton key and LSB calls like $instance::roleName())
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
