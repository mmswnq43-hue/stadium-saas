<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Stadium;
use App\Models\Field;
use App\Models\PricingRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ========================
        // 1. Super Admin
        // ========================
        User::create([
            'tenant_id' => null,
            'name'      => 'Super Admin',
            'email'     => 'admin@stadium-saas.com',
            'phone'     => '+966500000000',
            'password'  => Hash::make('Admin@1234'),
            'role'      => 'super_admin',
        ]);

        // ========================
        // 2. Tenant: زين للرياضة
        // ========================
        $zain = Tenant::create([
            'name'          => 'زين للرياضة',
            'slug'          => 'zain-sports',
            'email'         => 'info@zainsports.sa',
            'phone'         => '+966501234567',
            'plan'          => 'professional',
            'status'        => 'active',
            'settings'      => ['tax_rate' => 15, 'currency' => 'SAR'],
        ]);

        $zainOwner = User::create([
            'tenant_id' => $zain->id,
            'name'      => 'أحمد زين',
            'email'     => 'owner@zainsports.sa',
            'phone'     => '+966501234567',
            'password'  => Hash::make('Owner@1234'),
            'role'      => 'owner',
        ]);

        // ملعب رئيسي 1 - الرياض
        $stadium1 = Stadium::create([
            'tenant_id'    => $zain->id,
            'name'         => 'ملاعب زين - حي النخيل',
            'slug'         => 'zain-nakheel',
            'description'  => 'مجمع رياضي متكامل في قلب حي النخيل بالرياض',
            'city'         => 'الرياض',
            'district'     => 'حي النخيل',
            'address'      => 'شارع النخيل، حي النخيل، الرياض',
            'latitude'     => 24.7136,
            'longitude'    => 46.6753,
            'phone'        => '+966114000001',
            'whatsapp'     => '+966501234567',
            'opens_at'     => '06:00',
            'closes_at'    => '24:00',
            'working_days' => [0, 1, 2, 3, 4, 5, 6],
            'amenities'    => ['parking', 'wifi', 'cafeteria', 'showers', 'lockers'],
            'is_active'    => true,
            'is_featured'  => true,
        ]);

        // ملاعب فرعية
        $fields1 = [
            [
                'name'         => 'ملعب كرة قدم A (5×5)',
                'sport_type'   => 'football',
                'size'         => '5x5',
                'capacity'     => 10,
                'surface_type' => 'artificial_grass',
                'price_per_hour' => 150,
                'price_weekend'  => 200,
                'has_lighting' => true,
                'is_covered'   => false,
                'features'     => ['balls', 'bibs'],
            ],
            [
                'name'         => 'ملعب كرة قدم B (7×7)',
                'sport_type'   => 'football',
                'size'         => '7x7',
                'capacity'     => 14,
                'surface_type' => 'artificial_grass',
                'price_per_hour' => 200,
                'price_weekend'  => 250,
                'has_lighting' => true,
                'is_covered'   => false,
                'features'     => ['balls', 'bibs', 'referee'],
            ],
            [
                'name'         => 'ملعب بادل 1',
                'sport_type'   => 'padel',
                'size'         => 'standard',
                'capacity'     => 4,
                'surface_type' => 'artificial_grass',
                'price_per_hour' => 120,
                'price_morning' => 80,
                'price_evening' => 140,
                'has_lighting' => true,
                'is_covered'   => true,
                'has_ac'       => true,
                'features'     => ['rackets', 'balls'],
                'booking_slot_duration' => 60,
            ],
            [
                'name'         => 'ملعب كرة سلة',
                'sport_type'   => 'basketball',
                'size'         => 'full_court',
                'capacity'     => 10,
                'surface_type' => 'wooden',
                'price_per_hour' => 180,
                'has_lighting' => true,
                'is_covered'   => true,
                'has_ac'       => true,
                'features'     => ['balls'],
            ],
        ];

        foreach ($fields1 as $i => $fieldData) {
            $field = Field::create(array_merge($fieldData, [
                'tenant_id'              => $zain->id,
                'stadium_id'             => $stadium1->id,
                'currency'               => 'SAR',
                'min_booking_duration'   => 60,
                'max_booking_duration'   => 180,
                'booking_slot_duration'  => $fieldData['booking_slot_duration'] ?? 60,
                'sort_order'             => $i,
            ]));

            // قواعد تسعير ديناميكية للملعب الأول
            if ($i === 0) {
                PricingRule::create([
                    'tenant_id'   => $zain->id,
                    'field_id'    => $field->id,
                    'name'        => 'سعر المساء (بعد 7م)',
                    'type'        => 'time_based',
                    'start_time'  => '19:00',
                    'end_time'    => '24:00',
                    'price'       => 180,
                    'price_type'  => 'fixed',
                    'priority'    => 2,
                ]);

                PricingRule::create([
                    'tenant_id'    => $zain->id,
                    'field_id'     => $field->id,
                    'name'         => 'سعر نهاية الأسبوع',
                    'type'         => 'day_based',
                    'days_of_week' => [5, 6], // جمعة وسبت
                    'price'        => 200,
                    'price_type'   => 'fixed',
                    'priority'     => 1,
                ]);
            }
        }

        // ========================
        // 3. Tenant: نادي الفيصل
        // ========================
        $faisal = Tenant::create([
            'name'          => 'نادي الفيصل الرياضي',
            'slug'          => 'faisal-club',
            'email'         => 'info@faisalclub.sa',
            'phone'         => '+966502345678',
            'plan'          => 'basic',
            'status'        => 'active',
            'settings'      => ['tax_rate' => 15, 'currency' => 'SAR'],
        ]);

        User::create([
            'tenant_id' => $faisal->id,
            'name'      => 'فيصل العمري',
            'email'     => 'owner@faisalclub.sa',
            'phone'     => '+966502345678',
            'password'  => Hash::make('Owner@1234'),
            'role'      => 'owner',
        ]);

        $stadium2 = Stadium::create([
            'tenant_id'    => $faisal->id,
            'name'         => 'ملاعب الفيصل - جدة',
            'slug'         => 'faisal-jeddah',
            'city'         => 'جدة',
            'district'     => 'حي السلامة',
            'address'      => 'شارع فلسطين، حي السلامة، جدة',
            'latitude'     => 21.4858,
            'longitude'    => 39.1925,
            'phone'        => '+966122000001',
            'opens_at'     => '08:00',
            'closes_at'    => '23:00',
            'working_days' => [0, 1, 2, 3, 4, 5, 6],
            'amenities'    => ['parking', 'cafeteria'],
            'is_active'    => true,
        ]);

        Field::create([
            'tenant_id'             => $faisal->id,
            'stadium_id'            => $stadium2->id,
            'name'                  => 'ملعب فوتسال 1',
            'sport_type'            => 'futsal',
            'size'                  => '5x5',
            'capacity'              => 10,
            'surface_type'          => 'rubber',
            'price_per_hour'        => 100,
            'currency'              => 'SAR',
            'min_booking_duration'  => 60,
            'max_booking_duration'  => 120,
            'booking_slot_duration' => 60,
            'has_lighting'          => true,
            'is_covered'            => true,
            'sort_order'            => 0,
        ]);

        // ========================
        // 4. عميل تجريبي
        // ========================
        User::create([
            'tenant_id' => null,
            'name'      => 'محمد العتيبي',
            'email'     => 'customer@test.com',
            'phone'     => '+966555000001',
            'password'  => Hash::make('Customer@1234'),
            'role'      => 'customer',
        ]);

        $this->command->info('✅ تم إنشاء بيانات البداية بنجاح!');
        $this->command->info('');
        $this->command->info('📌 بيانات الدخول:');
        $this->command->info('  Super Admin : admin@stadium-saas.com / Admin@1234');
        $this->command->info('  زين (Owner) : owner@zainsports.sa  / Owner@1234');
        $this->command->info('  فيصل (Owner): owner@faisalclub.sa  / Owner@1234');
        $this->command->info('  Customer    : customer@test.com     / Customer@1234');
        $this->command->info('');
        $this->command->info('🏟️  Tenants:');
        $this->command->info('  زين : X-Tenant-Slug: zain-sports');
        $this->command->info('  فيصل: X-Tenant-Slug: faisal-club');
    }
}
