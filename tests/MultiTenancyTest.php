<?php

use Illuminate\Events\Dispatcher;
use Silber\Bouncer\Database\Role;
use Silber\Bouncer\Database\Models;
use Silber\Bouncer\Database\Ability;

class MultiTenancyTest extends BaseTestCase
{
    /**
     * Reset any scopes that have been applied in a test.
     *
     * @return void
     */
    public function tearDown()
    {
        Models::scope()->reset();

        parent::tearDown();
    }

    public function test_creating_roles_and_abilities_automatically_scopes_them()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->scope()->to(1);

        $bouncer->allow('admin')->to('create', User::class);
        $bouncer->assign('admin')->to($user);

        $this->assertEquals(1, $bouncer->ability()->query()->value('scope'));
        $this->assertEquals(1, $bouncer->role()->query()->value('scope'));
        $this->assertEquals(1, $this->db()->table('permissions')->value('scope'));
        $this->assertEquals(1, $this->db()->table('assigned_roles')->value('scope'));
    }

    public function test_relation_queries_are_properly_scoped()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->scope()->to(1);
        $bouncer->allow($user)->to('create', User::class);

        $bouncer->scope()->to(2);
        $bouncer->allow($user)->to('delete', User::class);

        $bouncer->scope()->to(1);
        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertEquals(1, $abilities->first()->scope);
        $this->assertEquals('create', $abilities->first()->name);
        $this->assertTrue($bouncer->can('create', User::class));
        $this->assertTrue($bouncer->cannot('delete', User::class));

        $bouncer->scope()->to(2);
        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertEquals(2, $abilities->first()->scope);
        $this->assertEquals('delete', $abilities->first()->name);
        $this->assertTrue($bouncer->can('delete', User::class));
        $this->assertTrue($bouncer->cannot('create', User::class));
    }

    public function test_relation_queries_can_be_scoped_exclusively()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->scope()->to(1)->onlyRelations();
        $bouncer->allow($user)->to('create', User::class);

        $bouncer->scope()->to(2);
        $bouncer->allow($user)->to('delete', User::class);

        $bouncer->scope()->to(1);
        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertNull($abilities->first()->scope);
        $this->assertEquals('create', $abilities->first()->name);
        $this->assertTrue($bouncer->can('create', User::class));
        $this->assertTrue($bouncer->cannot('delete', User::class));

        $bouncer->scope()->to(2);
        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertNull($abilities->first()->scope);
        $this->assertEquals('delete', $abilities->first()->name);
        $this->assertTrue($bouncer->can('delete', User::class));
        $this->assertTrue($bouncer->cannot('create', User::class));
    }

    public function test_scoping_also_returns_global_abilities()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->allow($user)->to('create', User::class);

        $bouncer->scope()->to(1)->onlyRelations();
        $bouncer->allow($user)->to('delete', User::class);

        $abilities = $user->abilities()->get();

        $this->assertCount(2, $abilities);
        $this->assertNull($abilities->first()->scope);
        $this->assertEquals('create', $abilities->first()->name);
        $this->assertTrue($bouncer->can('create', User::class));
        $this->assertTrue($bouncer->can('delete', User::class));
    }

    public function test_forbidding_abilities_only_affects_the_current_scope()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->scope()->to(1);
        $bouncer->allow($user)->to('create', User::class);

        $bouncer->scope()->to(2);
        $bouncer->allow($user)->to('create', User::class);
        $bouncer->forbid($user)->to('create', User::class);

        $bouncer->scope()->to(1);

        $this->assertTrue($bouncer->can('create', User::class));

        $bouncer->unforbid($user)->to('create', User::class);

        $bouncer->scope()->to(2);

        $this->assertTrue($bouncer->cannot('create', User::class));
    }

    public function test_assigning_and_retracting_roles_scopes_them_properly()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->scope()->to(1)->onlyRelations();
        $bouncer->assign('admin')->to($user);

        $bouncer->scope()->to(2);
        $bouncer->assign('admin')->to($user);
        $bouncer->retract('admin')->from($user);

        $bouncer->scope()->to(1);
        $this->assertTrue($bouncer->is($user)->an('admin'));

        $bouncer->scope()->to(2);
        $this->assertFalse($bouncer->is($user)->an('admin'));
    }

    public function test_role_abilities_can_be_excluded_from_scopes()
    {
        $bouncer = $this->bouncer($user = User::create());

        $bouncer->scope()->to(1)
                ->onlyRelations()
                ->dontScopeRoleAbilities();

        $bouncer->allow('admin')->to('delete', User::class);

        $bouncer->scope()->to(2);

        $bouncer->assign('admin')->to($user);

        $this->assertTrue($bouncer->can('delete', User::class));
    }
}
