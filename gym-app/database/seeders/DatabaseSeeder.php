<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\SaasPlan;
use App\Services\GymOnboarding;
use App\Support\Tenancy;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Trainer;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'super@gym.com'], [
            'name' => 'Platform Admin', 'password' => 'password', 'is_super_admin' => true,
        ]);

        foreach ([
            ['name' => 'أساسية', 'name_en' => 'Basic', 'price_monthly' => 499, 'price_yearly' => 4990, 'max_members' => 200, 'max_staff' => 3, 'max_branches' => 1, 'mobile_app' => false, 'sort' => 1],
            ['name' => 'احترافية', 'name_en' => 'Pro', 'price_monthly' => 999, 'price_yearly' => 9990, 'max_members' => 1000, 'max_staff' => 10, 'max_branches' => 2, 'sort' => 2],
            ['name' => 'سلاسل الجيمات', 'name_en' => 'Chains', 'price_monthly' => 2499, 'price_yearly' => 24990, 'max_members' => null, 'max_staff' => null, 'max_branches' => 10, 'sort' => 3],
        ] as $plan) {
            SaasPlan::updateOrCreate(['name' => $plan['name']], $plan + ['currency' => 'EGP']);
        }

        if (! app()->environment('local') || Gym::exists()) {
            return;
        }

        // Two demo gyms to show tenant isolation.
        $owner = User::create(['name' => 'مدير جيم الأبطال', 'email' => 'admin@gym.com', 'password' => 'password']);
        $gym = GymOnboarding::create($owner, ['name' => 'جيم الأبطال', 'slug' => 'champions', 'phone' => '01000000000',
            'address' => 'القاهرة - مدينة نصر', 'saas_plan_id' => SaasPlan::where('name_en', 'Pro')->value('id')]);
        $reception = User::create(['name' => 'موظف الاستقبال', 'email' => 'reception@gym.com', 'password' => 'password']);
        $gym->users()->attach($reception->id, ['role' => User::ROLE_RECEPTIONIST]);
        Tenancy::run($gym, fn () => $this->demo());

        $owner2 = User::create(['name' => 'Power Gym Owner', 'email' => 'owner2@gym.com', 'password' => 'password', 'locale' => 'en']);
        $gym2 = GymOnboarding::create($owner2, ['name' => 'Power Gym', 'slug' => 'power-gym', 'locale' => 'en', 'currency' => 'EGP']);
        Tenancy::run($gym2, fn () => Member::create(['name' => 'John Smith', 'phone' => '01222222222', 'password' => '123456']));
    }

    private function demo(): void
    {
        $trainers = collect([
            ['name' => 'كابتن أحمد علي', 'phone' => '01000000001', 'specialty' => 'كمال أجسام', 'salary' => 6000, 'commission_percent' => 10],
            ['name' => 'كابتن سارة محمود', 'phone' => '01000000002', 'specialty' => 'فيتنس وتخسيس', 'gender' => 'female', 'salary' => 5000, 'commission_percent' => 10],
        ])->map(fn ($t) => Trainer::create($t));

        foreach ([
            ['name' => 'مياه معدنية', 'category' => 'مشروبات', 'cost_price' => 4, 'sale_price' => 7, 'stock' => 100],
            ['name' => 'مشروب طاقة', 'category' => 'مشروبات', 'cost_price' => 20, 'sale_price' => 30, 'stock' => 40],
            ['name' => 'واي بروتين 2 كيلو', 'category' => 'مكملات', 'cost_price' => 2200, 'sale_price' => 2700, 'stock' => 5],
            ['name' => 'جوانتي تمرين', 'category' => 'إكسسوارات', 'cost_price' => 80, 'sale_price' => 150, 'stock' => 3],
        ] as $p) {
            Product::create($p);
        }

        $names = ['محمد حسن', 'محمود إبراهيم', 'أحمد مصطفى', 'عمر خالد', 'يوسف سامي', 'مريم أحمد', 'نور محمد', 'كريم عادل',
            'مصطفى السيد', 'هبة علي', 'علي حسين', 'إسلام فتحي', 'منة الله طارق', 'خالد رمضان', 'ياسمين عمرو'];
        $plans = Plan::all();

        foreach ($names as $i => $name) {
            $female = in_array($name, ['مريم أحمد', 'نور محمد', 'هبة علي', 'منة الله طارق', 'ياسمين عمرو']);
            $member = Member::create([
                'name' => $name,
                'phone' => '011'.str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT),
                'gender' => $female ? 'female' : 'male',
                'birth_date' => now()->subYears(rand(18, 45))->subDays(rand(0, 360)),
                'trainer_id' => $trainers->random()->id,
                'joined_at' => now()->subDays(rand(10, 200)),
                'height' => rand(155, 190), 'weight' => rand(55, 105),
            ]);
            $plan = $plans->random();
            $start = now()->subDays(rand(0, $plan->duration_days + 10));
            $sub = SubscriptionService::create($member, [
                'plan_id' => $plan->id, 'start_date' => $start->toDateString(),
                'paid_amount' => rand(0, 3) ? $plan->price : $plan->price / 2, 'method' => 'cash',
            ]);
            $sub->payments()->update(['paid_at' => $start]);
            foreach (range(1, rand(3, 12)) as $d) {
                $in = now()->subDays($d)->setTime(rand(7, 22), rand(0, 59));
                Attendance::create(['member_id' => $member->id, 'subscription_id' => $sub->id, 'check_in_at' => $in, 'check_out_at' => $in->copy()->addMinutes(rand(45, 120))]);
            }
        }
        SubscriptionService::refreshAll();

        Member::where('code', '1002')->first()?->forceFill(['password' => '123456'])->save();
        \App\Models\Announcement::create([
            'title' => 'عرض الصيف: خصم 20% على باقة 3 شهور', 'title_en' => 'Summer offer: 20% off the 3-month plan',
            'body' => 'العرض ساري حتى نهاية الشهر', 'body_en' => 'Valid until the end of the month', 'published_at' => now(),
        ]);
    }
}
