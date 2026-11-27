<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\Driver;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@pos.test'], [
            'name' => 'المدير', 'password' => 'password', 'role' => UserRole::Admin,
        ]);
        User::updateOrCreate(['email' => 'cashier@pos.test'], [
            'name' => 'كاشير 1', 'password' => 'password', 'role' => UserRole::Cashier,
        ]);

        $menu = [
            'سندوتشات' => ['فول' => 10, 'طعمية' => 10, 'شاورما فراخ' => 65, 'شاورما لحمة' => 85, 'برجر كلاسيك' => 120, 'كريسبي' => 95],
            'وجبات' => ['وجبة شاورما' => 140, 'وجبة كريسبي (3 قطع)' => 160, 'نص فرخة مشوي' => 190, 'كفتة مشوية' => 210],
            'بيتزا' => ['بيتزا مارجريتا' => 130, 'بيتزا خضار' => 140, 'بيتزا سجق' => 165, 'بيتزا سوبر سوبريم' => 195],
            'إضافات' => ['بطاطس' => 35, 'بطاطس بالجبنة' => 50, 'سلطة كولسلو' => 25, 'ثومية' => 10],
            'مشروبات' => ['بيبسي' => 25, 'مياه' => 10, 'عصير برتقال' => 40, 'شاي' => 15, 'قهوة' => 30],
            'حلويات' => ['أم علي' => 55, 'رز بلبن' => 35, 'كنافة' => 60],
        ];

        $sort = 0;
        foreach ($menu as $categoryName => $products) {
            $category = Category::updateOrCreate(['name' => $categoryName], ['sort' => $sort++]);
            foreach ($products as $name => $price) {
                $category->products()->updateOrCreate(['name' => $name], ['price' => $price, 'cost' => round($price * 0.4, 2)]);
            }
        }

        foreach (range(1, 10) as $n) {
            RestaurantTable::firstOrCreate(['name' => 'T'.$n], ['seats' => 4]);
        }

        foreach (['وسط البلد' => 15, 'مدينة نصر' => 25, 'المعادي' => 35, 'الزمالك' => 30, 'المهندسين' => 30] as $area => $fee) {
            DeliveryArea::updateOrCreate(['name' => $area], ['fee' => $fee]);
        }

        foreach (['أحمد' => '01000000001', 'محمود' => '01000000002', 'كريم' => '01000000003'] as $name => $phone) {
            Driver::firstOrCreate(['name' => $name], ['phone' => $phone]);
        }

        // حساب تجريبي لتطبيق الطيار مربوط بالطيار "أحمد"
        $driverUser = User::updateOrCreate(['email' => 'driver@pos.test'], [
            'name' => 'أحمد (طيار)', 'password' => 'password', 'role' => UserRole::Driver,
        ]);
        Driver::where('name', 'أحمد')->update(['user_id' => $driverUser->id]);
    }
}
