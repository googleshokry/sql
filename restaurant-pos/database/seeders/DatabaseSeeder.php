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

        // [الاسم بالعربي, الاسم بالإنجليزي, السعر]
        $menu = [
            ['سندوتشات', 'Sandwiches', [
                ['فول', 'Foul', 10], ['طعمية', 'Falafel', 10], ['شاورما فراخ', 'Chicken Shawarma', 65],
                ['شاورما لحمة', 'Beef Shawarma', 85], ['برجر كلاسيك', 'Classic Burger', 120], ['كريسبي', 'Crispy Chicken', 95],
            ]],
            ['وجبات', 'Meals', [
                ['وجبة شاورما', 'Shawarma Meal', 140], ['وجبة كريسبي (3 قطع)', 'Crispy Meal (3 pcs)', 160],
                ['نص فرخة مشوي', 'Half Grilled Chicken', 190], ['كفتة مشوية', 'Grilled Kofta', 210],
            ]],
            ['بيتزا', 'Pizza', [
                ['بيتزا مارجريتا', 'Margherita Pizza', 130], ['بيتزا خضار', 'Veggie Pizza', 140],
                ['بيتزا سجق', 'Sausage Pizza', 165], ['بيتزا سوبر سوبريم', 'Super Supreme Pizza', 195],
            ]],
            ['إضافات', 'Sides', [
                ['بطاطس', 'Fries', 35], ['بطاطس بالجبنة', 'Cheese Fries', 50], ['سلطة كولسلو', 'Coleslaw', 25], ['ثومية', 'Garlic Sauce', 10],
            ]],
            ['مشروبات', 'Drinks', [
                ['بيبسي', 'Pepsi', 25], ['مياه', 'Water', 10], ['عصير برتقال', 'Orange Juice', 40], ['شاي', 'Tea', 15], ['قهوة', 'Coffee', 30],
            ]],
            ['حلويات', 'Desserts', [
                ['أم علي', 'Om Ali', 55], ['رز بلبن', 'Rice Pudding', 35], ['كنافة', 'Kunafa', 60],
            ]],
        ];

        foreach ($menu as $sort => [$categoryName, $categoryEn, $products]) {
            $category = Category::updateOrCreate(['name' => $categoryName], ['name_en' => $categoryEn, 'sort' => $sort]);
            foreach ($products as [$name, $nameEn, $price]) {
                $category->products()->updateOrCreate(['name' => $name], [
                    'name_en' => $nameEn, 'price' => $price, 'cost' => round($price * 0.4, 2),
                ]);
            }
        }

        foreach (range(1, 10) as $n) {
            RestaurantTable::firstOrCreate(['name' => 'T'.$n], ['seats' => 4]);
        }

        foreach ([['وسط البلد', 'Downtown', 15], ['مدينة نصر', 'Nasr City', 25], ['المعادي', 'Maadi', 35],
            ['الزمالك', 'Zamalek', 30], ['المهندسين', 'Mohandessin', 30]] as [$area, $areaEn, $fee]) {
            DeliveryArea::updateOrCreate(['name' => $area], ['name_en' => $areaEn, 'fee' => $fee]);
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
