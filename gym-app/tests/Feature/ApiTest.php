<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function memberWithSubscription(): Member
    {
        $member = Member::create(['name' => 'Ali', 'phone' => '01000000000', 'password' => 'secret1']);
        $plan = Plan::create(['name' => 'Month', 'duration_days' => 30, 'price' => 400]);
        $this->actingAs(User::create(['name' => 'a', 'email' => 'a@a.com', 'password' => 'x', 'role' => 'admin']));
        SubscriptionService::create($member, ['plan_id' => $plan->id, 'paid_amount' => 100]);
        auth()->logout();

        return $member;
    }

    public function test_public_endpoints(): void
    {
        Plan::create(['name' => 'Month', 'duration_days' => 30, 'price' => 400]);

        $this->getJson('/api/v1/gym')->assertOk()->assertJsonStructure(['name', 'currency']);
        $this->getJson('/api/v1/plans')->assertOk()->assertJsonPath('data.0.name', 'Month');
        $this->getJson('/api/v1/announcements')->assertOk();
    }

    public function test_member_login_and_home_in_both_languages(): void
    {
        $member = $this->memberWithSubscription();

        $this->postJson('/api/v1/auth/member/login', ['login' => $member->code, 'password' => 'wrong'], ['Accept-Language' => 'en'])
            ->assertStatus(422)->assertJsonPath('errors.login.0', 'Invalid login details');

        $token = $this->postJson('/api/v1/auth/member/login', ['login' => '01000000000', 'password' => 'secret1'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/member/home', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('member.subscription.status_label', 'Active')
            ->assertJsonPath('member.subscription.remaining', 300);

        $this->withToken($token)->getJson('/api/v1/member/home', ['Accept-Language' => 'ar'])
            ->assertJsonPath('member.subscription.status_label', 'ساري');

        $this->withToken($token)->getJson('/api/v1/member/payments')->assertOk()->assertJsonCount(1, 'data');
        // A member token cannot use staff endpoints
        $this->withToken($token)->getJson('/api/v1/staff/dashboard')->assertForbidden();
    }

    public function test_staff_can_check_members_in_and_out(): void
    {
        $member = $this->memberWithSubscription();
        User::create(['name' => 'r', 'email' => 'r@gym.com', 'password' => 'password', 'role' => 'receptionist']);

        $token = $this->postJson('/api/v1/auth/staff/login', ['email' => 'r@gym.com', 'password' => 'password'])
            ->assertOk()->json('token');

        $this->withToken($token)->postJson('/api/v1/staff/checkin', ['code' => $member->code], ['Accept-Language' => 'en'])
            ->assertOk()->assertJsonPath('action', 'in')->assertJsonPath('member.is_inside', true);
        $this->withToken($token)->getJson('/api/v1/staff/inside')->assertJsonCount(1, 'data');
        $this->withToken($token)->postJson('/api/v1/staff/checkin', ['code' => $member->code])
            ->assertOk()->assertJsonPath('action', 'out');
        $this->withToken($token)->postJson('/api/v1/staff/checkin', ['code' => 'nope'])->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/staff/dashboard')->assertOk()->assertJsonMissingPath('income_today');
        $this->withToken($token)->getJson('/api/v1/member/home')->assertForbidden();
    }
}
