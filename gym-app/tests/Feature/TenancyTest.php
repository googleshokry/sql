<?php

namespace Tests\Feature;

use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_is_isolated_between_gyms(): void
    {
        $a = $this->createGym('gym-a');
        $alice = Member::create(['name' => 'Alice', 'phone' => '010']);

        $b = $this->createGym('gym-b');
        $bob = Member::create(['name' => 'Bob', 'phone' => '010']); // same phone is fine in another gym

        $this->assertSame('1001', $alice->code);
        $this->assertSame('1001', $bob->code); // numbering is per gym
        $this->assertSame(['Bob'], Member::pluck('name')->all());
        $this->assertNull(Member::find($alice->id));
        $this->assertCount(5, Plan::all()); // each gym got its own starter plans

        Tenancy::set($a);
        $this->assertSame(['Alice'], Member::pluck('name')->all());

        Tenancy::set(null); // platform level (console / super admin) sees everything
        $this->assertSame(2, Member::count());
    }

    public function test_panel_lists_only_the_current_gyms_members(): void
    {
        $a = $this->createGym('gym-a');
        Member::create(['name' => 'Alice', 'phone' => '010']);
        $b = $this->createGym('gym-b');
        Member::create(['name' => 'Bob', 'phone' => '011']);

        $this->actingAs($a->owner);
        Filament::setTenant($a);
        Tenancy::set($a);

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords(Member::where('name', 'Alice')->get())
            ->assertDontSee('Bob');

        // Staff of gym A cannot open gym B
        $this->get('/admin/gym-b')->assertNotFound();
        $this->get('/admin/gym-a')->assertOk();
    }

    public function test_api_tokens_are_bound_to_their_gym(): void
    {
        $a = $this->createGym('gym-a');
        $plan = Plan::first();
        $alice = Member::create(['name' => 'Alice', 'phone' => '010', 'password' => 'secret1']);
        $this->actingAs($a->owner);
        SubscriptionService::create($alice, ['plan_id' => $plan->id]);

        $b = $this->createGym('gym-b');
        Member::create(['name' => 'Bob', 'phone' => '010', 'password' => 'secret2']);
        Tenancy::set(null);
        $this->app['auth']->forgetGuards(); // API calls below are token-only (no web session)

        // Same phone in two gyms: the gym code decides who logs in
        $this->postJson('/api/v1/auth/member/login', ['login' => '010', 'password' => 'secret1'], ['X-Gym' => 'gym-b'])->assertStatus(422);
        $this->postJson('/api/v1/auth/member/login', ['login' => '010', 'password' => 'secret2'], ['X-Gym' => 'gym-b'])->assertOk();

        // A receptionist of gym B cannot check in gym A's member by code
        $staff = User::create(['name' => 'R', 'email' => 'r@b.test', 'password' => 'password']);
        $staff->gyms()->attach($b->id, ['role' => 'receptionist']);
        $token = $this->postJson('/api/v1/auth/staff/login', ['email' => 'r@b.test', 'password' => 'password'])->assertOk()->json('token');
        $this->withToken($token)->postJson('/api/v1/staff/checkin', ['code' => $alice->code])
            ->assertStatus(422); // finds gym B's member 1001 (Bob) who has no subscription — never Alice
        $this->withToken($token)->getJson("/api/v1/staff/members/{$alice->id}")->assertNotFound();
    }

    public function test_expired_gym_is_sent_to_billing_and_api_is_blocked(): void
    {
        $gym = $this->createGym('late-gym');
        Member::create(['name' => 'M', 'phone' => '010', 'password' => 'secret1']);
        $gym->forceFill(['trial_ends_at' => now()->subDay()])->save();
        Tenancy::set(null);

        $this->actingAs($gym->owner)->get('/admin/late-gym/members')->assertRedirect('/admin/late-gym/billing');
        $this->get('/admin/late-gym/billing')->assertOk();

        $this->postJson('/api/v1/auth/member/login', ['login' => '010', 'password' => 'secret1'], ['X-Gym' => 'late-gym'])
            ->assertStatus(422);

        // Paying the invoice restores access
        \App\Models\GymInvoice::issueFor($gym)->markPaid('cash');
        $this->assertTrue($gym->fresh()->hasAccess());
        $this->get('/admin/late-gym/members')->assertOk();
    }

    public function test_plan_member_limit_is_enforced(): void
    {
        $this->createGym('small-gym', ['max_members' => 1]);
        Member::create(['name' => 'One', 'phone' => '010']);

        $this->expectException(ValidationException::class);
        Member::create(['name' => 'Two', 'phone' => '011']);
    }

    public function test_signup_creates_gym_with_trial_and_owner_role(): void
    {
        $user = User::create(['name' => 'New', 'email' => 'new@x.test', 'password' => 'password']);
        \App\Models\SaasPlan::create(['name' => 'Basic', 'price_monthly' => 100, 'price_yearly' => 1000]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Pages\Tenancy\RegisterGym::class)
            ->fillForm(['name' => 'My Gym', 'slug' => 'my-gym'])
            ->call('register')
            ->assertHasNoFormErrors();

        $gym = Gym::where('slug', 'my-gym')->firstOrFail();
        $this->assertTrue($gym->onTrial());
        $this->assertSame('admin', $user->fresh()->roleIn($gym));
        $this->assertSame(5, Plan::withoutGlobalScopes()->where('gym_id', $gym->id)->count());
    }

    public function test_super_admin_panel_is_restricted(): void
    {
        $gym = $this->createGym('gym-a');
        Tenancy::set(null);
        $this->actingAs($gym->owner->fresh())->get('/super')->assertForbidden();
        $this->get('/admin/gym-a')->assertOk(); // but can use their gym
    }

    public function test_super_admin_sees_all_gyms(): void
    {
        $this->createGym('gym-a');
        Tenancy::set(null);

        $super = User::create(['name' => 'S', 'email' => 's@x.test', 'password' => 'password', 'is_super_admin' => true]);
        $this->actingAs($super)->get('/super')->assertOk();
        foreach (['/super/saas-plans', '/super/gym-invoices', '/super/users'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/super/gyms')->assertOk()->assertSee('Gym gym-a');
    }
}
