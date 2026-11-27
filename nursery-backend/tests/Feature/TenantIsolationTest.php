<?php

use App\Actions\OnboardNursery;
use App\Models\Branch;
use App\Models\Nursery;
use App\Models\User;
use App\Support\CurrentNursery;
use App\Support\Roles;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (Roles::ALL as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->a = Nursery::factory()->create();
    $this->b = Nursery::factory()->create();

    Branch::factory()->for($this->a)->count(2)->create();
    Branch::factory()->for($this->b)->count(3)->create();

    $this->teacherA = User::factory()->create(['phone' => '01011111111']);
    $this->a->members()->attach($this->teacherA);
    app(OnboardNursery::class)->assignRole($this->a, $this->teacherA, Roles::TEACHER);
});

// --- Model scope ---

it('shows only the current nursery records', function () {
    app(CurrentNursery::class)->set($this->a);

    expect(Branch::count())->toBe(2)
        ->and(Branch::pluck('nursery_id')->unique()->all())->toBe([$this->a->id]);
});

it('cannot load another nursery record by id', function () {
    $foreign = Branch::withoutGlobalScopes()->where('nursery_id', $this->b->id)->first();

    app(CurrentNursery::class)->set($this->a);

    expect(Branch::find($foreign->id))->toBeNull();
});

it('fills nursery_id on create from the current nursery', function () {
    app(CurrentNursery::class)->set($this->b);

    $branch = Branch::create(['name' => ['ar' => 'جديد', 'en' => 'New']]);

    expect($branch->nursery_id)->toBe($this->b->id);
});

it('applies no filter when there is no current nursery (platform / console)', function () {
    app(CurrentNursery::class)->clear();

    expect(Branch::count())->toBe(5);
});

// --- Roles are per nursery ---

it('scopes roles to the nursery they were given in', function () {
    expect($this->teacherA->rolesIn($this->a)->all())->toBe([Roles::TEACHER])
        ->and($this->teacherA->rolesIn($this->b)->all())->toBe([]);
});

// --- API ---

it('requires the X-Nursery-Id header', function () {
    $this->actingAs($this->teacherA, 'sanctum')
        ->getJson('/api/v1/nursery')
        ->assertStatus(400);
});

it('returns 404 for a nursery the user is not a member of', function () {
    $this->actingAs($this->teacherA, 'sanctum')
        ->getJson('/api/v1/nursery', ['X-Nursery-Id' => $this->b->id])
        ->assertNotFound();
});

it('returns only the member nursery branches over the API', function () {
    $this->actingAs($this->teacherA, 'sanctum')
        ->getJson('/api/v1/nursery', ['X-Nursery-Id' => $this->a->id])
        ->assertOk()
        ->assertJsonPath('data.id', $this->a->id)
        ->assertJsonPath('data.roles', [Roles::TEACHER])
        ->assertJsonCount(2, 'data.branches');
});

it('blocks a suspended nursery', function () {
    $this->a->update(['status' => 'suspended']);

    $this->actingAs($this->teacherA, 'sanctum')
        ->getJson('/api/v1/nursery', ['X-Nursery-Id' => $this->a->id])
        ->assertForbidden();
});

it('answers in the requested language', function () {
    $this->actingAs($this->teacherA, 'sanctum')
        ->getJson('/api/v1/nursery', ['X-Nursery-Id' => $this->a->id, 'Accept-Language' => 'en'])
        ->assertJsonPath('data.name', $this->a->getTranslation('name', 'en'));
});

// --- Panels ---

it('lets only super admins into the platform panel', function () {
    $this->actingAs($this->teacherA)->get('/platform')->assertForbidden();

    $admin = User::factory()->create();
    $admin->forceFill(['is_super_admin' => true])->save();

    $this->actingAs($admin)->get('/platform')->assertOk();
});

it('keeps teachers out of the nursery web panel', function () {
    $this->actingAs($this->teacherA)->get("/app/{$this->a->slug}")->assertNotFound();
});

it('lets a nursery admin into their own nursery panel only', function () {
    $admin = User::factory()->create();
    $this->a->members()->attach($admin);
    app(OnboardNursery::class)->assignRole($this->a, $admin, Roles::NURSERY_ADMIN);

    $this->actingAs($admin)->get("/app/{$this->a->slug}")->assertOk();
    $this->actingAs($admin)->get("/app/{$this->b->slug}")->assertNotFound();
});
