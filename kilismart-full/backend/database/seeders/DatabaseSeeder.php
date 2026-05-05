<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{Hash, DB};
use App\Models\{Category, Supplier, Product, User, Wallet};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding KiliSmart database...');

        DB::transaction(function () {
            $this->seedCategories();
            $this->seedSuppliers();
            $this->seedProducts();
            $this->seedAdminUser();
            $this->seedTestCustomer();
        });

        $this->command->info('✅ Seeding complete!');
    }

    // ──────────────────────────────────────────
    //  CATEGORIES
    // ──────────────────────────────────────────
    private function seedCategories(): void
    {
        $this->command->info('  → Seeding categories...');

        $categories = [
            ['slug'=>'smart-phones',    'name'=>'Smart Phones',        'name_sw'=>'Simu za Kisasa',    'icon'=>'📱','sort_order'=>1],
            ['slug'=>'home-appliances', 'name'=>'Home Appliances',     'name_sw'=>'Vifaa vya Nyumba',  'icon'=>'🏠','sort_order'=>2],
            ['slug'=>'boda-vehicles',   'name'=>'BodaBoda & Vehicles', 'name_sw'=>'Vyombo vya Usafiri','icon'=>'🏍️','sort_order'=>3],
            ['slug'=>'kilimo-farming',  'name'=>'Kilimo & Farming',    'name_sw'=>'Kilimo na Ufugaji', 'icon'=>'🌾','sort_order'=>4],
            ['slug'=>'solar-energy',    'name'=>'Solar & Energy',      'name_sw'=>'Solar na Umeme',    'icon'=>'⚡','sort_order'=>5],
            ['slug'=>'furniture',       'name'=>'Furniture',           'name_sw'=>'Samani',            'icon'=>'🪑','sort_order'=>6],
            ['slug'=>'computers-tv',    'name'=>'Computers & TV',      'name_sw'=>'Kompyuta na TV',    'icon'=>'💻','sort_order'=>7],
            ['slug'=>'school-kids',     'name'=>'School & Kids',       'name_sw'=>'Shule na Watoto',   'icon'=>'🎒','sort_order'=>8],
            ['slug'=>'beauty-health',   'name'=>'Beauty & Health',     'name_sw'=>'Urembo na Afya',    'icon'=>'💄','sort_order'=>9],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], array_merge($cat, ['is_active' => true]));
        }
    }

    // ──────────────────────────────────────────
    //  SUPPLIERS
    // ──────────────────────────────────────────
    private function seedSuppliers(): void
    {
        $this->command->info('  → Seeding suppliers...');

        $suppliers = [
            ['name'=>'TechMoshi Ltd',    'contact_person'=>'James Kileo',   'phone'=>'+255712000101','location'=>'Moshi Mjini',    'lead_days'=>2,'notes'=>'Main phone and electronics supplier. Good prices on Samsung and Tecno.'],
            ['name'=>'Moshi Furniture',  'contact_person'=>'Maria Swai',    'phone'=>'+255754000102','location'=>'Moshi Mjini',    'lead_days'=>3,'notes'=>'Furniture and plastic chairs. Can deliver to all Kilimanjaro.'],
            ['name'=>'HomeGoods Tz',     'contact_person'=>'Peter Mlay',    'phone'=>'+255769000103','location'=>'Arusha',         'lead_days'=>4,'notes'=>'Cookware and household items. Ships from Arusha, 3-4 days to Moshi.'],
            ['name'=>'Solar Africa Ltd', 'contact_person'=>'Grace Kimaro',  'phone'=>'+255713000104','location'=>'Dar es Salaam',  'lead_days'=>5,'notes'=>'Solar panels, batteries, inverters. Includes installation support.'],
            ['name'=>'SleepWell Tz',     'contact_person'=>'Ali Hassan',    'phone'=>'+255787000105','location'=>'Moshi Vijijini', 'lead_days'=>2,'notes'=>'Mattresses and bedding. Local supplier — fastest delivery.'],
            ['name'=>'AgriTools Kili',   'contact_person'=>'John Pallangyo','phone'=>'+255765000106','location'=>'Moshi Mjini',    'lead_days'=>3,'notes'=>'Farming equipment, sprayers, irrigation tools.'],
        ];

        foreach ($suppliers as $sup) {
            Supplier::updateOrCreate(['phone' => $sup['phone']], array_merge($sup, ['status' => 'active']));
        }
    }

    // ──────────────────────────────────────────
    //  PRODUCTS
    // ──────────────────────────────────────────
    private function seedProducts(): void
    {
        $this->command->info('  → Seeding products...');

        $cats = Category::pluck('id', 'slug');
        $sups = Supplier::pluck('id', 'name');

        $products = [
            // ── SMART PHONES ─────────────────────────────────
            [
                'category_id'     => $cats['smart-phones'],
                'supplier_id'     => $sups['TechMoshi Ltd'],
                'name'            => 'Samsung Galaxy A15',
                'name_sw'         => 'Samsung Galaxy A15',
                'slug'            => 'samsung-galaxy-a15',
                'emoji'           => '📱',
                'retail_price'    => 280000,
                'wholesale_price' => 220000,
                'delivery_fee'    => 5000,
                'badge'           => 'hot',
                'sort_order'      => 1,
                'description'     => 'Samsung Galaxy A15 is the best mid-range phone available in Tanzania. Features a stunning 6.5" Super AMOLED display, 50MP camera system, and 5000mAh battery that lasts all day.',
                'description_sw'  => 'Samsung Galaxy A15 ni simu bora ya bei ya wastani Tanzania. Ina screen ya 6.5" Super AMOLED inayong\'aa vizuri, kamera ya 50MP, na betri ya 5000mAh inayodumu siku nzima.',
                'specs'           => [
                    ['l'=>'Skrini',    'v'=>'6.5" Super AMOLED, 90Hz'],
                    ['l'=>'Processor', 'v'=>'MediaTek Helio G99'],
                    ['l'=>'RAM',       'v'=>'4GB DDR4'],
                    ['l'=>'Hifadhi',   'v'=>'128GB (expandable 1TB)'],
                    ['l'=>'Kamera',    'v'=>'50MP + 5MP + 2MP'],
                    ['l'=>'Selfie',    'v'=>'13MP'],
                    ['l'=>'Betri',     'v'=>'5000mAh, Fast Charge 25W'],
                    ['l'=>'OS',        'v'=>'Android 14, One UI 6'],
                    ['l'=>'SIM',       'v'=>'Dual SIM Nano'],
                    ['l'=>'Uzito',     'v'=>'Gramu 197'],
                ],
                'status' => 'active',
            ],
            [
                'category_id'     => $cats['smart-phones'],
                'supplier_id'     => $sups['TechMoshi Ltd'],
                'name'            => 'Tecno Spark 20',
                'name_sw'         => 'Tecno Spark 20',
                'slug'            => 'tecno-spark-20',
                'emoji'           => '📱',
                'retail_price'    => 195000,
                'wholesale_price' => 148000,
                'delivery_fee'    => 5000,
                'badge'           => 'new',
                'sort_order'      => 2,
                'description_sw'  => 'Tecno Spark 20 ni simu mpya yenye selfie ya 32MP na hifadhi kubwa ya 256GB kwa bei nzuri.',
                'specs'           => [
                    ['l'=>'Skrini',    'v'=>'6.6" IPS LCD, 90Hz'],
                    ['l'=>'RAM',       'v'=>'8GB + 8GB virtual'],
                    ['l'=>'Hifadhi',   'v'=>'256GB'],
                    ['l'=>'Kamera',    'v'=>'64MP AI Triple'],
                    ['l'=>'Selfie',    'v'=>'32MP AI'],
                    ['l'=>'Betri',     'v'=>'5000mAh, Fast Charge 18W'],
                    ['l'=>'OS',        'v'=>'Android 14'],
                ],
                'status' => 'active',
            ],
            [
                'category_id'     => $cats['smart-phones'],
                'supplier_id'     => $sups['TechMoshi Ltd'],
                'name'            => 'Itel P55',
                'name_sw'         => 'Itel P55',
                'slug'            => 'itel-p55',
                'emoji'           => '📱',
                'retail_price'    => 115000,
                'wholesale_price' => 84000,
                'delivery_fee'    => 5000,
                'badge'           => 'sale',
                'sort_order'      => 3,
                'description_sw'  => 'Itel P55 ni simu ya bei nafuu yenye betri kubwa ya 6000mAh. Bora kwa wanaotaka simu ya msingi ya android.',
                'specs'           => [
                    ['l'=>'Skrini',  'v'=>'6.6" IPS LCD'],
                    ['l'=>'RAM',     'v'=>'4GB'],
                    ['l'=>'Hifadhi', 'v'=>'128GB'],
                    ['l'=>'Kamera',  'v'=>'13MP'],
                    ['l'=>'Betri',   'v'=>'6000mAh'],
                ],
                'status' => 'active',
            ],

            // ── HOME APPLIANCES ───────────────────────────────
            [
                'category_id'     => $cats['home-appliances'],
                'supplier_id'     => $sups['SleepWell Tz'],
                'name'            => 'Godoro la Spring 6x6',
                'name_sw'         => 'Godoro la Spring (6×6)',
                'slug'            => 'godoro-spring-6x6',
                'emoji'           => '🛏️',
                'retail_price'    => 195000,
                'wholesale_price' => 145000,
                'delivery_fee'    => 8000,
                'badge'           => '',
                'sort_order'      => 4,
                'description_sw'  => 'Godoro bora la spring chenye unene wa inch 8. Lina spring 288 na pamba ya asili. Linadumu miaka mingi.',
                'specs'           => [
                    ['l'=>'Ukubwa',  'v'=>'6×6 futi (183×183cm)'],
                    ['l'=>'Unene',   'v'=>'Inch 8 (20cm)'],
                    ['l'=>'Springs', 'v'=>'288 Bonnell Spring'],
                    ['l'=>'Juu',     'v'=>'Pamba ya asili'],
                    ['l'=>'Uzito',   'v'=>'Kilo 22'],
                    ['l'=>'Dhamana', 'v'=>'Mwaka 1'],
                ],
                'status' => 'active',
            ],
            [
                'category_id'     => $cats['home-appliances'],
                'supplier_id'     => $sups['HomeGoods Tz'],
                'name'            => 'Sufuria Set 8 vipande',
                'name_sw'         => 'Sufuria Set (8 vipande)',
                'slug'            => 'sufuria-set-8',
                'emoji'           => '🍳',
                'retail_price'    => 85000,
                'wholesale_price' => 62000,
                'delivery_fee'    => 5000,
                'badge'           => 'sale',
                'sort_order'      => 5,
                'description_sw'  => 'Set kamili ya sufuria 8 za stainless steel. Zinafanya kazi gesi na umeme. Vifuniko vya kioo.',
                'specs'           => [
                    ['l'=>'Vipande', 'v'=>'8 (sufuria + vifuniko)'],
                    ['l'=>'Nyenzo',  'v'=>'Stainless Steel 18/10'],
                    ['l'=>'Saizi',   'v'=>'16, 18, 20, 24cm'],
                    ['l'=>'Jiko',    'v'=>'Gesi na Umeme'],
                ],
                'status' => 'active',
            ],
            [
                'category_id'     => $cats['home-appliances'],
                'supplier_id'     => $sups['TechMoshi Ltd'],
                'name'            => 'TV Samsung 32 Smart',
                'name_sw'         => 'TV Samsung 32" Smart',
                'slug'            => 'tv-samsung-32-smart',
                'emoji'           => '📺',
                'retail_price'    => 520000,
                'wholesale_price' => 400000,
                'delivery_fee'    => 8000,
                'badge'           => 'hot',
                'sort_order'      => 6,
                'description_sw'  => 'Smart TV ya Samsung 32 inch na YouTube, Netflix, WiFi. Screen ya HD inayong\'aa vizuri.',
                'specs'           => [
                    ['l'=>'Ukubwa', 'v'=>'32 inch'],
                    ['l'=>'Azimio', 'v'=>'HD Ready 720p'],
                    ['l'=>'Smart',  'v'=>'YouTube, Netflix, WiFi'],
                    ['l'=>'Nguvu',  'v'=>'50W'],
                ],
                'status' => 'active',
            ],

            // ── SOLAR ─────────────────────────────────────────
            [
                'category_id'     => $cats['solar-energy'],
                'supplier_id'     => $sups['Solar Africa Ltd'],
                'name'            => 'Solar Panel 100W + Battery Kit',
                'name_sw'         => 'Solar Panel 100W + Betri (Kit kamili)',
                'slug'            => 'solar-100w-kit',
                'emoji'           => '🌞',
                'retail_price'    => 420000,
                'wholesale_price' => 310000,
                'delivery_fee'    => 10000,
                'badge'           => 'new',
                'sort_order'      => 7,
                'description_sw'  => 'Mfumo kamili wa solar — panel 100W, betri 100Ah, inverter 500W. Unatosha kupiga taa 6, TV, na ku-charge simu. Fundi wetu anafunga.',
                'specs'           => [
                    ['l'=>'Panel',      'v'=>'100W Monocrystalline'],
                    ['l'=>'Betri',      'v'=>'12V 100Ah Deep Cycle'],
                    ['l'=>'Inverter',   'v'=>'500W Pure Sine Wave'],
                    ['l'=>'Controller', 'v'=>'MPPT 20A'],
                    ['l'=>'Taa',        'v'=>'Hadi 6 LED 5W'],
                    ['l'=>'Dhamana',    'v'=>'Panel: Miaka 2, Betri: Mwaka 1'],
                ],
                'status' => 'active',
            ],
            [
                'category_id'     => $cats['solar-energy'],
                'supplier_id'     => $sups['Solar Africa Ltd'],
                'name'            => 'Solar Lantern with Phone Charger',
                'name_sw'         => 'Taa ya Jua na Chaja ya Simu',
                'slug'            => 'solar-lantern',
                'emoji'           => '🔦',
                'retail_price'    => 45000,
                'wholesale_price' => 28000,
                'delivery_fee'    => 3000,
                'badge'           => 'sale',
                'sort_order'      => 8,
                'description_sw'  => 'Taa ya jua inayodumu masaa 12 na USB ya ku-charge simu. IP65 waterproof. Bora kwa maeneo bila umeme.',
                'specs'           => [
                    ['l'=>'Mwanga',   'v'=>'Masaa 12 (mode ya kati)'],
                    ['l'=>'Charge',   'v'=>'USB ya ku-charge simu'],
                    ['l'=>'Mwanga',   'v'=>'200 Lumens Max'],
                    ['l'=>'IP Rating','v'=>'IP65 Waterproof'],
                    ['l'=>'Dhamana',  'v'=>'Miaka 2'],
                ],
                'status' => 'active',
            ],

            // ── FURNITURE ─────────────────────────────────────
            [
                'category_id'     => $cats['furniture'],
                'supplier_id'     => $sups['Moshi Furniture'],
                'name'            => 'Sofa Set Plastiki 4 viti',
                'name_sw'         => 'Sofa Set ya Plastiki (Viti 4)',
                'slug'            => 'sofa-plastiki-4',
                'emoji'           => '🪑',
                'retail_price'    => 120000,
                'wholesale_price' => 88000,
                'delivery_fee'    => 5000,
                'badge'           => 'hot',
                'sort_order'      => 9,
                'description_sw'  => 'Viti 4 vya HDPE plastiki ya ubora wa juu. Vinastahimili mvua, jua, na uzito wa kilo 120 kila kiti.',
                'specs'           => [
                    ['l'=>'Idadi',   'v'=>'4 viti'],
                    ['l'=>'Nyenzo',  'v'=>'HDPE Plastiki'],
                    ['l'=>'Uzito',   'v'=>'Hadi Kilo 120/kiti'],
                    ['l'=>'Rangi',   'v'=>'Nyekundu, Bluu, Kijani, Njano'],
                    ['l'=>'Dhamana', 'v'=>'Miaka 2'],
                ],
                'status' => 'active',
            ],
            [
                'category_id'     => $cats['furniture'],
                'supplier_id'     => $sups['Moshi Furniture'],
                'name'            => 'Dining Table Set 4 chairs',
                'name_sw'         => 'Meza ya Chakula na Viti 4',
                'slug'            => 'meza-chakula-4',
                'emoji'           => '🪵',
                'retail_price'    => 245000,
                'wholesale_price' => 180000,
                'delivery_fee'    => 8000,
                'badge'           => '',
                'sort_order'      => 10,
                'description_sw'  => 'Meza ya mbao ya chakula na viti 4 vya mbao. Inafaa familia ya watu 4-6.',
                'specs'           => [
                    ['l'=>'Nyenzo',  'v'=>'Mbao ya Mninga'],
                    ['l'=>'Ukubwa',  'v'=>'120×80cm'],
                    ['l'=>'Viti',    'v'=>'4 (vinajumuishwa)'],
                    ['l'=>'Dhamana', 'v'=>'Miaka 2'],
                ],
                'status' => 'active',
            ],

            // ── COMPUTERS ────────────────────────────────────
            [
                'category_id'     => $cats['computers-tv'],
                'supplier_id'     => $sups['TechMoshi Ltd'],
                'name'            => 'Lenovo IdeaPad 3 Core i5',
                'name_sw'         => 'Laptop Lenovo IdeaPad 3',
                'slug'            => 'lenovo-ideapad-3',
                'emoji'           => '💻',
                'retail_price'    => 680000,
                'wholesale_price' => 540000,
                'delivery_fee'    => 5000,
                'badge'           => '',
                'sort_order'      => 11,
                'description_sw'  => 'Laptop Lenovo IdeaPad 3 yenye Core i5, RAM 8GB, SSD 256GB, na betri ya masaa 8. Windows 11 tayari. Bora kwa kazi na masomo.',
                'specs'           => [
                    ['l'=>'Processor', 'v'=>'Intel Core i5-1235U'],
                    ['l'=>'RAM',       'v'=>'8GB DDR4'],
                    ['l'=>'Hifadhi',   'v'=>'256GB NVMe SSD'],
                    ['l'=>'Screen',    'v'=>'15.6" Full HD IPS'],
                    ['l'=>'Betri',     'v'=>'45Wh — Masaa 8'],
                    ['l'=>'OS',        'v'=>'Windows 11 Home'],
                    ['l'=>'Uzito',     'v'=>'Kilo 1.65'],
                    ['l'=>'Dhamana',   'v'=>'Mwaka 1'],
                ],
                'status' => 'active',
            ],

            // ── KILIMO ────────────────────────────────────────
            [
                'category_id'     => $cats['kilimo-farming'],
                'supplier_id'     => $sups['AgriTools Kili'],
                'name'            => 'Solar Water Pump 150W',
                'name_sw'         => 'Pampu ya Maji ya Jua (150W)',
                'slug'            => 'solar-pump-150w',
                'emoji'           => '💧',
                'retail_price'    => 350000,
                'wholesale_price' => 260000,
                'delivery_fee'    => 8000,
                'badge'           => 'new',
                'sort_order'      => 12,
                'description_sw'  => 'Pampu ya maji inayotumia nishati ya jua. Inaweza kupeleka maji umbali wa mita 30 kwa lita 50 kwa dakika. Bora kwa kilimo cha umwagiliaji.',
                'specs'           => [
                    ['l'=>'Nguvu',      'v'=>'150W Solar'],
                    ['l'=>'Mtiririko',  'v'=>'Lita 50/dakika'],
                    ['l'=>'Kina',       'v'=>'Mita 30 max'],
                    ['l'=>'Mwelekeo',   'v'=>'Usawa na mwinuko'],
                    ['l'=>'Dhamana',    'v'=>'Miaka 2'],
                ],
                'status' => 'active',
            ],
            [
                'category_id'     => $cats['kilimo-farming'],
                'supplier_id'     => $sups['AgriTools Kili'],
                'name'            => 'Crop Sprayer 16L',
                'name_sw'         => 'Dawa ya Kupulizia (Lita 16)',
                'slug'            => 'sprayer-16l',
                'emoji'           => '🌾',
                'retail_price'    => 95000,
                'wholesale_price' => 68000,
                'delivery_fee'    => 5000,
                'badge'           => '',
                'sort_order'      => 13,
                'description_sw'  => 'Dawa ya kupulizia mazao yenye tanki la lita 16 na bomba refu la mita 1.2. Nyepesi kubeba shambani.',
                'specs'           => [
                    ['l'=>'Ujazo',  'v'=>'Lita 16'],
                    ['l'=>'Bomba',  'v'=>'Mita 1.2'],
                    ['l'=>'Nyenzo', 'v'=>'Plastiki nzito'],
                    ['l'=>'Uzito',  'v'=>'Kilo 2.8 (tupu)'],
                ],
                'status' => 'active',
            ],

            // ── BODABODA ─────────────────────────────────────
            [
                'category_id'     => $cats['boda-vehicles'],
                'supplier_id'     => $sups['Moshi Furniture'],
                'name'            => 'Cargo Bicycle Heavy Duty',
                'name_sw'         => 'Baiskeli ya Mizigo (Heavy Duty)',
                'slug'            => 'baiskeli-mizigo',
                'emoji'           => '🚲',
                'retail_price'    => 280000,
                'wholesale_price' => 210000,
                'delivery_fee'    => 5000,
                'badge'           => 'new',
                'sort_order'      => 14,
                'description_sw'  => 'Baiskeli imara ya kubeba mizigo hadi kilo 80. Fremu nzito ya chuma, jaza kubwa la nyuma, breki nzuri mbele na nyuma.',
                'specs'           => [
                    ['l'=>'Fremu',   'v'=>'Steel 28"'],
                    ['l'=>'Mzigo',   'v'=>'Hadi Kilo 80'],
                    ['l'=>'Breki',   'v'=>'Front na Rear'],
                    ['l'=>'Rangi',   'v'=>'Nyekundu, Nyeusi'],
                    ['l'=>'Dhamana', 'v'=>'Mwaka 1'],
                ],
                'status' => 'active',
            ],

            // ── SCHOOL ───────────────────────────────────────
            [
                'category_id'     => $cats['school-kids'],
                'supplier_id'     => $sups['HomeGoods Tz'],
                'name'            => 'School Bag Complete Set',
                'name_sw'         => 'Mfuko wa Shule (Set Kamili)',
                'slug'            => 'school-bag-set',
                'emoji'           => '🎒',
                'retail_price'    => 45000,
                'wholesale_price' => 30000,
                'delivery_fee'    => 3000,
                'badge'           => 'sale',
                'sort_order'      => 15,
                'description_sw'  => 'Set kamili ya shule — mfuko mkubwa wa mgongo, lunch box, na pencil case. Nyenzo za mvua. Inafaa darasa 1 hadi kidato 6.',
                'specs'           => [
                    ['l'=>'Ujazo',  'v'=>'35 Liters'],
                    ['l'=>'Nyenzo', 'v'=>'Nylon 600D'],
                    ['l'=>'Sehemu', 'v'=>'3 kubwa + 2 ndogo'],
                    ['l'=>'Mvua',   'v'=>'Water Resistant'],
                    ['l'=>'Rangi',  'v'=>'4 zinazochaguliwa'],
                ],
                'status' => 'active',
            ],
        ];

        foreach ($products as $prod) {
            Product::updateOrCreate(
                ['slug' => $prod['slug']],
                $prod
            );
        }

        $this->command->info('  → ' . count($products) . ' products seeded.');
    }

    // ──────────────────────────────────────────
    //  ADMIN USER
    // ──────────────────────────────────────────
    private function seedAdminUser(): void
    {
        $this->command->info('  → Seeding admin user...');

        // Create admin via users table with a special role marker
        // In production, change this password immediately after first login!
        $admin = User::updateOrCreate(
            ['phone' => '+255700000001'],
            [
                'full_name'    => 'KiliSmart Admin',
                'id_type'      => 'nida',
                'id_number'    => 'ADMIN000000000000000',
                'district'     => 'Moshi Mjini',
                'ward'         => 'Mji Mpya',
                'job_type'     => 'Admin',
                'income_range' => 'N/A',
                'password'     => Hash::make('Admin@KiliSmart2024!'),
                'status'       => 'active',
                'region'       => 'Kilimanjaro',
                'gender'       => 'prefer_not_to_say',
                'whatsapp_notifications' => false,
                'phone_verified_at' => now(),
            ]
        );

        // Ensure wallet exists
        Wallet::firstOrCreate(['user_id' => $admin->id], ['balance' => 0]);

        $this->command->info('  → Admin created: phone=+255700000001, password=Admin@KiliSmart2024!');
        $this->command->warn('  ⚠️  CHANGE THE ADMIN PASSWORD after first login!');
    }

    // ──────────────────────────────────────────
    //  TEST CUSTOMER (for demo/testing)
    // ──────────────────────────────────────────
    private function seedTestCustomer(): void
    {
        $this->command->info('  → Seeding test customer...');

        $user = User::updateOrCreate(
            ['phone' => '+255700000002'],
            [
                'full_name'      => 'Amina Test Moshi',
                'id_type'        => 'nida',
                'id_number'      => 'TEST00000000000000001',
                'date_of_birth'  => '1995-06-15',
                'gender'         => 'female',
                'district'       => 'Moshi Mjini',
                'ward'           => 'Mji Mpya',
                'street'         => 'Karibu na KKKT',
                'job_type'       => 'Biashara ndogo',
                'income_range'   => 'TZS 200,000 – 500,000',
                'password'       => Hash::make('Test@1234!'),
                'status'         => 'active',
                'region'         => 'Kilimanjaro',
                'phone_verified_at' => now(),
                'whatsapp_notifications' => true,
            ]
        );

        // Give test customer TZS 2,000 welcome bonus
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 2000, 'bonus_balance' => 2000]
        );

        $this->command->info('  → Test customer: phone=+255700000002, password=Test@1234!');
    }
}
