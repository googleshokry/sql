<?php

namespace Database\Seeders;

use App\Models\Attendance;
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
        User::updateOrCreate(['email' => 'admin@gym.com'], [
            'name' => 'مدير النظام', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
        User::updateOrCreate(['email' => 'reception@gym.com'], [
            'name' => 'موظف الاستقبال', 'password' => 'password', 'role' => User::ROLE_RECEPTIONIST,
        ]);

        foreach ([
            ['name' => 'شهر', 'duration_days' => 30, 'price' => 400, 'freeze_days' => 7, 'guest_invitations' => 1, 'color' => '#22c55e'],
            ['name' => '3 شهور', 'duration_days' => 90, 'price' => 1000, 'freeze_days' => 15, 'guest_invitations' => 3, 'color' => '#3b82f6'],
            ['name' => '6 شهور', 'duration_days' => 180, 'price' => 1800, 'freeze_days' => 30, 'guest_invitations' => 5, 'color' => '#8b5cf6'],
            ['name' => 'سنة', 'duration_days' => 365, 'price' => 3000, 'freeze_days' => 60, 'guest_invitations' => 10, 'color' => '#f59e0b'],
            ['name' => '12 حصة', 'duration_days' => 30, 'price' => 250, 'sessions_count' => 12, 'color' => '#06b6d4'],
            ['name' => 'شهر + مدرب خاص', 'duration_days' => 30, 'price' => 1200, 'includes_trainer' => true, 'color' => '#ef4444'],
        ] as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], $plan);
        }

        foreach (['إيجار', 'كهرباء ومياه', 'مرتبات', 'صيانة أجهزة', 'نظافة', 'دعاية وإعلان', 'أخرى'] as $c) {
            ExpenseCategory::firstOrCreate(['name' => $c]);
        }

        if (app()->environment('local') && Member::count() === 0) {
            $this->demo();
        }
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
    }
}
